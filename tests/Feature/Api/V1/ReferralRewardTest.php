<?php

use App\Domain\Transactions\TransactionStateMachine;
use App\Domain\Wallet\LedgerService;
use App\Enums\TransactionStatus;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->machine = app(TransactionStateMachine::class);
    $this->ledger = app(LedgerService::class);
});

function driveToDelivered(TransactionStateMachine $machine, Transaction $transaction): Transaction
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

it('links a referral code to a new signup at registration', function () {
    $referrer = User::factory()->create();
    $referral = Referral::create(['referrer_id' => $referrer->id, 'code' => 'JLX-TEST-CODE', 'status' => 'pending']);

    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'New Signup',
        'phone_number' => '08031112222',
        'email' => 'new-signup@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'referral_code' => 'JLX-TEST-CODE',
        'device_name' => 'test-device',
    ])->assertCreated();

    $newUser = User::where('email', 'new-signup@example.com')->firstOrFail();
    expect($referral->fresh())
        ->referred_user_id->toBe($newUser->id)
        ->status->toBe('qualified');
});

it('silently ignores an unknown referral code instead of failing registration', function () {
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'New Signup',
        'phone_number' => '08031113333',
        'email' => 'new-signup-2@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'referral_code' => 'NOT-A-REAL-CODE',
        'device_name' => 'test-device',
    ])->assertCreated();
});

it('rewards the referrer once the referred user completes their first delivered transaction', function () {
    $referrer = User::factory()->create();
    $referredUser = User::factory()->create();
    Referral::create([
        'referrer_id' => $referrer->id,
        'referred_user_id' => $referredUser->id,
        'code' => 'JLX-ABCD-1234',
        'status' => 'qualified',
    ]);
    $referrerWallet = $this->ledger->walletFor($referrer);

    $transaction = Transaction::factory()->create(['user_id' => $referredUser->id]);
    driveToDelivered($this->machine, $transaction);

    expect((float) $referrerWallet->fresh()->balance)->toBe((float) config('referrals.reward_amount'));
    expect(Referral::where('referred_user_id', $referredUser->id)->first())
        ->status->toBe('rewarded')
        ->reward_type->toBe('wallet_credit');
});

it('does not reward a referral twice for a second transaction', function () {
    $referrer = User::factory()->create();
    $referredUser = User::factory()->create();
    Referral::create([
        'referrer_id' => $referrer->id,
        'referred_user_id' => $referredUser->id,
        'code' => 'JLX-EFGH-5678',
        'status' => 'qualified',
    ]);
    $referrerWallet = $this->ledger->walletFor($referrer);

    driveToDelivered($this->machine, Transaction::factory()->create(['user_id' => $referredUser->id]));
    $balanceAfterFirst = (float) $referrerWallet->fresh()->balance;

    driveToDelivered($this->machine, Transaction::factory()->create(['user_id' => $referredUser->id]));

    expect((float) $referrerWallet->fresh()->balance)->toBe($balanceAfterFirst);
});
