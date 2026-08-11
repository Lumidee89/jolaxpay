<?php

use App\Domain\Transactions\TransactionStateMachine;
use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Enums\TransactionStatus;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->machine = app(TransactionStateMachine::class);
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
    $this->disco = Disco::factory()->create();
    $this->meter = Meter::factory()->for($this->user)->for($this->disco)->create();
    Sanctum::actingAs($this->user);
});

function deliverInsightTx(TransactionStateMachine $machine, Transaction $transaction): Transaction
{
    foreach ([
        TransactionStatus::PaymentInitiated,
        TransactionStatus::PaymentReceived,
        TransactionStatus::PaymentConfirmed,
        TransactionStatus::GeneratingToken,
        TransactionStatus::TokenGenerated,
        TransactionStatus::Delivered,
    ] as $next) {
        $machine->transition($transaction, $next);
    }

    return $transaction->fresh();
}

it('returns no insight for a brand-new user with no history', function () {
    $response = $this->getJson('/api/v1/insights');

    $response->assertOk()->assertJsonPath('data', null);
});

it('suggests a top-up amount from the average of past fundings, or a flat default with none', function () {
    $this->getJson('/api/v1/insights/suggested-top-up')->assertOk()->assertJsonPath('data.amount', 2000);

    $wallet = $this->ledger->walletFor($this->user);
    $this->ledger->credit($wallet, '1000.00', LedgerReason::WalletFunding);
    $this->ledger->credit($wallet, '5000.00', LedgerReason::WalletFunding);

    $this->getJson('/api/v1/insights/suggested-top-up')->assertOk()->assertJsonPath('data.amount', 3000);
});

it('suggests a purchase amount based on this meter\'s own purchase history', function () {
    deliverInsightTx($this->machine, Transaction::factory()->for($this->user)->for($this->meter)->create(['amount' => '4000.00']));
    deliverInsightTx($this->machine, Transaction::factory()->for($this->user)->for($this->meter)->create(['amount' => '6000.00']));

    $response = $this->getJson('/api/v1/insights/suggested-amount?service_type=electricity&meter_id='.$this->meter->id);

    $response->assertOk()->assertJsonPath('data.amount', 5000);
});

it('surfaces a low-balance insight when the wallet is below the usual recharge amount', function () {
    deliverInsightTx($this->machine, Transaction::factory()->for($this->user)->for($this->meter)->create(['amount' => '5000.00']));

    $response = $this->getJson('/api/v1/insights');

    $response->assertOk()->assertJsonPath('data.type', 'low_balance');
});

it('surfaces a spending summary once the wallet is topped up above the usual amount', function () {
    deliverInsightTx($this->machine, Transaction::factory()->for($this->user)->for($this->meter)->create(['amount' => '5000.00']));

    $wallet = $this->ledger->walletFor($this->user);
    $this->ledger->credit($wallet, '50000.00', LedgerReason::WalletFunding);

    $response = $this->getJson('/api/v1/insights');

    $response->assertOk()->assertJsonPath('data.type', 'spending_summary');
});
