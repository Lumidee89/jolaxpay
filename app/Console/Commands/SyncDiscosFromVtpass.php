<?php

namespace App\Console\Commands;

use App\Domain\Vending\Providers\VtpassElectricityProvider;
use App\Models\Disco;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Refreshes `discos` from VTpass's live electricity billers list
 * (GET /services?identifier=electricity-bill) instead of relying solely
 * on DiscoSeeder's hardcoded catalog. Safe to run repeatedly:
 *
 * - A DisCo VTpass already lists (matched by `api_provider_code`, its
 *   VTpass `serviceID`) gets its `name` refreshed and is marked active —
 *   its curated `code`/`region` are left untouched.
 * - A DisCo VTpass lists that we don't have yet is created (inactive
 *   `region`, a generated `code` — ops can fill those in via the admin
 *   Provider Health page).
 * - A DisCo we have that VTpass no longer lists is marked inactive, never
 *   deleted (meters/transactions still reference it historically).
 *
 * DiscoSeeder remains the bootstrap fallback so `db:seed` still works
 * before any VTpass credentials exist — same "seed for parallel
 * development" pattern used throughout this app (see README's
 * "Mocked vs. real"). This command is what keeps the catalog itself
 * authoritative once VTpass is actually reachable.
 */
class SyncDiscosFromVtpass extends Command
{
    protected $signature = 'vtpass:sync-discos';

    protected $description = "Sync the DisCo catalog (names, active status) from VTpass's live electricity-bill services list.";

    public function handle(VtpassElectricityProvider $provider): int
    {
        $services = $provider->fetchElectricityServices();

        if (empty($services)) {
            $this->error('VTpass returned no electricity services — check VTPASS_* credentials and connectivity, then see storage/logs/laravel.log for the exact response.');

            return self::FAILURE;
        }

        $seenServiceIds = [];
        $created = 0;
        $updated = 0;

        foreach ($services as $service) {
            $seenServiceIds[] = $service['service_id'];
            $existing = Disco::where('api_provider_code', $service['service_id'])->first();

            if ($existing) {
                $existing->update(['name' => $service['name'], 'is_active' => true]);
                $updated++;

                continue;
            }

            Disco::create([
                'name' => $service['name'],
                'code' => $this->uniqueCode($service['service_id']),
                'region' => null,
                'service_type' => 'electricity',
                'api_provider_code' => $service['service_id'],
                'health_status' => 'unknown',
                'is_active' => true,
            ]);
            $created++;
        }

        $retired = Disco::where('service_type', 'electricity')
            ->where('is_active', true)
            ->whereNotIn('api_provider_code', $seenServiceIds)
            ->update(['is_active' => false]);

        $this->info("Synced {$created} new, {$updated} updated, {$retired} retired DisCo(s) from VTpass.");

        return self::SUCCESS;
    }

    protected function uniqueCode(string $serviceId): string
    {
        $base = Str::upper(Str::slug($serviceId, ''));
        $code = $base;
        $suffix = 1;

        while (Disco::where('code', $code)->exists()) {
            $code = $base.(++$suffix);
        }

        return $code;
    }
}
