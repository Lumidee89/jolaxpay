<?php

use App\Models\Beneficiary;
use App\Models\Biller;
use App\Models\BillerVariation;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * The biller-anchored counterpart to TransactionFlowTest — airtime/data/
 * cable_tv/education purchases go through the same TransactionService
 * pipeline as electricity, just anchored to a Biller instead of a Meter.
 * QUEUE_CONNECTION=sync in the test environment, so the full pipeline runs
 * inline within the request.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('takes an airtime purchase all the way to delivered with just a biller and a phone number', function () {
    $biller = Biller::factory()->create(['service_type' => 'airtime', 'api_provider_code' => 'mtn']);

    $response = $this->withHeader('Idempotency-Key', 'airtime-key-1')
        ->postJson('/api/v1/transactions', [
            'service_type' => 'airtime',
            'biller_id' => $biller->id,
            'amount' => '1000',
            'payment_method' => 'card',
            'recipient_phone' => '08011111111',
        ]);

    $response->assertStatus(202);
    $transaction = Transaction::find($response->json('data.id'));

    expect($transaction->status->value)->toBe('delivered')
        ->and($transaction->biller_id)->toBe($biller->id);
});

it('rejects a data purchase with no variation_code', function () {
    $biller = Biller::factory()->data()->create(['service_type' => 'data', 'api_provider_code' => 'mtn-data']);

    $this->withHeader('Idempotency-Key', 'data-key-1')
        ->postJson('/api/v1/transactions', [
            'service_type' => 'data',
            'biller_id' => $biller->id,
            'biller_identifier' => '08011111111',
            'amount' => '1000',
            'payment_method' => 'card',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('variation_code');
});

it('takes a data purchase all the way to delivered once biller_identifier and variation_code are provided', function () {
    $biller = Biller::factory()->data()->create(['service_type' => 'data', 'api_provider_code' => 'mtn-data']);
    BillerVariation::factory()->for($biller)->create([
        'variation_code' => 'mtn-10mb-100',
        'amount' => '1000.00',
        'fixed_price' => true,
        'is_active' => true,
    ]);

    $response = $this->withHeader('Idempotency-Key', 'data-key-2')
        ->postJson('/api/v1/transactions', [
            'service_type' => 'data',
            'biller_id' => $biller->id,
            'biller_identifier' => '08011111111',
            'variation_code' => 'mtn-10mb-100',
            'amount' => '1000',
            'payment_method' => 'card',
        ]);

    $response->assertStatus(202);
    $transaction = Transaction::find($response->json('data.id'));

    expect($transaction->status->value)->toBe('delivered')
        ->and($transaction->variation_code)->toBe('mtn-10mb-100');
});

it('purchases against a saved beneficiary without repeating the biller_identifier', function () {
    $biller = Biller::factory()->create(['service_type' => 'airtime', 'api_provider_code' => 'glo']);
    $beneficiary = Beneficiary::factory()->for($this->user)->for($biller)->create(['identifier' => '08022222222']);

    $response = $this->withHeader('Idempotency-Key', 'beneficiary-key-1')
        ->postJson('/api/v1/transactions', [
            'service_type' => 'airtime',
            'beneficiary_id' => $beneficiary->id,
            'amount' => '500',
            'payment_method' => 'card',
        ]);

    $response->assertStatus(202);
    $transaction = Transaction::find($response->json('data.id'));

    expect($transaction->biller_id)->toBe($biller->id)
        ->and($transaction->biller_identifier)->toBe('08022222222');
});

it('rejects a beneficiary that belongs to someone else', function () {
    $biller = Biller::factory()->create(['service_type' => 'airtime']);
    $otherUser = User::factory()->create();
    $beneficiary = Beneficiary::factory()->for($otherUser)->for($biller)->create();

    $this->withHeader('Idempotency-Key', 'beneficiary-key-2')
        ->postJson('/api/v1/transactions', [
            'service_type' => 'airtime',
            'beneficiary_id' => $beneficiary->id,
            'amount' => '500',
            'payment_method' => 'card',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('beneficiary_id');
});

it('auto-refunds to wallet when a biller purchase fails vending after payment was captured', function () {
    $biller = Biller::factory()->create(['service_type' => 'airtime']);

    $response = $this->withHeader('Idempotency-Key', 'airtime-fail-key')
        ->postJson('/api/v1/transactions', [
            'service_type' => 'airtime',
            'biller_id' => $biller->id,
            'amount' => '1000',
            'payment_method' => 'card',
            'recipient_phone' => '08011111111',
            'meta' => ['simulate_failure' => true],
        ]);

    $transaction = Transaction::find($response->json('data.id'));

    expect($transaction->status->value)->toBe('failed')
        ->and($transaction->refunded_to_wallet)->toBeTrue();
});
