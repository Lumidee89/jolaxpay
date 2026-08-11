<?php

use App\Domain\Transactions\TransactionStateMachine;
use App\Enums\TransactionStatus;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Referral;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->staff = User::factory()->create();
    $this->staff->assignRole('ops');
});

it('shows product analytics to staff with view-dashboard access', function () {
    $machine = app(TransactionStateMachine::class);
    $user = User::factory()->create();
    $disco = Disco::factory()->create();
    $meter = Meter::factory()->for($user)->for($disco)->create();
    $transaction = Transaction::factory()->for($user)->for($meter)->create();
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

    $referrer = User::factory()->create();
    Referral::create(['referrer_id' => $referrer->id, 'referred_user_id' => $user->id, 'code' => 'JLX-A1B2', 'status' => 'rewarded']);
    SupportTicket::factory()->create(['category' => 'billing']);
    SupportTicket::factory()->create(['category' => 'billing']);
    SupportTicket::factory()->create(['category' => 'technical']);

    $response = $this->actingAs($this->staff)->get(route('admin.analytics.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Analytics/Index')
        ->where('activation.activated_users', 1)
        ->where('electricityFlow.completed', 1)
        ->where('referralConversion.linked', 1)
        ->where('referralConversion.rewarded', 1)
        ->has('ticketsByCategory', 2)
    );
});

it('records and aggregates AI insight engagement pings', function () {
    $user = User::factory()->create();
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $this->postJson('/api/v1/insights/engagement', ['insight_type' => 'low_balance', 'action' => 'shown'])->assertCreated();
    $this->postJson('/api/v1/insights/engagement', ['insight_type' => 'low_balance', 'action' => 'clicked'])->assertCreated();

    $response = $this->actingAs($this->staff)->get(route('admin.analytics.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('aiInsightEngagement.shown', 1)
        ->where('aiInsightEngagement.clicked', 1)
        ->where('aiInsightEngagement.click_through_rate', 100)
    );
});
