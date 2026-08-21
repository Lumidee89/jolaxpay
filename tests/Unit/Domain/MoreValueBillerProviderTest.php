<?php

use App\Domain\Vending\Providers\MoreValueBillerProvider;
use App\Models\Biller;
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
    $this->provider = new MoreValueBillerProvider;
    $this->user = User::factory()->create(['phone_number' => '08010000000']);
});

it('sends MoreValue airtime payload and authentication header', function () {
    Http::fake(['morevaluedigital.com.ng/api/airtime/' => Http::response([
        'status' => 'success', 'msg' => 'Airtime topup successful',
    ])]);
    $biller = Biller::factory()->create(['service_type' => 'airtime', 'api_provider_code' => '1']);
    $transaction = Transaction::factory()->for($this->user)->for($biller)->create([
        'service_type' => 'airtime', 'amount' => '100.00', 'biller_identifier' => '08123456789',
    ]);

    expect($this->provider->vend($transaction)->successful)->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === 'https://morevaluedigital.com.ng/api/airtime/'
        && $request->hasHeader('Token', 'Token test-token')
        && $request['network'] === '1'
        && $request['phone'] === '08123456789'
        && $request['ref'] === $transaction->reference);
});

it('sends MoreValue data plan ID from variation code', function () {
    Http::fake(['morevaluedigital.com.ng/api/data/' => Http::response(['Status' => 'successful'])]);
    $biller = Biller::factory()->data()->create(['api_provider_code' => '4']);
    $transaction = Transaction::factory()->for($this->user)->for($biller)->create([
        'service_type' => 'data', 'amount' => '500.00', 'biller_identifier' => '08123456789',
        'variation_code' => '420',
    ]);

    expect($this->provider->vend($transaction)->successful)->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === 'https://morevaluedigital.com.ng/api/data/'
        && $request['network'] === '4' && $request['plan'] === '420');
});

it('verifies cable accounts and purchases the selected bouquet', function () {
    Http::fake([
        'morevaluedigital.com.ng/api/cabletv/verify/' => Http::response(['status' => 'success', 'customer_name' => 'JOHN DOE']),
        'morevaluedigital.com.ng/api/cabletv/' => Http::response(['status' => 'success', 'msg' => 'Subscription Successful']),
    ]);
    $biller = Biller::factory()->cableTv()->create(['api_provider_code' => '2', 'supports_verify' => true]);

    expect($this->provider->verify($biller, '1234567890')->customerName)->toBe('JOHN DOE');

    $transaction = Transaction::factory()->for($this->user)->for($biller)->create([
        'service_type' => 'cable_tv', 'amount' => '5000.00', 'biller_identifier' => '1234567890',
        'variation_code' => '25',
    ]);
    expect($this->provider->vend($transaction)->successful)->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === 'https://morevaluedigital.com.ng/api/cabletv/'
        && $request['provider'] === '2' && $request['iucnumber'] === '1234567890' && $request['plan'] === '25');
});
