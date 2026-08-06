<?php

namespace App\Jobs;

use App\Domain\Transactions\TransactionService;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Vending calls, payment calls, and notification dispatch are never
 * inline in the request cycle (TRD §5, §8) — this job, ProcessVending,
 * and DeliverToken are the queued pipeline behind `TransactionService::initiate()`.
 */
class ProcessTransactionPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Transaction $transaction) {}

    public function handle(TransactionService $transactions): void
    {
        $transactions->processPayment($this->transaction);
    }
}
