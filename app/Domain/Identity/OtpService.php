<?php

namespace App\Domain\Identity;

use App\Domain\Notifications\NotificationDispatcher;
use App\Enums\DeliveryChannel;
use App\Enums\OtpPurpose;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * OTP verification (TRD §7): new-device login, password reset, and
 * high-value transactions. Registration OTPs are keyed by `identifier`
 * (phone/email) rather than `user_id` since the account doesn't exist yet.
 */
class OtpService
{
    private const CODE_LENGTH = 6;

    private const TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly NotificationDispatcher $notifier) {}

    public function issue(string $identifier, OtpPurpose $purpose, DeliveryChannel $channel = DeliveryChannel::Sms, ?User $user = null): Otp
    {
        $code = (string) random_int(10 ** (self::CODE_LENGTH - 1), (10 ** self::CODE_LENGTH) - 1);

        $otp = Otp::create([
            'user_id' => $user?->id,
            'identifier' => $identifier,
            'channel' => $channel,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        if ($user) {
            $this->notifier->send($user, 'otp_'.$purpose->value, $channel, ['code' => $code]);
        } else {
            // Pre-registration: no User row to hang the log on yet — the
            // dispatcher requires one, so log directly via the same
            // 'log' driver convention channels fall back to.
            logger()->info("[notify:otp] {$purpose->value} code for {$identifier}: {$code}");
        }

        return $otp;
    }

    public function verify(string $identifier, OtpPurpose $purpose, string $code): bool
    {
        $otp = Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->isExpired() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $otp->increment('attempts');

        if (! $otp->matches($code)) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
