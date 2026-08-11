<?php

namespace App\Domain\Payments;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Transactions\TransactionService;
use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\LedgerReason;
use App\Enums\TransactionStatus;
use App\Jobs\ProcessTransactionPayment;
use App\Models\Transaction;
use App\Models\WalletFundingIntent;
use Illuminate\Support\Facades\Log;

/**
 * Finalizes a Paystack charge reference (wallet funding or a transaction
 * card payment) — the single place that both PaystackWebhookController
 * (`charge.success`/`charge.failed`) and the mobile app's status-polling
 * endpoints go through, so a charge only ever gets confirmed one way.
 *
 * Webhooks are the primary path but aren't guaranteed to arrive: a local
 * dev backend has no public URL for Paystack to call, and even in
 * production a webhook can be delayed or dropped. `reconcile()` is the
 * fallback — it asks Paystack directly (`/transaction/verify`) instead of
 * waiting, which is Paystack's own documented recommendation for exactly
 * this gap. Every method here only acts on a still-pending
 * intent/transaction, so calling both the webhook and the fallback for the
 * same reference (a real possibility) never double-credits.
 */
class PaystackChargeReconciler
{
    public function __construct(
        private readonly PaystackGateway $paystack,
        private readonly TransactionService $transactions,
        private readonly LedgerService $ledger,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * Asks Paystack for the current state of a charge reference and
     * applies it. Safe to call repeatedly (e.g. once per poll) — it's a
     * no-op once the intent/transaction is no longer pending.
     */
    public function reconcile(string $reference): string
    {
        $data = $this->paystack->verifyTransaction($reference);
        $status = $data['status'] ?? null;

        if ($status === 'success') {
            $this->markChargeSuccessful($reference);
        } elseif (in_array($status, ['failed', 'abandoned', 'reversed'], true)) {
            $this->markChargeFailed($reference, $data['gateway_response'] ?? null);
        }

        return $status ?? 'unknown';
    }

    public function markChargeSuccessful(string $reference): void
    {
        if ($intent = WalletFundingIntent::where('reference', $reference)->where('status', 'pending')->first()) {
            $wallet = $intent->wallet;
            $entry = $this->ledger->credit($wallet, (string) $intent->amount, LedgerReason::WalletFunding, null, [
                'paystack_reference' => $reference,
            ]);
            $intent->update(['status' => 'success']);

            $this->notifier->send($intent->user, 'wallet_funded', DeliveryChannel::InApp, [
                'amount' => (string) $intent->amount,
                'currency' => $wallet->currency,
                'balance' => (string) $wallet->fresh()->balance,
            ]);

            Log::info('Paystack wallet funding confirmed', ['intent_id' => $intent->id, 'ledger_entry_id' => $entry->id]);

            return;
        }

        $transaction = Transaction::where('meta->paystack_reference', $reference)->first();

        if ($transaction && $transaction->status === TransactionStatus::PaymentInitiated) {
            ProcessTransactionPayment::dispatch($transaction);

            return;
        }

        Log::info('Paystack charge confirmed for an unrecognised or already-processed reference', ['reference' => $reference]);
    }

    public function markChargeFailed(string $reference, ?string $reason = null): void
    {
        if ($intent = WalletFundingIntent::where('reference', $reference)->where('status', 'pending')->first()) {
            $intent->update(['status' => 'failed']);

            return;
        }

        $transaction = Transaction::where('meta->paystack_reference', $reference)->first();

        if ($transaction && ! $transaction->status->isTerminal()) {
            $this->transactions->fail($transaction, $reason ?: 'Card payment declined by Paystack.');
        }
    }
}
