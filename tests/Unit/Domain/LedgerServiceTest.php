<?php

use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerEntryType;
use App\Enums\LedgerReason;
use App\Models\Transaction;
use App\Models\User;

/**
 * Ledger correctness is the highest-priority test surface in the app
 * (Implementation Plan §5) — every wallet operation must be covered.
 */
beforeEach(function () {
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
});

it('creates a wallet on first access with a zero balance', function () {
    $wallet = $this->ledger->walletFor($this->user);

    expect($wallet->currency)->toBe('NGN')
        ->and((float) $wallet->balance)->toBe(0.0);
});

it('credits a wallet and records a balanced ledger entry', function () {
    $wallet = $this->ledger->walletFor($this->user);

    $entry = $this->ledger->credit($wallet, '1000.00', LedgerReason::WalletFunding);

    expect((float) $wallet->fresh()->balance)->toBe(1000.0)
        ->and($entry->type)->toBe(LedgerEntryType::Credit)
        ->and((float) $entry->balance_after)->toBe(1000.0);
});

it('debits a wallet with sufficient funds', function () {
    $wallet = $this->ledger->walletFor($this->user);
    $this->ledger->credit($wallet, '1000.00', LedgerReason::WalletFunding);

    $entry = $this->ledger->debit($wallet, '400.00', LedgerReason::Purchase);

    expect((float) $wallet->fresh()->balance)->toBe(600.0)
        ->and($entry->type)->toBe(LedgerEntryType::Debit);
});

it('refuses to debit a wallet past zero', function () {
    $wallet = $this->ledger->walletFor($this->user);
    $this->ledger->credit($wallet, '100.00', LedgerReason::WalletFunding);

    $this->ledger->debit($wallet, '150.00', LedgerReason::Purchase);
})->throws(InsufficientFundsException::class);

it('leaves the balance unchanged when a debit is refused', function () {
    $wallet = $this->ledger->walletFor($this->user);
    $this->ledger->credit($wallet, '100.00', LedgerReason::WalletFunding);

    try {
        $this->ledger->debit($wallet, '150.00', LedgerReason::Purchase);
    } catch (InsufficientFundsException) {
        // expected
    }

    expect((float) $wallet->fresh()->balance)->toBe(100.0);
});

it('refunds a failed transaction to the wallet exactly once', function () {
    $wallet = $this->ledger->walletFor($this->user);
    $transaction = Transaction::factory()->for($this->user)->create([
        'amount' => '2000.00',
        'fee' => '30.00',
    ]);

    $first = $this->ledger->refundToWallet($transaction);
    $second = $this->ledger->refundToWallet($transaction->fresh());

    expect((float) $first->amount)->toBe(2030.0)
        ->and($second)->toBeNull()
        ->and((float) $wallet->fresh()->balance)->toBe(2030.0);
});

it('never lets concurrent debits push a wallet negative', function () {
    // Simulates two "concurrent" purchase debits against the same balance —
    // the second must fail cleanly rather than silently overdraw the wallet.
    $wallet = $this->ledger->walletFor($this->user);
    $this->ledger->credit($wallet, '1000.00', LedgerReason::WalletFunding);

    $this->ledger->debit($wallet, '700.00', LedgerReason::Purchase);

    expect(fn () => $this->ledger->debit($wallet->fresh(), '700.00', LedgerReason::Purchase))
        ->toThrow(InsufficientFundsException::class);

    expect((float) $wallet->fresh()->balance)->toBe(300.0);
});
