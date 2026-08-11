<?php

use App\Domain\Transactions\TransactionStateMachine;
use App\Enums\TransactionStatus;
use App\Models\Biller;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->machine = app(TransactionStateMachine::class);
    $this->user = User::factory()->create();
    $this->disco = Disco::factory()->create();
    $this->meter = Meter::factory()->for($this->user)->for($this->disco)->create();
    Sanctum::actingAs($this->user);
});

function deliverSearchTx(TransactionStateMachine $machine, Transaction $transaction): Transaction
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

it('finds transactions by service type keyword', function () {
    $airtimeBiller = Biller::factory()->create(['service_type' => 'airtime']);
    deliverSearchTx($this->machine, Transaction::factory()->for($this->user)->for($this->meter)->create(['amount' => '5000.00']));
    $airtime = Transaction::factory()->for($this->user)->create([
        'meter_id' => null, 'biller_id' => $airtimeBiller->id, 'service_type' => 'airtime', 'amount' => '1000.00',
    ]);
    deliverSearchTx($this->machine, $airtime);

    $response = $this->getJson('/api/v1/transactions/search?q=airtime');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($airtime->id);
});

it('finds transactions by status and amount comparison combined', function () {
    $cheap = Transaction::factory()->for($this->user)->for($this->meter)->create(['amount' => '1000.00']);
    deliverSearchTx($this->machine, $cheap);
    $expensive = Transaction::factory()->for($this->user)->for($this->meter)->create(['amount' => '9000.00']);
    deliverSearchTx($this->machine, $expensive);

    $response = $this->getJson('/api/v1/transactions/search?'.http_build_query(['q' => 'electricity over 5000']));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($expensive->id);
});

it('falls back to a substring match on the meter label', function () {
    $meter = Meter::factory()->for($this->user)->for($this->disco)->create(['label' => 'Office Meter']);
    $tx = Transaction::factory()->for($this->user)->for($meter)->create();
    deliverSearchTx($this->machine, $tx);
    deliverSearchTx($this->machine, Transaction::factory()->for($this->user)->for($this->meter)->create());

    $response = $this->getJson('/api/v1/transactions/search?q=office');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($tx->id);
});

it('only searches the authenticated user\'s own transactions', function () {
    $stranger = User::factory()->create();
    $strangerMeter = Meter::factory()->for($stranger)->for($this->disco)->create();
    deliverSearchTx($this->machine, Transaction::factory()->for($stranger)->for($strangerMeter)->create());

    $response = $this->getJson('/api/v1/transactions/search?q=electricity');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});
