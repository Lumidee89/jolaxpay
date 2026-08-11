<?php

namespace App\Domain\Fraud;

use App\Enums\TransactionStatus;
use App\Models\FraudFlag;
use App\Models\Transaction;

/**
 * PRD §15: "basic, rules-based fraud checks on transaction velocity and
 * unusual amounts." Called from TransactionService::initiate() right
 * after a transaction is created — see that call site for why a failure
 * here is swallowed rather than blocking the purchase (TRD §8's
 * degrade-gracefully principle, same reasoning as broadcast/notification
 * failures elsewhere in the Payment Flow).
 */
class FraudCheckService
{
    public function evaluate(Transaction $transaction): void
    {
        $this->checkVelocity($transaction);
        $this->checkUnusualAmount($transaction);
    }

    protected function checkVelocity(Transaction $transaction): void
    {
        $windowMinutes = (int) config('fraud.velocity.window_minutes');
        $maxCount = (int) config('fraud.velocity.max_count');

        $recentCount = Transaction::where('user_id', $transaction->user_id)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($recentCount > $maxCount) {
            FraudFlag::create([
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
                'rule' => 'velocity',
                'details' => "{$recentCount} transactions initiated within the last {$windowMinutes} minute(s) (threshold: {$maxCount}).",
                'meta' => ['count' => $recentCount, 'window_minutes' => $windowMinutes, 'max_count' => $maxCount],
            ]);
        }
    }

    protected function checkUnusualAmount(Transaction $transaction): void
    {
        $sampleSize = (int) config('fraud.unusual_amount.sample_size');
        $multiplier = (float) config('fraud.unusual_amount.multiplier');
        $amount = (float) $transaction->amount;

        $priorSuccessful = Transaction::where('user_id', $transaction->user_id)
            ->where('id', '!=', $transaction->id)
            ->whereIn('status', [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed]);

        $count = (clone $priorSuccessful)->count();

        if ($count >= $sampleSize) {
            $average = (float) (clone $priorSuccessful)->avg('amount');
            $threshold = $average * $multiplier;

            if ($average > 0 && $amount > $threshold) {
                FraudFlag::create([
                    'user_id' => $transaction->user_id,
                    'transaction_id' => $transaction->id,
                    'rule' => 'unusual_amount',
                    'details' => sprintf(
                        '₦%s is %.1fx this user\'s average purchase of ₦%s over their last %d purchases (threshold: %sx).',
                        number_format($amount, 2),
                        $average > 0 ? $amount / $average : 0,
                        number_format($average, 2),
                        $count,
                        rtrim(rtrim(number_format($multiplier, 1), '0'), '.'),
                    ),
                    'meta' => ['amount' => $amount, 'average' => $average, 'multiplier' => $multiplier, 'sample_size' => $count],
                ]);
            }

            return;
        }

        // Not enough history to establish "usual" for this user yet — fall
        // back to a flat ceiling so a brand-new account can't be used to
        // move an unusually large amount before any baseline exists.
        $ceiling = (float) config('fraud.unusual_amount.new_user_ceiling');
        if ($amount > $ceiling) {
            FraudFlag::create([
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
                'rule' => 'unusual_amount',
                'details' => sprintf(
                    '₦%s from a user with only %d prior successful purchase(s), above the new-account ceiling of ₦%s.',
                    number_format($amount, 2),
                    $count,
                    number_format($ceiling, 2),
                ),
                'meta' => ['amount' => $amount, 'ceiling' => $ceiling, 'sample_size' => $count],
            ]);
        }
    }
}
