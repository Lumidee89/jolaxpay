<?php

use App\Domain\Referrals\CommissionService;
use App\Domain\Transactions\TransactionStateMachine;
use App\Enums\TransactionStatus;
use App\Models\AgentCommission;
use App\Models\CommissionRule;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->machine = app(TransactionStateMachine::class);
    $this->commissions = app(CommissionService::class);
});

function driveAgentTransactionToDelivered(TransactionStateMachine $machine, Transaction $transaction): Transaction
{
    foreach ([TransactionStatus::PaymentInitiated, TransactionStatus::PaymentReceived, TransactionStatus::PaymentConfirmed, TransactionStatus::GeneratingToken, TransactionStatus::TokenGenerated, TransactionStatus::Delivered] as $next) {
        $machine->transition($transaction, $next);
    }

    return $transaction->fresh();
}

function approvedAgent(array $attributes = []): User
{
    return User::factory()->create([...$attributes, 'account_type' => 'agent', 'agent_approved_at' => now(), 'referral_code' => $attributes['referral_code'] ?? 'JLX-AGENT-'.fake()->unique()->numerify('####')]);
}

it('automatically gives a registered Agent a reusable referral code', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Approved Agent', 'phone_number' => '08031110001', 'email' => 'agent@example.com',
        'password' => 'Password1', 'password_confirmation' => 'Password1', 'account_type' => 'agent', 'device_name' => 'agent-phone',
    ])->assertCreated()->assertJsonPath('user.account_type', 'agent');

    expect(User::find($response->json('user.id'))->referral_code)->not->toBeNull();
});

it('immutably attributes a Personal signup to the Agent code', function () {
    $agent = approvedAgent(['referral_code' => 'JLX-TEST-CODE']);
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'New Personal User', 'phone_number' => '08031112222', 'email' => 'personal@example.com',
        'password' => 'Password1', 'password_confirmation' => 'Password1', 'referral_code' => 'JLX-TEST-CODE', 'device_name' => 'test-device',
    ])->assertCreated();

    $personal = User::where('email', 'personal@example.com')->firstOrFail();
    expect(Referral::where('referred_user_id', $personal->id)->first())
        ->referrer_id->toBe($agent->id)->code->toBe('JLX-TEST-CODE')->status->toBe('qualified');
});

it('pays recurring referral commissions only after successful transactions and activates the referral', function () {
    $agent = approvedAgent();
    $personal = User::factory()->create(['account_type' => 'individual']);
    Referral::create(['referrer_id' => $agent->id, 'referred_user_id' => $personal->id, 'code' => $agent->referral_code, 'status' => 'qualified', 'attributed_at' => now()]);
    CommissionRule::create(['name' => 'Referral electricity', 'earning_type' => 'referral', 'service_type' => 'electricity', 'calculation_type' => 'fixed', 'value' => 50, 'is_active' => true]);

    $first = driveAgentTransactionToDelivered($this->machine, Transaction::factory()->create(['user_id' => $personal->id]));
    $second = driveAgentTransactionToDelivered($this->machine, Transaction::factory()->create(['user_id' => $personal->id]));

    expect(AgentCommission::where('agent_id', $agent->id)->where('earning_type', 'referral')->count())->toBe(2)
        ->and((float) AgentCommission::where('agent_id', $agent->id)->sum('amount'))->toBe(100.0)
        ->and(Referral::where('referred_user_id', $personal->id)->first()->activated_at)->not->toBeNull();
    $this->commissions->accrue($first);
    expect(AgentCommission::where('transaction_id', $first->id)->where('earning_type', 'referral')->count())->toBe(1);
});

it('pays direct commission for an Agent own successful sale', function () {
    $agent = approvedAgent();
    CommissionRule::create(['name' => 'Direct electricity', 'earning_type' => 'direct', 'service_type' => 'electricity', 'calculation_type' => 'percentage', 'value' => 2, 'is_active' => true]);
    $transaction = driveAgentTransactionToDelivered($this->machine, Transaction::factory()->create(['user_id' => $agent->id, 'amount' => '2000.00']));

    expect(AgentCommission::where('transaction_id', $transaction->id)->where('earning_type', 'direct')->first())
        ->amount->toBe('40.00')->status->toBe('available');
});

it('does not create second-level commission and records reversals', function () {
    $agent = approvedAgent();
    $personal = User::factory()->create();
    $secondLevel = User::factory()->create();
    Referral::create(['referrer_id' => $agent->id, 'referred_user_id' => $personal->id, 'code' => $agent->referral_code, 'status' => 'qualified', 'attributed_at' => now()]);
    CommissionRule::create(['name' => 'Referral all', 'earning_type' => 'referral', 'calculation_type' => 'fixed', 'value' => 25, 'is_active' => true]);

    driveAgentTransactionToDelivered($this->machine, Transaction::factory()->create(['user_id' => $secondLevel->id]));
    expect(AgentCommission::where('agent_id', $agent->id)->count())->toBe(0);

    $transaction = driveAgentTransactionToDelivered($this->machine, Transaction::factory()->create(['user_id' => $personal->id]));
    $this->commissions->reverseFor($transaction, 'Test refund');
    expect(AgentCommission::where('transaction_id', $transaction->id)->where('earning_type', 'referral')->first()->status)->toBe('reversed')
        ->and((float) AgentCommission::where('transaction_id', $transaction->id)->where('earning_type', 'referral_reversal')->first()->amount)->toBe(-25.0);
});
