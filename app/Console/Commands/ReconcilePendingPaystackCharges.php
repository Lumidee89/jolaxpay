<?php

namespace App\Console\Commands;

use App\Domain\Payments\PaystackChargeReconciler;
use App\Models\WalletFundingIntent;
use Illuminate\Console\Command;

class ReconcilePendingPaystackCharges extends Command
{
    protected $signature = 'payments:reconcile-paystack {--limit=100 : Maximum pending charges to verify}';

    protected $description = 'Verify pending Paystack wallet funding and apply successful credits';

    public function handle(PaystackChargeReconciler $reconciler): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $intents = WalletFundingIntent::query()
            ->where('status', 'pending')
            ->where('meta->provider', 'paystack')
            ->oldest()
            ->limit($limit)
            ->get();

        $credited = 0;
        foreach ($intents as $intent) {
            $reconciler->reconcile($intent->reference);
            if ($intent->fresh()->status === 'success') {
                $credited++;
            }
        }

        $this->info("Verified {$intents->count()} pending Paystack charge(s); {$credited} successful charge(s) reconciled.");

        return self::SUCCESS;
    }
}
