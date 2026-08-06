<?php

namespace App\Domain\Scheduling;

use App\Domain\Transactions\TransactionService;
use App\Models\ScheduledPurchase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Job engine evaluating due scheduled/recurring purchases (PRD §7.4,
 * TRD §5), triggering Payments + Vending through the normal
 * TransactionService pipeline. Invoked by the `purchases:evaluate-scheduled`
 * console command, itself run on the Laravel scheduler (routes/console.php).
 */
class ScheduledPurchaseEvaluator
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function evaluateDue(): int
    {
        $due = ScheduledPurchase::query()
            ->where('active', true)
            ->where('next_run_at', '<=', now())
            ->with('meter')
            ->get();

        foreach ($due as $scheduled) {
            $this->run($scheduled);
        }

        return $due->count();
    }

    protected function run(ScheduledPurchase $scheduled): void
    {
        try {
            $this->transactions->initiate($scheduled->user, [
                'meter_id' => $scheduled->meter_id,
                'amount' => (string) $scheduled->amount,
                'currency' => 'NGN',
                'payment_method' => $scheduled->payment_method_id ?? 'wallet',
                'delivery_destination' => 'me',
                'idempotency_key' => 'scheduled-'.$scheduled->id.'-'.now()->format('Y-m-d'),
                'meta' => ['scheduled_purchase_id' => $scheduled->id],
            ]);
        } catch (\Throwable $e) {
            Log::error('Scheduled purchase failed to initiate', [
                'scheduled_purchase_id' => $scheduled->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $scheduled->update([
            'last_run_at' => now(),
            'next_run_at' => $this->nextRunAt($scheduled),
        ]);
    }

    protected function nextRunAt(ScheduledPurchase $scheduled): Carbon
    {
        return match ($scheduled->frequency) {
            'weekly' => now()->addWeek(),
            'biweekly' => now()->addWeeks(2),
            'monthly' => now()->addMonthNoOverflow(),
            'custom' => now()->addDays($scheduled->custom_interval_days ?? 30),
            default => now()->addMonthNoOverflow(),
        };
    }
}
