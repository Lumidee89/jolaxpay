<?php

namespace App\Domain\Notifications;

use App\Enums\DeliveryChannel;
use App\Enums\NotificationCategory;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Unified dispatcher for SMS, email, WhatsApp, and in-app notifications
 * (TRD §5) — including the Outcome Confirmation prompt. Always called
 * from inside a queued job, never inline in the request cycle (TRD §5,
 * Implementation Plan §2).
 *
 * Channel drivers default to 'log' (see config/notify.php) so delivery is
 * fully exercisable — and asserted on in tests — before SMS/WhatsApp
 * gateway credentials exist. In-app delivery also fans out to the user's
 * registered Expo Push devices when PUSH_DRIVER=expo.
 */
class NotificationDispatcher
{
    public function __construct(private readonly BulkSmsLiveGateway $bulkSms) {}

    public function send(User $user, string $type, DeliveryChannel $channel, array $payload = []): NotificationLog
    {
        $log = NotificationLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'channel' => $channel,
            'payload' => $payload,
            'status' => 'queued',
        ]);

        // PRD §14: a muted category is skipped entirely — never sent, and
        // never shown in the in-app feed (NotificationController::index()
        // excludes 'skipped' the same way it excludes otp_* logs). OTP
        // codes have no category (NotificationCategory::fromType() returns
        // null for them) so they're never gated here.
        if ($this->isMuted($user, $type)) {
            $log->update(['status' => 'skipped']);

            return $log;
        }

        try {
            $this->deliver($channel, $user, $type, $payload);
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed']);
            Log::error('Notification delivery failed', [
                'notification_log_id' => $log->id,
                'channel' => $channel->value,
                'error' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    protected function isMuted(User $user, string $type): bool
    {
        $category = NotificationCategory::fromType($type);
        if (! $category) {
            return false;
        }

        $muted = $user->notificationPreference?->muted_categories ?? [];

        return in_array($category->value, $muted, true);
    }

    protected function deliver(DeliveryChannel $channel, User $user, string $type, array $payload): void
    {
        match ($channel) {
            DeliveryChannel::Sms => $this->viaSms($user->phone_number, $type, $payload),
            DeliveryChannel::Whatsapp => $this->viaGatewayOrLog('whatsapp', $user->phone_number, $type, $payload),
            DeliveryChannel::Email => $this->viaGatewayOrLog('email', $user->email, $type, $payload),
            DeliveryChannel::InApp => $this->viaExpoPush($user, $type, $payload),
        };
    }

    protected function viaGatewayOrLog(string $configKey, ?string $destination, string $type, array $payload): void
    {
        $driver = config("notify.{$configKey}.driver", 'log');

        if ($driver === 'log') {
            Log::info("[notify:{$configKey}] {$type} -> {$destination}", $payload);

            return;
        }

        throw new \RuntimeException("Notification driver [{$driver}] for [{$configKey}] is not implemented yet.");
    }

    /** The one 'sms' driver with a real implementation — see BulkSmsLiveGateway. */
    protected function viaSms(?string $destination, string $type, array $payload): void
    {
        $driver = config('notify.sms.driver', 'log');

        if ($driver === 'log') {
            Log::info("[notify:sms] {$type} -> {$destination}", $payload);

            return;
        }

        if ($driver === 'bulksmslive') {
            if (! $destination) {
                throw new \RuntimeException('Cannot send SMS: this user has no phone number on file.');
            }

            if (! $this->bulkSms->send($destination, $this->smsCopy($type, $payload))) {
                throw new \RuntimeException('BulkSMSLive failed to send the SMS — see the preceding log entry for why.');
            }

            return;
        }

        throw new \RuntimeException("Notification driver [{$driver}] for [sms] is not implemented yet.");
    }

    /**
     * Deliberately terser than pushCopy()'s wording — this is a real SMS
     * charged per segment, not a push notification, and an OTP code
     * needs to read cleanly within a single 160-character segment.
     */
    protected function smsCopy(string $type, array $payload): string
    {
        return match (true) {
            str_starts_with($type, 'otp_') => 'Your JolaxPay verification code is '.($payload['code'] ?? '').'. It expires in 10 minutes. Never share this code with anyone.',
            default => $payload['body'] ?? 'You have a new JolaxPay account update.',
        };
    }

    /** Send one message per active user device through Expo Push Service. */
    protected function viaExpoPush(User $user, string $type, array $payload): void
    {
        if (config('notify.push.driver', 'expo') === 'log') {
            Log::info("[notify:push] {$type} -> user:{$user->id}", $payload);

            return;
        }

        $tokens = $user->devicePushTokens()->pluck('token')->all();
        if ($tokens === []) {
            return;
        }

        [$title, $body] = $this->pushCopy($type, $payload);
        $messages = array_map(fn (string $token) => [
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => ['type' => $type, ...$payload],
        ], $tokens);

        $response = Http::acceptJson()
            ->timeout(10)
            ->post(config('notify.push.endpoint'), $messages);

        if ($response->failed()) {
            throw new \RuntimeException('Expo Push Service returned HTTP '.$response->status());
        }

        foreach ($response->json('data', []) as $index => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($tokens[$index])) {
                $user->devicePushTokens()->where('token', $tokens[$index])->delete();
            }
        }
    }

    /** @return array{string, string} */
    protected function pushCopy(string $type, array $payload): array
    {
        return match ($type) {
            'login_success' => [$payload['title'] ?? 'Welcome back to JolaxPay', $payload['body'] ?? 'You have successfully signed in.'],
            'wallet_funded' => ['Wallet funded', 'Your wallet has been credited with ₦'.number_format((float) ($payload['amount'] ?? 0), 2).'.'],
            'payment_confirmation' => ['Purchase confirmed', 'Your payment has been confirmed and we are generating your token.'],
            'token_delivery' => ['Token delivered', 'Your electricity token is ready. Open JolaxPay to view it.'],
            'referral_reward' => [
                'You earned a referral reward! 🎉',
                ($payload['referred_name'] ?? 'Someone you referred').' made their first purchase — ₦'.number_format((float) ($payload['amount'] ?? 0), 2).' has been added to your wallet.',
            ],
            default => ['JolaxPay update', $payload['body'] ?? 'You have a new account update.'],
        };
    }
}
