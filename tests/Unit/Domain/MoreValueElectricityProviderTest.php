<?php

use App\Domain\Vending\Providers\MoreValueElectricityProvider;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'vending.morevalue.base_url' => 'https://morevaluedigital.com.ng/api',
        'vending.morevalue.api_token' => 'test-token',
        'vending.morevalue.timeout' => 10,
    ]);
    $this->provider = new MoreValueElectricityProvider;
    $this->user = User::factory()->create(['phone_number' => '08010000000']);
    $this->disco = Disco::factory()->create(['api_provider_code' => '1']);
    $this->meter = Meter::factory()->for($this->user)->for($this->disco)->create([
        'meter_number' => '01234567890', 'meter_type' => 'prepaid',
    ]);
});

it('verifies a MoreValue electricity meter', function () {
    Http::fake(['morevaluedigital.com.ng/api/electricity/verify/' => Http::response([
        'status' => 'success', 'customer_name' => 'JANE DOE', 'address' => 'Lagos',
    ])]);

    $result = $this->provider->verifyMeter($this->meter);
    expect($result->valid)->toBeTrue()->and($result->customerName)->toBe('JANE DOE');
    Http::assertSent(fn ($request) => $request['provider'] === '1'
        && $request['meternumber'] === '01234567890' && $request['metertype'] === 'prepaid');
});

it('vends electricity and returns token and units', function () {
    Http::fake(['morevaluedigital.com.ng/api/electricity/' => Http::response([
        'status' => 'success', 'token' => '1234-5678', 'units' => '31.4 kWh',
    ])]);
    $transaction = Transaction::factory()->for($this->user)->for($this->meter)->create([
        'service_type' => 'electricity', 'amount' => '2000.00', 'recipient_phone' => '08123456789',
    ]);

    $result = $this->provider->vend($transaction);
    expect($result->successful)->toBeTrue()->and($result->token)->toBe('1234-5678')
        ->and($result->raw['units'])->toBe('31.4 kWh');
    Http::assertSent(fn ($request) => $request->url() === 'https://morevaluedigital.com.ng/api/electricity/'
        && $request['amount'] === 2000.0 && $request['ref'] === $transaction->reference);
});
