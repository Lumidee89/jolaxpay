<?php

namespace App\Domain\Notifications;

use App\Enums\DeliveryChannel;
use App\Models\NotificationLog;
use App\Models\User;
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
 * gateway credentials exist. In-app "delivery" is just the NotificationLog
 * row itself, surfaced to the mobile app via `/v1/notifications` (or the
 * relevant resource) rather than pushed through an external gateway.
 */
class NotificationDispatcher
{
    public function send(User $user, string $type, DeliveryChannel $channel, array $payload = []): NotificationLog
    {
        $log = NotificationLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'channel' => $channel,
            'payload' => $payload,
            'status' => 'queued',
        ]);

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

    protected function deliver(DeliveryChannel $channel, User $user, string $type, array $payload): void
    {
        match ($channel) {
            DeliveryChannel::Sms => $this->viaGatewayOrLog('sms', $user->phone_number, $type, $payload),
            DeliveryChannel::Whatsapp => $this->viaGatewayOrLog('whatsapp', $user->phone_number, $type, $payload),
            DeliveryChannel::Email => $this->viaGatewayOrLog('email', $user->email, $type, $payload),
            DeliveryChannel::InApp => null, // no external send — the NotificationLog row is the delivery
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
}
