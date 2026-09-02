<?php

use App\Domain\Vending\Providers\SchedwaveBillerProvider;
use App\Domain\Vending\Providers\SchedwaveElectricityProvider;
use App\Models\Biller;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'vending.schedwave.api_key' => 'sched_test_key',
        'vending.schedwave.base_url' => 'https://schedwave.test/api/v1',
        'vending.schedwave.plan_markup' => '50.00',
    ]);
    Cache::flush();
});

it('purchases airtime for the entered number with Schedwave network mapping', function () {
    Http::fake(['*/vtu/airtime' => Http::response(['error' => false, 'order_id' => 1042, 'reference' => 'API_1', 'status' => 'completed', 'message' => 'Airtime Purchase Successful.'])]);
    $user = User::factory()->create(['phone_number' => '08099999999']);
    $biller = Biller::factory()->create(['code' => 'AIRTEL_AIRTIME', 'service_type' => 'airtime']);
    $transaction = Transaction::factory()->for($user)->for($biller)->create(['service_type' => 'airtime', 'biller_identifier' => '08012345678', 'amount' => '500.00']);

    $result = app(SchedwaveBillerProvider::class)->vend($transaction);

    expect($result->successful)->toBeTrue()->and($result->providerReference)->toBe('API_1');
    Http::assertSent(fn ($request) => $request->url() === 'https://schedwave.test/api/v1/vtu/airtime'
        && $request['network'] === 2 && $request['phone'] === '08012345678' && $request['amount'] === 500
        && $request->hasHeader('Authorization', 'Bearer sched_test_key'));
});

it('purchases a data plan and fetches Schedwave plan pricing', function () {
    Http::fake([
        '*/vtu/data-plans*' => Http::response(['error' => false, 'plans' => [['plan_id' => 22, 'network' => 'MTN', 'datasize' => '1GB', 'type' => 'SME', 'validity' => 30, 'price' => 683]]]),
        '*/vtu/data' => Http::response(['error' => false, 'order_id' => 1043, 'reference' => 'API_2', 'status' => 'completed']),
    ]);
    $user = User::factory()->create();
    $biller = Biller::factory()->data()->create(['code' => 'MTN_DATA']);
    $transaction = Transaction::factory()->for($user)->for($biller)->create(['service_type' => 'data', 'biller_identifier' => '08012345678', 'variation_code' => '22']);
    $provider = app(SchedwaveBillerProvider::class);

    expect($provider->vend($transaction)->successful)->toBeTrue()
        ->and($provider->fetchVariations($biller)[0])->toMatchArray(['variation_code' => '22', 'amount' => '733.00']);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/vtu/data') && $request['network'] === 1 && $request['plan_id'] === 22);
});

it('verifies and purchases cable tv through Schedwave', function () {
    Http::fake([
        '*/vtu/cable-validate*' => Http::response(['error' => false, 'name' => 'A Customer', 'message' => 'verified']),
        '*/vtu/cable' => Http::response(['error' => false, 'order_id' => 10, 'reference' => 'CABLE_1', 'status' => 'completed']),
    ]);
    $user = User::factory()->create();
    $biller = Biller::factory()->cableTv()->create(['code' => 'DSTV']);
    $transaction = Transaction::factory()->for($user)->for($biller)->create(['service_type' => 'cable_tv', 'biller_identifier' => '1234567890', 'variation_code' => '25']);
    $provider = app(SchedwaveBillerProvider::class);

    expect($provider->verify($biller, '1234567890')->customerName)->toBe('A Customer')
        ->and($provider->vend($transaction)->successful)->toBeTrue();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/vtu/cable') && $request['cable'] === 2 && $request['cable_plan'] === 25);
});

it('purchases an exam pin and rejects unsupported WAEC registration', function () {
    Http::fake(['*/vtu/exam' => Http::response(['error' => false, 'order_id' => 11, 'reference' => 'EXAM_1', 'pin' => '1234567890', 'status' => 'completed'])]);
    $user = User::factory()->create();
    $jamb = Biller::factory()->education()->create(['code' => 'JAMB']);
    $transaction = Transaction::factory()->for($user)->for($jamb)->create(['service_type' => 'education', 'variation_code' => '4']);
    $waecRegistration = Biller::factory()->education()->create(['code' => 'WAEC_REG']);
    $unsupported = Transaction::factory()->for($user)->for($waecRegistration)->create(['service_type' => 'education']);
    $provider = app(SchedwaveBillerProvider::class);

    expect($provider->vend($transaction)->token)->toBe('1234567890')
        ->and($provider->vend($unsupported)->successful)->toBeFalse();
});

it('requeries an existing Schedwave order instead of submitting it twice', function () {
    Http::fake(['*/vtu/orders*' => Http::response([
        'error' => false, 'page' => 1, 'total_pages' => 1,
        'orders' => [['order_id' => 77, 'reference' => 'API_77', 'status' => 'completed']],
    ])]);
    $user = User::factory()->create();
    $biller = Biller::factory()->create(['code' => 'MTN_AIRTIME', 'service_type' => 'airtime']);
    $transaction = Transaction::factory()->for($user)->for($biller)->create([
        'service_type' => 'airtime', 'meta' => ['schedwave_order_id' => 77],
    ]);

    $result = app(SchedwaveBillerProvider::class)->vend($transaction);

    expect($result->successful)->toBeTrue()->and($result->providerReference)->toBe('API_77');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/vtu/orders'));
});

it('verifies and vends electricity using the live Schedwave provider catalog', function () {
    Http::fake([
        '*/vtu/electricity-providers' => Http::response(['error' => false, 'providers' => [['id' => 1, 'name' => 'Ikeja Electric', 'code' => 'IE']]]),
        '*/vtu/electricity-validate*' => Http::response(['error' => false, 'name' => 'IBRAHIM MUSA', 'address' => 'Lagos', 'message' => 'verified']),
        '*/vtu/electricity' => Http::response(['error' => false, 'order_id' => 1045, 'reference' => 'POWER_1', 'token' => '1234-5678', 'status' => 'completed']),
    ]);
    $user = User::factory()->create();
    $disco = Disco::factory()->create(['code' => 'IKEDC']);
    $meter = Meter::factory()->for($user)->for($disco)->create(['meter_number' => '56789076064', 'meter_type' => 'prepaid']);
    $transaction = Transaction::factory()->for($user)->for($meter)->create(['service_type' => 'electricity', 'amount' => '2000.00']);
    $provider = app(SchedwaveElectricityProvider::class);

    expect($provider->verifyMeter($meter))->customerName->toBe('IBRAHIM MUSA')
        ->and($provider->vend($transaction)->token)->toBe('1234-5678');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/vtu/electricity') && $request['disco'] === 1 && $request['meter_number'] === '56789076064');
});
