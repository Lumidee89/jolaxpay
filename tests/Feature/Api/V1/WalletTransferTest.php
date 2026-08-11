<?php

use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->sender = User::factory()->create();
    $this->recipient = User::factory()->create();
    $this->ledger = app(LedgerService::class);
});

it('sends money from one wallet to another by wallet_address', function () {
    $senderWallet = $this->ledger->walletFor($this->sender);
    $this->ledger->credit($senderWallet, '5000.00', LedgerReason::WalletFunding);
    $recipientWallet = $this->ledger->walletFor($this->recipient);

    Sanctum::actingAs($this->sender);

    $this->postJson('/api/v1/wallet/transfer', [
        'wallet_address' => $recipientWallet->wallet_address,
        'amount' => '1500.00',
        'note' => 'Lunch money',
    ])->assertCreated();

    expect((float) $senderWallet->fresh()->balance)->toBe(3500.0)
        ->and((float) $recipientWallet->fresh()->balance)->toBe(1500.0);
});

it('rejects a transfer with insufficient funds', function () {
    $senderWallet = $this->ledger->walletFor($this->sender);
    $this->ledger->credit($senderWallet, '100.00', LedgerReason::WalletFunding);
    $recipientWallet = $this->ledger->walletFor($this->recipient);

    Sanctum::actingAs($this->sender);

    $this->postJson('/api/v1/wallet/transfer', [
        'wallet_address' => $recipientWallet->wallet_address,
        'amount' => '500.00',
    ])->assertStatus(422);
});

it('rejects a transfer to a wallet_address that does not exist', function () {
    $this->ledger->credit($this->ledger->walletFor($this->sender), '5000.00', LedgerReason::WalletFunding);

    Sanctum::actingAs($this->sender);

    $this->postJson('/api/v1/wallet/transfer', [
        'wallet_address' => 'JLXNOTREAL00',
        'amount' => '100.00',
    ])->assertStatus(422);
});
