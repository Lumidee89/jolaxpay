<?php

use App\Models\Disco;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

it('returns a clear validation error when Schedwave cannot identify the meter', function () {
    config([
        'vending.electricity.driver' => 'schedwave',
        'vending.schedwave.api_key' => 'sched_test_key',
        'vending.schedwave.base_url' => 'https://schedwave.test/api/v1',
    ]);
    Cache::flush();
    Http::fake([
        '*/vtu/electricity-providers' => Http::response(['error' => false, 'providers' => [['id' => 1, 'name' => 'Ikeja Electric', 'code' => 'IE']]]),
        '*/vtu/electricity-validate*' => Http::response(['error' => true, 'error_code' => 'INVALID_METER', 'message' => 'Invalid meter number'], 422),
    ]);
    Sanctum::actingAs(User::factory()->create());
    $disco = Disco::factory()->create(['code' => 'IKEDC']);

    $this->postJson('/api/v1/meters/verify', [
        'disco_id' => $disco->id,
        'meter_number' => '00000000000',
        'meter_type' => 'prepaid',
    ])->assertUnprocessable()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('message', 'The meter number is incorrect for the selected DisCo or meter type. Check the details and try again.');
});
