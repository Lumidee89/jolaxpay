<?php

namespace App\Listeners;

use App\Domain\Referrals\CommissionService;
use App\Domain\Transactions\Events\TransactionStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued (not inline) because TransactionStateMachine::transition() wraps
 * its event() call in a broadcast-failure try/catch — a synchronous
 * listener throwing here would get silently logged as "broadcast failed"
 * rather than surfacing as what it actually is. Auto-discovered by
 * Laravel from this class's handle() type-hint; no manual registration
 * needed (see EventServiceProvider's absence — Laravel 13 event discovery).
 */
class RewardReferralOnFirstTransaction implements ShouldQueue
{
    public function __construct(private readonly CommissionService $commissions) {}

    public function handle(TransactionStatusUpdated $event): void
    {
        $this->commissions->accrue($event->transaction);
    }
}
