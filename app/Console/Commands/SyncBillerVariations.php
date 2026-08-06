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
 * daily once VENDING_*_DRIVER=vtpass is live) — existing rows are updated
 * in place, not duplicated.
 */
class SyncBillerVariations extends Command
{
    protected $signature = 'vtpass:sync-variations {--biller= : Only sync one biller, by its `code`}';

    protected $description = 'Sync data/cable_tv/education bundle options and prices from VTpass.';

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

            foreach ($variations as $variation) {
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

            $this->info("{$biller->name}: synced ".count($variations).' variation(s).');
        }

        return self::SUCCESS;
    }
}
