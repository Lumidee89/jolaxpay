<?php

namespace App\Jobs;

use App\Domain\Transactions\TransactionService;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Vending calls, payment calls, and notification dispatch are never
 * inline in the request cycle (TRD §5, §8) — this job, ProcessVending,
 * and DeliverToken are the queued pipeline behind `TransactionService::initiate()`.
 *
 * ShouldBeUnique: a Paystack-paid transaction can be confirmed from two
 * independent places — the `charge.success` webhook and the mobile app's
 * status-poll fallback (PaystackChargeReconciler) — which can legitimately
 * race each other while a transaction sits queued but not yet processed.
 * Without this, both could dispatch this job for the same transaction.
 */
class ProcessTransactionPayment implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public Transaction $transaction) {}

    public function uniqueId(): string
    {
        return (string) $this->transaction->id;
    }

    public function handle(TransactionService $transactions): void
    {
        $transactions->processPayment($this->transaction);
    }
}
