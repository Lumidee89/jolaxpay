<?php

use App\Models\Disco;
use Illuminate\Support\Facades\Http;

it('activates every disco returned by Schedwave and retires unavailable ones', function () {
    config([
        'vending.schedwave.api_key' => 'sched_test_key',
        'vending.schedwave.base_url' => 'https://schedwave.test/api/v1',
    ]);
    Disco::factory()->create(['code' => 'IKEDC', 'is_active' => false]);
    Disco::factory()->create(['code' => 'OLD', 'is_active' => true]);
    Http::fake(['*/vtu/electricity-providers' => Http::response([
        'error' => false,
        'providers' => [
            ['id' => 1, 'name' => 'Ikeja Electric', 'code' => 'IE'],
            ['id' => 12, 'name' => 'Aba Electric', 'code' => 'ABAE'],
        ],
    ])]);

    $this->artisan('schedwave:sync-discos')->assertSuccessful();

    expect(Disco::where('code', 'IKEDC')->first())->is_active->toBeTrue()
        ->and(Disco::where('code', 'ABAELECTRIC')->first())->is_active->toBeTrue()
        ->and(Disco::where('code', 'OLD')->first())->is_active->toBeFalse();
});
