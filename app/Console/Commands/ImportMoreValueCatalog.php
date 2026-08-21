<?php

namespace App\Console\Commands;

use App\Models\Biller;
use App\Models\BillerVariation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

class ImportMoreValueCatalog extends Command
{
    protected $signature = 'morevalue:import-catalog {file : JSON file containing MoreValue plan IDs and selling prices}';

    protected $description = 'Import confidential MoreValue data and cable plan IDs from a local JSON file.';

    public function handle(): int
    {
        $path = $this->argument('file');
        $path = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Catalog file is not readable: {$path}");

            return self::FAILURE;
        }

        try {
            $catalog = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Invalid catalog JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($catalog['billers'] ?? null)) {
            $this->error('Catalog must contain a "billers" object. See morevalue-catalog.example.json.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($catalog) {
            foreach ($catalog['billers'] as $billerCode => $plans) {
                $biller = Biller::where('code', $billerCode)
                    ->whereIn('service_type', ['data', 'cable_tv'])
                    ->first();

                if (! $biller) {
                    $this->warn("{$billerCode}: unknown or unsupported biller; skipped.");

                    continue;
                }

                if (! is_array($plans)) {
                    $this->warn("{$billerCode}: plans must be an array; skipped.");

                    continue;
                }

                $seen = [];
                foreach ($plans as $index => $plan) {
                    $id = trim((string) ($plan['id'] ?? ''));
                    $name = trim((string) ($plan['name'] ?? ''));
                    $amount = $plan['amount'] ?? null;

                    if ($id === '' || $name === '' || ! is_numeric($amount) || (float) $amount <= 0) {
                        $this->warn("{$billerCode}: invalid plan at index {$index}; skipped.");

                        continue;
                    }

                    $seen[] = $id;
                    BillerVariation::updateOrCreate(
                        ['biller_id' => $biller->id, 'variation_code' => $id],
                        ['name' => $name, 'amount' => $amount, 'fixed_price' => true, 'is_active' => true],
                    );
                }

                BillerVariation::where('biller_id', $biller->id)
                    ->whereNotIn('variation_code', $seen ?: ['__none__'])
                    ->update(['is_active' => false]);

                $this->info("{$biller->name}: imported ".count($seen).' active plan(s).');
            }
        });

        return self::SUCCESS;
    }
}
