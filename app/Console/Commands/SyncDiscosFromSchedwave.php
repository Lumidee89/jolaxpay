<?php

namespace App\Console\Commands;

use App\Domain\Vending\Providers\SchedwaveElectricityProvider;
use App\Models\Disco;
use Illuminate\Console\Command;

class SyncDiscosFromSchedwave extends Command
{
    protected $signature = 'schedwave:sync-discos';

    protected $description = 'Sync active electricity DisCos from the configured Schedwave catalog.';

    /** Schedwave catalog code => JolaxPay stable code. */
    private const CODE_MAP = [
        'IE' => 'IKEDC',
        'EKEDC' => 'EKEDC',
        'KEDCO' => 'KEDCO',
        'PHEDC' => 'PHED',
        'JED' => 'JED',
        'IBEDC' => 'IBEDC',
        'KEDC' => 'KAEDCO',
        'AEDC' => 'AEDC',
        'ENUGU' => 'EEDC',
        'BENIN' => 'BEDC',
        'YOLA' => 'YOLAELECTRIC',
        'ABAE' => 'ABAELECTRIC',
    ];

    public function handle(SchedwaveElectricityProvider $provider): int
    {
        $providers = $provider->fetchProviders();
        if ($providers === []) {
            $this->error('Schedwave returned no electricity providers. Existing rows were left untouched.');

            return self::FAILURE;
        }

        $seenCodes = [];
        $created = 0;
        $updated = 0;

        foreach ($providers as $remote) {
            $code = self::CODE_MAP[strtoupper($remote['code'])] ?? strtoupper($remote['code']);
            $seenCodes[] = $code;
            $disco = Disco::where('code', $code)->first();

            if ($disco) {
                $disco->update(['name' => $remote['name'], 'is_active' => true]);
                $updated++;
            } else {
                Disco::create([
                    'name' => $remote['name'],
                    'code' => $code,
                    'region' => null,
                    'service_type' => 'electricity',
                    // Provider IDs are resolved live by the Schedwave driver;
                    // do not overwrite this legacy/provider-neutral column.
                    'api_provider_code' => null,
                    'health_status' => 'unknown',
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        $retired = Disco::where('service_type', 'electricity')
            ->where('is_active', true)
            ->whereNotIn('code', $seenCodes)
            ->update(['is_active' => false]);

        $this->info("Schedwave DisCos synced: {$created} created, {$updated} updated, {$retired} retired.");

        return self::SUCCESS;
    }
}
