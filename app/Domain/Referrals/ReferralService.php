<?php

namespace App\Domain\Referrals;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\LedgerReason;
use App\Enums\TransactionStatus;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Power Circle Rewards (PRD §16): links a referral code to a new signup,
 * then credits the referrer once — and only once — that referred user
 * completes their first real transaction.
 */
class ReferralService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * Called from AuthController::register(). Silently ignores an
     * unknown/already-claimed/self-referral code rather than failing
     * registration over it — a typo'd referral code should never block
     * someone from creating an account.
     */
    public function redeem(User $newUser, ?string $code): void
    {
        if (! $code) {
            return;
        }

        $referral = Referral::where('code', $code)->whereNull('referred_user_id')->first();

        if (! $referral || $referral->referrer_id === $newUser->id) {
            Log::info('Referral code not redeemed (unknown, already used, or self-referral).', [
                'code' => $code,
                'new_user_id' => $newUser->id,
            ]);

            return;
        }

        $referral->update([
            'referred_user_id' => $newUser->id,
            'status' => 'qualified',
        ]);
    }

    /**
     * Called from RewardReferralOnFirstTransaction on every Delivered/
     * OutcomeConfirmed transition. No-ops unless: the buyer was referred,
     * that referral hasn't been rewarded yet, and this is genuinely their
     * first successful transaction (guards against a retried/duplicate
     * event crediting twice).
     */
    public function rewardForFirstTransaction(Transaction $transaction): void
    {
        if (! in_array($transaction->status, [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed], true)) {
            return;
        }

        $referral = Referral::where('referred_user_id', $transaction->user_id)
            ->where('status', 'qualified')
            ->first();

        if (! $referral) {
            return;
        }

        $hasEarlierSuccess = Transaction::where('user_id', $transaction->user_id)
            ->whereIn('status', [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed])
            ->where('id', '!=', $transaction->id)
            ->exists();

        if ($hasEarlierSuccess) {
            return;
        }

        $amount = (string) config('referrals.reward_amount', 500);
        $wallet = $this->ledger->walletFor($referral->referrer, config('referrals.reward_currency', 'NGN'));

        $this->ledger->credit($wallet, $amount, LedgerReason::ReferralReward, $transaction, [
            'referred_user_id' => $transaction->user_id,
        ]);

        $referral->update([
            'status' => 'rewarded',
            'reward_type' => 'wallet_credit',
            'reward_value' => $amount,
        ]);

        $this->notifier->send($referral->referrer, 'referral_reward', DeliveryChannel::InApp, [
            'amount' => $amount,
            'currency' => config('referrals.reward_currency', 'NGN'),
            'referred_name' => $transaction->user->full_name,
        ]);
    }
}
