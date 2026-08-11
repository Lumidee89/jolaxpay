<?php

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BulkSMSLive.com HTTP API v2 (https://www.bulksmslive.com/bulksmslive_HTTP_SMS_API%202.0.pdf)
 * — backs SMS_DRIVER=bulksmslive. Only used for OTP delivery today (the
 * new-device login challenge in AuthController; see OtpService and
 * NotificationDispatcher::viaSms()).
 *
 * Supports both of BulkSMSLive's auth methods, picked automatically at
 * call time: an API key (Bearer token, POST /v2/app/sendsms) if
 * `notify.sms.api_key` is set, otherwise account email/password
 * (POST /v2/app/sms) if `notify.sms.email`/`notify.sms.password` are.
 *
 * Mirrors PaystackGateway/the VTpass providers' discipline: a rejected
 * send is a normal outcome the caller handles (returns false, logs why),
 * a network failure or malformed response is the same — nothing here
 * throws.
 */
class BulkSmsLiveGateway
{
    private const BASE_URL = 'https://api.bulksmslive.com/v2';

    /** Below this many units, an OTP send succeeding today doesn't mean the next one will. */
    private const LOW_BALANCE_THRESHOLD = 50;

    public function send(string $to, string $message): bool
    {
        $recipient = $this->normalizeRecipient($to);

        if (! $recipient) {
            Log::error('BulkSMSLive: could not normalize recipient phone number — message not sent.', ['to' => $to]);

            return false;
        }

        $params = [
            'message' => $message,
            'sender_name' => (string) config('notify.sms.sender_id', 'JolaxPay'),
            'recipients' => $recipient,
            // Explicit rather than relying on BulkSMSLive's own default:
            // this is what lets the message reach MTN numbers on DND,
            // which OTP delivery can't afford to silently skip.
            'forcednd' => 1,
        ];

        try {
            $response = $this->apiKey()
                ? Http::withToken($this->apiKey())->acceptJson()->asForm()->timeout(15)
                    ->post(self::BASE_URL.'/app/sendsms', $params)
                : Http::acceptJson()->asForm()->timeout(15)
                    ->post(self::BASE_URL.'/app/sms', [...$params, 'email' => config('notify.sms.email'), 'password' => config('notify.sms.password')]);
        } catch (Throwable $e) {
            Log::error('BulkSMSLive request failed', ['error' => $e->getMessage()]);

            return false;
        }

        $decoded = $response->json();

        // status: 1 = sent, negative = rejected (bad sender name, no
        // units, ...), 401 = bad credentials — none of these are comm
        // failures, just Paystack/VTpass-style "here's why it said no".
        if (! is_array($decoded) || (int) ($decoded['status'] ?? 0) !== 1) {
            Log::error('BulkSMSLive rejected the message', [
                'http_status' => $response->status(),
                'response' => $decoded ?? $response->body(),
            ]);

            return false;
        }

        if (isset($decoded['balance']) && (float) $decoded['balance'] < self::LOW_BALANCE_THRESHOLD) {
            Log::warning('BulkSMSLive account balance is low — OTP delivery will start failing once it runs out.', [
                'balance' => $decoded['balance'],
            ]);
        }

        return true;
    }

    protected function apiKey(): ?string
    {
        return config('notify.sms.api_key') ?: null;
    }

    /**
     * BulkSMSLive expects international format with no leading "+" (e.g.
     * "2348031234567"). Users register with a local Nigerian number
     * ("0803...") — this is the one place that gets translated, rather
     * than normalizing at write-time and having to touch every existing
     * caller (VTpass, for instance, wants the local format as-is).
     */
    protected function normalizeRecipient(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        // Already has a country code (234... or, for a Diaspora Mode
        // account, some other one) — passed through rather than guessed at.
        return $digits;
    }
}
