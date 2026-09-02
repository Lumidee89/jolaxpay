<?php

use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * QUEUE_CONNECTION=sync in the test environment (phpunit.xml), so the
 * ProcessTransactionPayment → ProcessVending → DeliverToken pipeline runs
 * inline within the request — no queue worker needed for these assertions.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->disco = Disco::factory()->create();
    $this->meter = Meter::factory()->for($this->user)->for($this->disco)->create();

    Sanctum::actingAs($this->user);
});

it('requires an Idempotency-Key header to initiate a purchase', function () {
    $this->postJson('/api/v1/transactions', [
        'meter_id' => $this->meter->id,
        'amount' => '5000',
        'payment_method' => 'card',
    ])->assertStatus(422)->assertJsonPath('message', 'The Idempotency-Key header is required for this request.');
});

it('does not require amount_ngn on an ordinary NGN purchase, currency sent explicitly', function () {
    // Regression: `required_if:currency,!=,NGN` isn't valid Laravel syntax
    // (required_if only does exact-value matching) — it silently parsed as
    // "required if currency equals '!=' or 'NGN'", wrongly requiring
    // amount_ngn on exactly this request shape (what the mobile app sends).
    $this->withHeader('Idempotency-Key', 'ngn-key-1')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'currency' => 'NGN',
            'payment_method' => 'card',
        ])->assertStatus(202);
});

it('requires amount_ngn for a non-NGN (Diaspora) purchase', function () {
    $this->withHeader('Idempotency-Key', 'usd-key-1')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '50',
            'currency' => 'USD',
            'payment_method' => 'card',
        ])->assertStatus(422)->assertJsonValidationErrors('amount_ngn');
});

it('takes a card purchase all the way to delivered', function () {
    $response = $this->withHeader('Idempotency-Key', 'test-key-1')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'payment_method' => 'card',
            'delivery_destination' => 'me',
        ]);

    $response->assertStatus(202);
    $id = $response->json('data.id');

    $transaction = Transaction::find($id);
    expect($transaction->status->value)->toBe('delivered')
        ->and($transaction->token)->not->toBeNull()
        ->and($transaction->fee)->toEqual('0.00');
});

it('debits the wallet for a wallet-funded purchase', function () {
    $ledger = app(LedgerService::class);
    $wallet = $ledger->walletFor($this->user);
    $ledger->credit($wallet, '10000.00', LedgerReason::WalletFunding);

    $this->withHeader('Idempotency-Key', 'test-key-2')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'payment_method' => 'wallet',
        ])->assertStatus(202);

    expect((float) $wallet->fresh()->balance)->toBe(10000.0 - 5000.0);
});

it('fails cleanly with no side effects when the wallet has insufficient funds', function () {
    $ledger = app(LedgerService::class);
    $wallet = $ledger->walletFor($this->user);
    $ledger->credit($wallet, '100.00', LedgerReason::WalletFunding);

    $response = $this->withHeader('Idempotency-Key', 'test-key-3')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'payment_method' => 'wallet',
        ]);

    $id = $response->json('data.id');
    $transaction = Transaction::find($id);

    expect($transaction->status->value)->toBe('failed')
        ->and((float) $wallet->fresh()->balance)->toBe(100.0); // untouched
});

it('auto-refunds to wallet when vending fails after payment was captured', function () {
    $response = $this->withHeader('Idempotency-Key', 'test-key-4')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'payment_method' => 'card',
            'meta' => ['simulate_failure' => true],
        ]);

    $id = $response->json('data.id');
    $transaction = Transaction::find($id);
    $wallet = app(LedgerService::class)->walletFor($this->user);

    expect($transaction->status->value)->toBe('failed')
        ->and($transaction->refunded_to_wallet)->toBeTrue()
        ->and((float) $wallet->fresh()->balance)->toBe(5000.0);
});

it('replays the original response for a repeated idempotency key instead of double-charging', function () {
    $first = $this->withHeader('Idempotency-Key', 'test-key-5')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'payment_method' => 'card',
        ]);

    $second = $this->withHeader('Idempotency-Key', 'test-key-5')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'payment_method' => 'card',
        ]);

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Transaction::count())->toBe(1);
});

it('lets the buyer confirm the outcome of their own transaction', function () {
    $response = $this->withHeader('Idempotency-Key', 'test-key-6')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'payment_method' => 'card',
        ]);
    $id = $response->json('data.id');

    $this->postJson("/api/v1/transactions/{$id}/outcome", ['confirmed' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'outcome_confirmed');
});

it("blocks a stranger from viewing someone else's transaction", function () {
    $other = User::factory()->create();
    $transaction = Transaction::factory()->for($this->user)->for($this->meter)->create();

    Sanctum::actingAs($other);

    $this->getJson("/api/v1/transactions/{$transaction->id}")->assertForbidden();
});
