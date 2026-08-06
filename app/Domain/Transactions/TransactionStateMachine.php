<?php

namespace App\Domain\Transactions;

use App\Domain\Transactions\Events\TransactionStatusUpdated;
use App\Domain\Transactions\Exceptions\InvalidTransitionException;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single place allowed to move a transaction between Payment Flow
 * stages (TRD §6). Every transition is validated against
 * TransactionStatus::allowedNextStates(), recorded to
 * `transaction_status_history` for the Admin audit trail (User Journey
 * §7), and broadcast in real time.
 */
class TransactionStateMachine
{
    public function transition(
        Transaction $transaction,
        TransactionStatus $to,
        ?string $note = null,
        ?User $causedBy = null,
    ): Transaction {
        $from = $transaction->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidTransitionException(
                "Transaction #{$transaction->id} cannot move from [{$from->value}] to [{$to->value}]."
            );
        }

        // 'status' is deliberately absent from Transaction's #[Fillable] list
        // (this class is the only thing allowed to change it) — update()
        // would silently no-op on a non-fillable attribute, so forceFill().
        $transaction->forceFill(['status' => $to])->save();

        TransactionStatusHistory::create([
            'transaction_id' => $transaction->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'caused_by_user_id' => $causedBy?->id,
        ]);

        // Broadcasting is a UX enhancement, not a correctness requirement —
        // the polling fallback (`GET /v1/transactions/{id}/status`, TRD §3)
        // covers a down/unreachable broadcast server. A failure here must
        // never take down the purchase flow itself (TRD §8: vending/
        // notification failures "must degrade gracefully, never take down
        // checkout" — the same principle applies to realtime status pushes).
        try {
            event(new TransactionStatusUpdated($transaction->fresh()));
        } catch (Throwable $e) {
            Log::warning('Transaction status broadcast failed; client will fall back to polling.', [
                'transaction_id' => $transaction->id,
                'to_status' => $to->value,
                'error' => $e->getMessage(),
            ]);
        }

        return $transaction;
    }
}
