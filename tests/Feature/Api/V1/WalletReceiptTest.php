<?php

use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->ledger = app(LedgerService::class);
});

it('downloads a PDF receipt for a wallet ledger entry the user owns', function () {
    $wallet = $this->ledger->walletFor($this->owner);
    $entry = $this->ledger->credit($wallet, '2500.00', LedgerReason::WalletFunding);

    Sanctum::actingAs($this->owner);

    $response = $this->get("/api/v1/wallet/entries/{$entry->id}/receipt");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('refuses a wallet receipt for an entry belonging to another user', function () {
    $wallet = $this->ledger->walletFor($this->owner);
    $entry = $this->ledger->credit($wallet, '2500.00', LedgerReason::WalletFunding);

    Sanctum::actingAs($this->stranger);

    $this->get("/api/v1/wallet/entries/{$entry->id}/receipt")->assertForbidden();
});
