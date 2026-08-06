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
 * Bounded self-retry lives inside TransactionService::processVending()
 * (it re-dispatches itself with backoff up to `vending.max_vend_attempts`),
 * so `$tries` here is just a hard ceiling against an infinite requeue bug.
 */
class ProcessVending implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public Transaction $transaction) {}

    public function handle(TransactionService $transactions): void
    {
        $transactions->processVending($this->transaction);
    }
}
