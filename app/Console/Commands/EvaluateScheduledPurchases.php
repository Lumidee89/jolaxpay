<?php

namespace App\Console\Commands;

use App\Domain\Scheduling\ScheduledPurchaseEvaluator;
use Illuminate\Console\Command;

class EvaluateScheduledPurchases extends Command
{
    protected $signature = 'purchases:evaluate-scheduled';

    protected $description = 'Initiate every scheduled/recurring purchase that is due (PRD §7.4).';

    public function handle(ScheduledPurchaseEvaluator $evaluator): int
    {
        $count = $evaluator->evaluateDue();

        $this->info("Evaluated scheduled purchases: {$count} due.");

        return self::SUCCESS;
    }
}
