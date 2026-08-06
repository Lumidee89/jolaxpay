<?php

use App\Domain\Vending\Providers\VtpassBillerProvider;
use App\Models\Biller;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

/**
 * Response shapes below are taken verbatim from VTpass's own published
 * examples (https://vtpass.com/documentation/) for each product family —
 * mirrors tests/Unit/Domain/VtpassElectricityProviderTest.php.
 */
beforeEach(function () {
    config([
        'vending.vtpass.env' => 'sandbox',
        'vending.vtpass.api_key' => 'test-api-key',
        'vending.vtpass.secret_key' => 'test-secret-key',
        'vending.vtpass.public_key' => 'test-public-key',
    ]);

    $this->provider = new VtpassBillerProvider;
});

it('vends airtime with no billersCode or variation_code', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response([
            'code' => '000',
            'content' => ['transactions' => [
                'status' => 'delivered', 'product_name' => 'MTN Airtime VTU',
                'unique_element' => '08011111111', 'transactionId' => '17415980564672211596777904',
            ]],
            'response_description' => 'TRANSACTION SUCCESSFUL',
            'requestId' => '2025031010146932932',
            'amount' => 20,
        ], 200),
    ]);

    $biller = Biller::factory()->create(['api_provider_code' => 'mtn', 'requires_billers_code' => false, 'requires_variation' => false]);
    $transaction = Transaction::factory()->forBiller()->for($biller)->create(['amount' => '20.00', 'recipient_phone' => '08011111111']);

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeTrue()
        ->and($result->token)->toBeNull(); // airtime has nothing to hand back

    Http::assertSent(fn ($request) => $request['serviceID'] === 'mtn'
        && $request['phone'] === '08011111111'
        && ! isset($request['billersCode'])
        && ! isset($request['variation_code']));
});

it('vends data with billersCode (subscriber phone) and variation_code', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response([
            'code' => '000',
            'content' => ['transactions' => ['status' => 'delivered', 'product_name' => 'MTN Data', 'transactionId' => 'TXN456']],
            'response_description' => 'TRANSACTION SUCCESSFUL',
        ], 200),
    ]);

    $biller = Biller::factory()->data()->create(['api_provider_code' => 'mtn-data']);
    $transaction = Transaction::factory()->forBiller()->for($biller)->create([
        'service_type' => 'data', 'amount' => '100.00',
        'biller_identifier' => '08011111111', 'variation_code' => 'mtn-10mb-100',
        'recipient_phone' => '08011111111',
    ]);

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeTrue();

    Http::assertSent(fn ($request) => $request['serviceID'] === 'mtn-data'
        && $request['billersCode'] === '08011111111'
        && $request['variation_code'] === 'mtn-10mb-100');
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
            'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => 'TXN789']],
            'response_description' => 'TRANSACTION SUCCESSFUL',
        ], 200),
    ]);

    $biller = Biller::factory()->create(['api_provider_code' => 'glo']);
    $transaction = Transaction::factory()->forBiller()->for($biller)->create(['amount' => '500.00']);

    $first = $this->provider->vend($transaction);
    expect($first->successful)->toBeFalse();

    $requestId = $transaction->fresh()->meta['vtpass_request_id'];
    expect($requestId)->not->toBeNull();

    $second = $this->provider->vend($transaction->fresh());
    expect($second->successful)->toBeTrue();

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.vtpass.com/api/requery'
        && $request['request_id'] === $requestId);
});

it('verifies a DSTV smartcard and returns the customer name', function () {
    Http::fake([
        'sandbox.vtpass.com/api/merchant-verify' => Http::response([
            'code' => '000',
            'content' => ['Customer_Name' => 'TEST METER', 'Status' => 'ACTIVE', 'Customer_Number' => '8061522780'],
        ], 200),
    ]);

    $biller = Biller::factory()->cableTv()->create(['api_provider_code' => 'dstv']);

    $result = $this->provider->verify($biller, '1212121212');

    expect($result->valid)->toBeTrue()
        ->and($result->customerName)->toBe('TEST METER');

    Http::assertSent(fn ($request) => $request['billersCode'] === '1212121212' && $request['serviceID'] === 'dstv');
});

it('pays a DSTV subscription with subscription_type and quantity', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response([
            'code' => '000',
            'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => '17416009779459629327738818']],
            'response_description' => 'TRANSACTION SUCCESSFUL',
        ], 200),
    ]);

    $biller = Biller::factory()->cableTv()->create(['api_provider_code' => 'dstv']);
    $transaction = Transaction::factory()->forBiller()->for($biller)->create([
        'service_type' => 'cable_tv', 'amount' => '1850.00',
        'biller_identifier' => '1212121212', 'variation_code' => 'dstv-padi',
        'meta' => ['subscription_type' => 'change'],
    ]);

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeTrue();

    Http::assertSent(fn ($request) => $request['billersCode'] === '1212121212'
        && $request['variation_code'] === 'dstv-padi'
        && $request['subscription_type'] === 'change');
});

it('extracts a WAEC pin from purchased_code without a nested transactions object', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response([
            'code' => '000',
            'response_description' => 'TRANSACTION SUCCESSFUL',
            'purchased_code' => 'Serial No:WRN123456790, pin: 098765432112',
            'amount' => 3400,
            'requestId' => '20250218124018-c3pwwi49eid',
        ], 200),
    ]);

    $biller = Biller::factory()->education()->create(['api_provider_code' => 'waec', 'requires_billers_code' => false]);
    $transaction = Transaction::factory()->forBiller()->for($biller)->create([
        'service_type' => 'education', 'amount' => '3400.00', 'variation_code' => 'waecdirect',
    ]);

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeTrue()
        ->and($result->token)->toBe('Serial No:WRN123456790, pin: 098765432112');

    Http::assertSent(fn ($request) => ! isset($request['billersCode']) && $request['variation_code'] === 'waecdirect');
});

it('extracts a JAMB pin and sends billersCode as the Profile ID', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response([
            'code' => '000',
            'response_description' => 'TRANSACTION SUCCESSFUL',
            'purchased_code' => 'Pin : 3678251321392432',
            'amount' => 7700,
        ], 200),
    ]);

    $biller = Biller::factory()->education()->create(['api_provider_code' => 'jamb', 'requires_billers_code' => true]);
    $transaction = Transaction::factory()->forBiller()->for($biller)->create([
        'service_type' => 'education', 'amount' => '7700.00',
        'biller_identifier' => '0123456789', 'variation_code' => 'utme-mock',
    ]);

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeTrue()
        ->and($result->token)->toBe('Pin : 3678251321392432');

    Http::assertSent(fn ($request) => $request['billersCode'] === '0123456789' && $request['variation_code'] === 'utme-mock');
});

it('treats a hard failure code as unsuccessful', function () {
    Http::fake([
        'sandbox.vtpass.com/api/pay' => Http::response(['code' => '016', 'response_description' => 'TRANSACTION FAILED'], 200),
    ]);

    $biller = Biller::factory()->create(['api_provider_code' => 'airtel']);
    $transaction = Transaction::factory()->forBiller()->for($biller)->create();

    $result = $this->provider->vend($transaction);

    expect($result->successful)->toBeFalse()->and($result->message)->toBe('TRANSACTION FAILED');
});

it('fetches and normalizes GOTV variation codes', function () {
    Http::fake([
        'sandbox.vtpass.com/api/service-variations*' => Http::response([
            'response_description' => '000',
            'content' => [
                'ServiceName' => 'GOTV Payment',
                'serviceID' => 'gotv',
                'variations' => [
                    ['variation_code' => 'gotv-lite', 'name' => 'GOtv Lite N400', 'variation_amount' => '400.00', 'fixedPrice' => 'Yes'],
                    ['variation_code' => 'gotv-value', 'name' => 'GOtv value N1250', 'variation_amount' => '1250.00', 'fixedPrice' => 'Yes'],
                ],
            ],
        ], 200),
    ]);

    $biller = Biller::factory()->cableTv()->create(['api_provider_code' => 'gotv']);

    $variations = $this->provider->fetchVariations($biller);

    expect($variations)->toHaveCount(2)
        ->and($variations[0]['variation_code'])->toBe('gotv-lite')
        ->and($variations[0]['amount'])->toBe('400.00')
        ->and($variations[0]['fixed_price'])->toBeTrue();
});

it('reports healthy only when the balance endpoint returns a balance', function () {
    Http::fake([
        'sandbox.vtpass.com/api/balance' => Http::response(['code' => 1, 'contents' => ['balance' => 1081.82]], 200),
    ]);

    expect($this->provider->healthCheck())->toBeTrue();
});
