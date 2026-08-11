<?php

use App\Models\Biller;
use App\Models\BillerVariation;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'vending.data.driver' => 'vtpass',
        'vending.vtpass.env' => 'sandbox',
        'vending.vtpass.api_key' => 'test-api-key',
        'vending.vtpass.public_key' => 'test-public-key',
    ]);
});

it('retires a variation VTpass stops returning instead of leaving it active forever', function () {
    $biller = Biller::factory()->data()->create(['code' => 'MTN_DATA', 'api_provider_code' => 'mtn-data']);

    // A leftover row from testing against the mock driver before real VTpass
    // credentials existed — exactly what should get cleaned up here.
    $stale = BillerVariation::factory()->for($biller)->create([
        'variation_code' => 'mock-small',
        'is_active' => true,
    ]);

    Http::fake([
        'sandbox.vtpass.com/api/service-variations*' => Http::response([
            'content' => [
                'variations' => [
                    ['variation_code' => 'mtn-10mb-100', 'name' => 'N100 100MB', 'variation_amount' => '100.00', 'fixedPrice' => 'Yes'],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('vtpass:sync-variations', ['--biller' => 'MTN_DATA'])->assertSuccessful();

    expect($stale->fresh()->is_active)->toBeFalse()
        ->and(BillerVariation::where('biller_id', $biller->id)->where('variation_code', 'mtn-10mb-100')->first())
        ->not->toBeNull();

    // Biller::variations() only surfaces active rows — the mobile app never sees the stale one.
    expect($biller->variations()->pluck('variation_code')->all())->toBe(['mtn-10mb-100']);
});

it('leaves existing rows untouched when VTpass returns nothing for a biller', function () {
    $biller = Biller::factory()->data()->create(['code' => 'MTN_DATA', 'api_provider_code' => 'mtn-data']);
    $existing = BillerVariation::factory()->for($biller)->create(['variation_code' => 'mtn-10mb-100', 'is_active' => true]);

    Http::fake([
        'sandbox.vtpass.com/api/service-variations*' => Http::response(['content' => ['variations' => []]], 200),
    ]);

    $this->artisan('vtpass:sync-variations', ['--biller' => 'MTN_DATA'])->assertSuccessful();

    expect($existing->fresh()->is_active)->toBeTrue();
});
