<?php

use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Models\Biller;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = app(LedgerService::class);
    $this->ledger->credit($this->ledger->walletFor($this->user), '20000.00', LedgerReason::WalletFunding);
    Sanctum::actingAs($this->user);
});

it('repeats a past electricity purchase against the same meter', function () {
    $disco = Disco::factory()->create();
    $meter = Meter::factory()->for($this->user)->for($disco)->create();
    $original = Transaction::factory()->for($this->user)->for($meter)->delivered()->create([
        'amount' => '3000.00', 'fee' => '45.00', 'payment_method' => 'wallet',
    ]);

    $response = $this->withHeader('Idempotency-Key', 'repeat-key-1')
        ->postJson("/api/v1/transactions/{$original->id}/repeat");

    $response->assertStatus(202);
    $repeated = Transaction::find($response->json('data.id'));

    expect($repeated->id)->not->toBe($original->id)
        ->and($repeated->meter_id)->toBe($meter->id)
        ->and((float) $repeated->amount)->toBe(3000.0)
        ->and($repeated->status->value)->toBe('delivered');
});

it('repeats a past biller purchase, allowing the payment method to change', function () {
    $biller = Biller::factory()->create(['service_type' => 'airtime']);
    $original = Transaction::factory()->for($this->user)->forBiller()->for($biller)->delivered()->create([
        'amount' => '500.00', 'fee' => '7.50', 'payment_method' => 'card', 'biller_identifier' => '08011111111',
    ]);

    $response = $this->withHeader('Idempotency-Key', 'repeat-key-2')
        ->postJson("/api/v1/transactions/{$original->id}/repeat", ['payment_method' => 'wallet']);

    $response->assertStatus(202);
    $repeated = Transaction::find($response->json('data.id'));

    expect($repeated->biller_id)->toBe($biller->id)
        ->and($repeated->biller_identifier)->toBe('08011111111')
        ->and($repeated->payment_method)->toBe('wallet');
});

it("blocks repeating someone else's transaction", function () {
    $otherUser = User::factory()->create();
    $disco = Disco::factory()->create();
    $meter = Meter::factory()->for($otherUser)->for($disco)->create();
    $original = Transaction::factory()->for($otherUser)->for($meter)->delivered()->create();

    $this->withHeader('Idempotency-Key', 'repeat-key-3')
        ->postJson("/api/v1/transactions/{$original->id}/repeat")
        ->assertForbidden();
});
