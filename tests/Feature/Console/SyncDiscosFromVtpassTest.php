<?php

use App\Models\Disco;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'vending.vtpass.env' => 'sandbox',
        'vending.vtpass.api_key' => 'test-api-key',
        'vending.vtpass.public_key' => 'test-public-key',
    ]);
});

it('updates an existing disco, creates a new one, and retires one VTpass no longer lists', function () {
    Http::fake([
        'sandbox.vtpass.com/api/services*' => Http::response([
            'content' => [
                ['serviceID' => 'ikeja-electric', 'name' => 'Ikeja Electric (renamed)'],
                ['serviceID' => 'aba-electric', 'name' => 'ABA Electric'],
            ],
        ], 200),
    ]);

    $existing = Disco::factory()->create([
        'name' => 'Ikeja Electric',
        'code' => 'IKEDC',
        'region' => 'Lagos (Ikeja)',
        'api_provider_code' => 'ikeja-electric',
        'is_active' => true,
    ]);
    $retiring = Disco::factory()->create([
        'code' => 'RETIRED',
        'api_provider_code' => 'no-longer-listed',
        'is_active' => true,
    ]);

    $this->artisan('vtpass:sync-discos')->assertSuccessful();

    expect($existing->fresh())
        ->name->toBe('Ikeja Electric (renamed)')
        // code/region are curated by us, not overwritten by the sync.
        ->code->toBe('IKEDC')
        ->region->toBe('Lagos (Ikeja)')
        ->is_active->toBeTrue();

    expect($retiring->fresh()->is_active)->toBeFalse();

    expect(Disco::where('api_provider_code', 'aba-electric')->first())
        ->not->toBeNull()
        ->and(Disco::where('api_provider_code', 'aba-electric')->first()->is_active)->toBeTrue();
});

it('fails cleanly and touches nothing when VTpass returns no services', function () {
    Http::fake([
        'sandbox.vtpass.com/api/services*' => Http::response('"Invalid Credentials."', 401),
    ]);

    $existing = Disco::factory()->create(['api_provider_code' => 'ikeja-electric', 'name' => 'Ikeja Electric']);

    $this->artisan('vtpass:sync-discos')->assertFailed();

    expect($existing->fresh()->name)->toBe('Ikeja Electric');
});
