<?php

namespace App\Console\Commands;

use App\Domain\Vending\VendingManager;
use App\Enums\ServiceType;
use App\Models\Biller;
use App\Models\BillerVariation;
use Illuminate\Console\Command;

/**
 * Refreshes `biller_variations` from VTpass's GET /service-variations for
 * every biller that needs one (data bundles, cable TV bouquets, education
 * pin types) — VTpass's bundle catalog and pricing change from time to
 * time, and the mobile app reads the cached copy rather than hitting
 * VTpass on every purchase-form load. Safe to run repeatedly (schedule it
 * daily once VENDING_*_DRIVER=vtpass is live): existing rows are updated
 * in place, not duplicated, and — like SyncDiscosFromVtpass does for
 * discos — any row VTpass stops returning for a biller is marked
 * `is_active = false` rather than left behind. That matters here more
 * than it might seem: a stale row (e.g. a leftover MockBillerProvider
 * `mock-small`/`mock-large` entry from testing against the mock driver
 * before real VTpass credentials existed) would otherwise sit in the
 * catalog indefinitely and could be selected for a real purchase.
 */
class SyncBillerVariations extends Command
{
    protected $signature = 'vtpass:sync-variations {--biller= : Only sync one biller, by its `code`}';

    protected $description = 'Sync data/cable_tv/education options and prices from each service type\'s active vending driver.';

    public function handle(VendingManager $vending): int
    {
        $billers = Biller::where('requires_variation', true)
            ->when($this->option('biller'), fn ($q, $code) => $q->where('code', $code))
            ->get();

        if ($billers->isEmpty()) {
            $this->warn('No matching billers to sync.');

            return self::SUCCESS;
        }

        foreach ($billers as $biller) {
            $driver = $vending->billerDriverFor(ServiceType::from($biller->service_type));
            $variations = $driver->fetchVariations($biller);

            if (empty($variations)) {
                $this->warn("{$biller->name}: no variations returned — left existing rows untouched.");

                continue;
            }

            $seenCodes = [];

            foreach ($variations as $variation) {
                $seenCodes[] = $variation['variation_code'];

                BillerVariation::updateOrCreate(
                    ['biller_id' => $biller->id, 'variation_code' => $variation['variation_code']],
                    [
                        'name' => $variation['name'],
                        'amount' => $variation['amount'],
                        'fixed_price' => $variation['fixed_price'],
                        'is_active' => true,
                    ]
                );
            }

            $retired = BillerVariation::where('biller_id', $biller->id)
                ->where('is_active', true)
                ->whereNotIn('variation_code', $seenCodes)
                ->update(['is_active' => false]);

            $this->info("{$biller->name}: synced ".count($variations)." variation(s), retired {$retired}.");
        }

        return self::SUCCESS;
    }
}
