<?php

namespace App\Enums;

/**
 * Groups NotificationDispatcher's raw `type` strings into the categories a
 * user actually toggles in Settings (PRD §14). OTP codes deliberately have
 * no category — see fromType() — since they're mandatory 2FA, not a
 * preference a user can turn off.
 */
enum NotificationCategory: string
{
    case Transactions = 'transactions';
    case Wallet = 'wallet';
    case Referrals = 'referrals';
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::Transactions => 'Purchases & bill payments',
            self::Wallet => 'Wallet activity',
            self::Referrals => 'Referral rewards',
            self::Security => 'Account & security',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Transactions => 'Payment confirmations and token/bundle delivery.',
            self::Wallet => 'Funding, transfers, and withdrawal updates.',
            self::Referrals => 'When someone you referred earns you a reward.',
            self::Security => 'New sign-ins and other account activity.',
        };
    }

    /** Maps a NotificationDispatcher `type` string to its category, or null if it's never gated (e.g. otp_*). */
    public static function fromType(string $type): ?self
    {
        if (str_starts_with($type, 'otp_')) {
            return null;
        }

        return match ($type) {
            'payment_confirmation', 'token_delivery' => self::Transactions,
            'wallet_funded', 'wallet_transfer_sent', 'wallet_transfer_received',
            'withdrawal_completed', 'withdrawal_failed' => self::Wallet,
            'referral_reward', 'agent_referral_commission', 'agent_reward' => self::Referrals,
            'login_success' => self::Security,
            default => null,
        };
    }
}
