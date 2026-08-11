<?php

use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Models\Disco;
use App\Models\FraudFlag;
use App\Models\Meter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * PRD §15 rules-based fraud checks (FraudCheckService, called from
 * TransactionService::initiate()). QUEUE_CONNECTION=sync in tests, so
 * every purchase below runs the full pipeline to 'delivered' inline.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->disco = Disco::factory()->create();
    $this->meter = Meter::factory()->for($this->user)->for($this->disco)->create();

    $ledger = app(LedgerService::class);
    $wallet = $ledger->walletFor($this->user);
    $ledger->credit($wallet, '1000000.00', LedgerReason::WalletFunding);

    Sanctum::actingAs($this->user);

    $this->buy = fn (string $amount, string $key) => $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => $amount,
            'payment_method' => 'wallet',
        ]);
});

it('does not flag an ordinary single purchase', function () {
    ($this->buy)('5000', 'fraud-key-normal');

    expect(FraudFlag::count())->toBe(0);
});

it('flags a burst of purchases that exceeds the velocity threshold', function () {
    config(['fraud.velocity.max_count' => 2, 'fraud.velocity.window_minutes' => 10]);

    ($this->buy)('5000', 'fraud-key-v1');
    ($this->buy)('5000', 'fraud-key-v2');
    ($this->buy)('5000', 'fraud-key-v3');

    $flag = FraudFlag::where('rule', 'velocity')->first();
    expect($flag)->not->toBeNull()
        ->and($flag->user_id)->toBe($this->user->id)
        ->and($flag->status)->toBe('open');
});

it("flags a purchase far above the user's own historical average", function () {
    config([
        'fraud.velocity.max_count' => 100,
        'fraud.unusual_amount.sample_size' => 3,
        'fraud.unusual_amount.multiplier' => 5,
        // Otherwise the ₦100,000 purchase below would hit the high-value
        // OTP challenge (identity.high_value_threshold) and never reach
        // TransactionService::initiate() at all — not what this is testing.
        'identity.high_value_threshold' => 1000000,
    ]);

    ($this->buy)('5000', 'fraud-key-h1');
    ($this->buy)('5000', 'fraud-key-h2');
    ($this->buy)('5000', 'fraud-key-h3');
    ($this->buy)('100000', 'fraud-key-h4');

    $flag = FraudFlag::where('rule', 'unusual_amount')->first();
    expect($flag)->not->toBeNull()
        ->and((float) $flag->meta['average'])->toBe(5000.0);
});

it("flags a brand-new user's purchase above the new-account ceiling", function () {
    config(['fraud.velocity.max_count' => 100, 'fraud.unusual_amount.new_user_ceiling' => 10000]);

    ($this->buy)('20000', 'fraud-key-new');

    $flag = FraudFlag::where('rule', 'unusual_amount')->first();
    expect($flag)->not->toBeNull()
        ->and((float) $flag->meta['ceiling'])->toBe(10000.0);
});

it('lets staff review and dismiss a flag from the admin panel', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $flag = FraudFlag::create(['user_id' => $this->user->id, 'rule' => 'velocity', 'status' => 'open']);

    $staff = User::factory()->create();
    $staff->assignRole('super_admin');

    $this->actingAs($staff)->post(route('admin.fraud.dismiss', $flag->id))->assertRedirect();

    expect($flag->fresh()->status)->toBe('dismissed');
});
