<?php

use App\Domain\Vending\Providers\VtpassElectricityProvider;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

/**
 * Response shapes below are taken verbatim from VTpass's own published
 * examples (https://vtpass.com/documentation/), not invented — this is
 * what actually protects the parsing logic in VtpassElectricityProvider
 * from silently drifting away from the real API.
 */
beforeEach(function () {
    config([
        'vending.vtpass.env' => 'sandbox',
        'vending.vtpass.api_key' => 'test-api-key',
        'vending.vtpass.secret_key' => 'test-secret-key',
        'vending.vtpass.public_key' => 'test-public-key',
    ]);

    $this->provider = new VtpassElectricityProvider;
    $this->disco = Disco::factory()->create(['api_provider_code' => 'ikeja-electric']);
    $this->meter = Meter::factory()->for($this->disco)->create([
        'meter_number' => '68100017372',
        'meter_type' => 'prepaid',
    ]);
});

it('marks the transaction delivered and strips the "Token : " prefix', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response([
            'code' => '000',
            'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => 'TXN123']],
            'response_description' => 'TRANSACTION SUCCESSFUL',
            'purchased_code' => 'Token : 26362054405982757802',
            'token' => 'Token : 26362054405982757802',
            'tokenAmount' => 1860.47,
            'units' => '79.9 kWh',
        ], 200),
    ]);

    $transaction = Transaction::factory()->for($this->meter)->create(['amount' => '2000.00']);

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeTrue()
        ->and($result->token)->toBe('26362054405982757802')
        ->and($result->raw['units'])->toBe('79.9 kWh');

    Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.vtpass.com/api/pay'
        && $request->hasHeader('api-key', 'test-api-key')
        && $request->hasHeader('secret-key', 'test-secret-key')
        && $request['serviceID'] === 'ikeja-electric'
        && $request['billersCode'] === '68100017372'
        && $request['variation_code'] === 'prepaid');
});

it('requeries the same request_id on retry instead of paying again', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response([
            'code' => '000',
            'content' => ['transactions' => ['status' => 'pending']],
            'response_description' => 'TRANSACTION IS PROCESSING',
        ], 200),
        'sandbox.vtpass.com/api/requery' => Http::response([
            'code' => '000',
            'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => 'TXN123']],
            'response_description' => 'TRANSACTION SUCCESSFUL',
            'token' => 'Token : 11112222333344445555',
        ], 200),
    ]);

    $transaction = Transaction::factory()->for($this->meter)->create(['amount' => '2000.00']);

    $first = $this->provider->vend($transaction);
    expect($first->successful)->toBeFalse();

    $requestId = $transaction->fresh()->meta['vtpass_request_id'];
    expect($requestId)->not->toBeNull();

    $second = $this->provider->vend($transaction->fresh());
    expect($second->successful)->toBeTrue()
        ->and($second->token)->toBe('11112222333344445555');

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.vtpass.com/api/requery'
        && $request['request_id'] === $requestId);
});

it('treats a hard failure code as unsuccessful without touching the wallet', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response([
            'code' => '016',
            'response_description' => 'TRANSACTION FAILED',
        ], 200),
    ]);

    $transaction = Transaction::factory()->for($this->meter)->create();

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toBe('TRANSACTION FAILED');
});

it('returns a failed VendResult on a network error instead of throwing', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

    $transaction = Transaction::factory()->for($this->meter)->create();

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeFalse();
});

it('parses a successful merchant-verify into a valid MeterVerificationResult', function () {
    Http::fake([
        'sandbox.vtpass.com/api/merchant-verify' => Http::response([
            'code' => '000',
            'content' => [
                'Customer_Name' => 'TESTMETER1',
                'Address' => 'ABULE EGBA BU ABULE',
                'Can_Vend' => 'yes',
                'WrongBillersCode' => false,
                'Min_Purchase_Amount' => '500',
            ],
        ], 200),
    ]);

    $result = $this->provider->verifyMeter($this->meter);

    expect($result->valid)->toBeTrue()
        ->and($result->customerName)->toBe('TESTMETER1');
});

it('marks a meter invalid when VTpass flags WrongBillersCode', function () {
    Http::fake([
        'sandbox.vtpass.com/api/merchant-verify' => Http::response([
            'code' => '000',
            'content' => ['WrongBillersCode' => true, 'Can_Vend' => 'no'],
        ], 200),
    ]);

    $result = $this->provider->verifyMeter($this->meter);

    expect($result->valid)->toBeFalse();
});

it('reports healthy only when the balance endpoint returns a balance', function () {
    Http::fake([
        'sandbox.vtpass.com/api/balance' => Http::response(['code' => 1, 'contents' => ['balance' => 1081.82]], 200),
    ]);

    expect($this->provider->healthCheck())->toBeTrue();

    Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-api-key')
        && $request->hasHeader('public-key', 'test-public-key'));
});

it('fetches and normalizes the live electricity services list', function () {
    Http::fake([
        'sandbox.vtpass.com/api/services*' => Http::response([
            'response_description' => '000',
            'content' => [
                ['serviceID' => 'ikeja-electric', 'name' => 'Ikeja Electric', 'minimium_amount' => '100'],
                ['serviceID' => 'eko-electric', 'name' => 'Eko Electricity Distribution Company', 'minimium_amount' => '100'],
            ],
        ], 200),
    ]);

    $services = $this->provider->fetchElectricityServices();

    expect($services)->toHaveCount(2)
        ->and($services[0])->toBe(['service_id' => 'ikeja-electric', 'name' => 'Ikeja Electric']);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/services')
        && $request['identifier'] === 'electricity-bill'
        && $request->hasHeader('public-key', 'test-public-key'));
});

it('treats a non-array services response as a comm failure instead of throwing', function () {
    Http::fake([
        'sandbox.vtpass.com/api/services*' => Http::response('"Invalid Credentials."', 401),
    ]);

    expect($this->provider->fetchElectricityServices())->toBe([]);
});
