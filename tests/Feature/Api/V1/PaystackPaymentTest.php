<?php

use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletFundingIntent;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * Both wallet funding and card purchases route through Paystack's hosted
 * checkout the same way: initialize now, confirm later via a
 * `charge.success`/`charge.failed` webhook (PaystackWebhookController) —
 * these tests exercise that full round trip, not just the initialize call.
 */
beforeEach(function () {
    config([
        'payments.domestic.driver' => 'paystack',
        'payments.paystack.secret_key' => 'sk_test_secret',
        'payments.paystack.public_key' => 'pk_test_public',
    ]);

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

function paystackWebhook(array $payload): \Illuminate\Testing\TestResponse
{
    $signature = hash_hmac('sha512', json_encode($payload), 'sk_test_secret');

    return test()->postJson('/api/v1/webhooks/paystack', $payload, ['x-paystack-signature' => $signature]);
}

it('rejects a webhook with a bad signature', function () {
    test()->postJson('/api/v1/webhooks/paystack', ['event' => 'charge.success', 'data' => ['reference' => 'x']], [
        'x-paystack-signature' => 'not-a-real-signature',
    ])->assertStatus(400);
});

it('starts a Paystack checkout for wallet funding instead of crediting immediately', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123', 'reference' => 'ref-generated'],
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/wallet/fund', [
        'amount' => '2000',
        'currency' => 'NGN',
        'payment_method' => 'card',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('requires_redirect', true)
        ->assertJsonStructure(['authorization_url', 'reference']);

    expect(WalletFundingIntent::where('user_id', $this->user->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('credits the wallet once charge.success confirms a funding intent', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'access_code' => 'x', 'reference' => 'x'],
    ], 200)]);

    $init = $this->postJson('/api/v1/wallet/fund', ['amount' => '3000', 'currency' => 'NGN', 'payment_method' => 'card']);
    $reference = $init->json('reference');

    paystackWebhook([
        'event' => 'charge.success',
        'data' => ['reference' => $reference, 'status' => 'success', 'amount' => 300000],
    ])->assertOk();

    $intent = WalletFundingIntent::where('reference', $reference)->first();
    expect($intent->status)->toBe('success')
        ->and((float) $intent->wallet->fresh()->balance)->toBe(3000.0);
});

it('does not credit the wallet on charge.failed', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'access_code' => 'x', 'reference' => 'x'],
    ], 200)]);

    $init = $this->postJson('/api/v1/wallet/fund', ['amount' => '3000', 'currency' => 'NGN', 'payment_method' => 'card']);
    $reference = $init->json('reference');

    paystackWebhook(['event' => 'charge.failed', 'data' => ['reference' => $reference]])->assertOk();

    $intent = WalletFundingIntent::where('reference', $reference)->first();
    expect($intent->status)->toBe('failed')
        ->and((float) $intent->wallet->fresh()->balance)->toBe(0.0);
});

it('starts a Paystack checkout for a card purchase and delivers once charge.success arrives', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/y', 'access_code' => 'y', 'reference' => 'y'],
    ], 200)]);

    $disco = Disco::factory()->create();
    $meter = Meter::factory()->for($this->user)->for($disco)->create();

    $response = $this->withHeader('Idempotency-Key', 'paystack-key-1')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $meter->id,
            'amount' => '5000',
            'currency' => 'NGN',
            'payment_method' => 'card',
        ]);

    $response->assertStatus(202);
    $transaction = Transaction::find($response->json('data.id'));

    expect($transaction->status->value)->toBe('payment_initiated')
        ->and($response->json('data.paystack_authorization_url'))->toBe('https://checkout.paystack.com/y');

    paystackWebhook([
        'event' => 'charge.success',
        'data' => ['reference' => $transaction->meta['paystack_reference'], 'status' => 'success'],
    ])->assertOk();

    expect($transaction->fresh()->status->value)->toBe('delivered');
});

it('reconciles a pending funding intent by asking Paystack directly when no webhook has arrived', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'access_code' => 'x', 'reference' => 'x'],
        ], 200),
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true, 'data' => ['status' => 'success', 'reference' => 'placeholder'],
        ], 200),
    ]);

    $init = $this->postJson('/api/v1/wallet/fund', ['amount' => '3000', 'currency' => 'NGN', 'payment_method' => 'card']);
    $reference = $init->json('reference');

    // No webhook call here — the mobile app's poll is the only thing confirming this.
    $status = $this->getJson("/api/v1/wallet/fund/{$reference}/status");

    $status->assertOk()->assertJsonPath('data.status', 'success');
    expect((float) WalletFundingIntent::where('reference', $reference)->first()->wallet->fresh()->balance)->toBe(3000.0);
});

it('reconciles a pending transaction by asking Paystack directly when no webhook has arrived', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/y', 'access_code' => 'y', 'reference' => 'y'],
        ], 200),
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true, 'data' => ['status' => 'success', 'reference' => 'placeholder'],
        ], 200),
    ]);

    $disco = Disco::factory()->create();
    $meter = Meter::factory()->for($this->user)->for($disco)->create();

    $response = $this->withHeader('Idempotency-Key', 'paystack-key-reconcile')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $meter->id,
            'amount' => '5000',
            'currency' => 'NGN',
            'payment_method' => 'card',
        ]);
    $transaction = Transaction::find($response->json('data.id'));

    // No webhook call here — only the status poll.
    $status = $this->getJson("/api/v1/transactions/{$transaction->id}/status");

    expect($status->json('data.status'))->not->toBe('payment_initiated')
        ->and($transaction->fresh()->status->value)->toBe('delivered');
});

it('does not double-credit a funding intent already confirmed by the webhook when the poll also reconciles', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'access_code' => 'x', 'reference' => 'x'],
        ], 200),
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true, 'data' => ['status' => 'success', 'reference' => 'placeholder'],
        ], 200),
    ]);

    $init = $this->postJson('/api/v1/wallet/fund', ['amount' => '3000', 'currency' => 'NGN', 'payment_method' => 'card']);
    $reference = $init->json('reference');

    paystackWebhook(['event' => 'charge.success', 'data' => ['reference' => $reference, 'status' => 'success']])->assertOk();
    $this->getJson("/api/v1/wallet/fund/{$reference}/status")->assertOk();

    expect((float) WalletFundingIntent::where('reference', $reference)->first()->wallet->fresh()->balance)->toBe(3000.0);
});

it('fails the transaction on charge.failed without touching the wallet', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/z', 'access_code' => 'z', 'reference' => 'z'],
    ], 200)]);

    $disco = Disco::factory()->create();
    $meter = Meter::factory()->for($this->user)->for($disco)->create();

    $response = $this->withHeader('Idempotency-Key', 'paystack-key-2')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $meter->id,
            'amount' => '5000',
            'currency' => 'NGN',
            'payment_method' => 'card',
        ]);

    $transaction = Transaction::find($response->json('data.id'));

    paystackWebhook([
        'event' => 'charge.failed',
        'data' => ['reference' => $transaction->meta['paystack_reference']],
    ])->assertOk();

    expect($transaction->fresh()->status->value)->toBe('failed')
        // Payment was never actually captured, so fail()'s auto-refund
        // correctly has nothing to refund.
        ->and($transaction->fresh()->refunded_to_wallet)->toBeFalse();
});
