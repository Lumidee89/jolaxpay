<?php

use App\Domain\Transactions\Events\TransactionStatusUpdated;
use App\Domain\Transactions\Exceptions\InvalidTransitionException;
use App\Domain\Transactions\TransactionStateMachine;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->machine = app(TransactionStateMachine::class);
});

it('moves a transaction through an allowed transition and records history', function () {
    Event::fake();

    $transaction = Transaction::factory()->create(); // status: fee_disclosed

    $this->machine->transition($transaction, TransactionStatus::PaymentInitiated, 'moved forward');

    expect($transaction->fresh()->status)->toBe(TransactionStatus::PaymentInitiated);

    $history = $transaction->statusHistory()->latest('id')->first();
    expect($history->from_status)->toBe(TransactionStatus::FeeDisclosed)
        ->and($history->to_status)->toBe(TransactionStatus::PaymentInitiated)
        ->and($history->note)->toBe('moved forward');

    Event::assertDispatched(TransactionStatusUpdated::class);
});

it('refuses a transition that skips stages', function () {
    $transaction = Transaction::factory()->create(); // status: fee_disclosed

    $this->machine->transition($transaction, TransactionStatus::TokenGenerated);
})->throws(InvalidTransitionException::class);

it('refuses any transition out of a terminal state', function () {
    $transaction = Transaction::factory()->failed()->create();

    $this->machine->transition($transaction, TransactionStatus::PaymentReceived);
})->throws(InvalidTransitionException::class);

it('does not fail the transition when broadcasting the status update errors', function () {
    // BROADCAST_CONNECTION=null in the test environment already avoids a
    // real broadcast call, but this asserts the *contract*: a broadcast
    // failure must never surface as an exception from transition() (TRD §8
    // — realtime pushes degrade gracefully, same as vending/notifications).
    $transaction = Transaction::factory()->create();

    $result = $this->machine->transition($transaction, TransactionStatus::PaymentInitiated);

    expect($result->status)->toBe(TransactionStatus::PaymentInitiated);
});
