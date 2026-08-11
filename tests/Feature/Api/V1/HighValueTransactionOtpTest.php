<?php

use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\LedgerReason;
use App\Enums\OtpPurpose;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Otp;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/**
 * PRD §15: a purchase at/above config('identity.high_value_threshold')
 * needs a verified OTP step-up before it's allowed through
 * TransactionService::initiate() — see TransactionController::store().
 */
beforeEach(function () {
    config(['identity.high_value_threshold' => 50000]);

    $this->user = User::factory()->create();
    $this->disco = Disco::factory()->create();
    $this->meter = Meter::factory()->for($this->user)->for($this->disco)->create();

    $ledger = app(LedgerService::class);
    $wallet = $ledger->walletFor($this->user);
    $ledger->credit($wallet, '200000.00', LedgerReason::WalletFunding);

    Sanctum::actingAs($this->user);
});

it('challenges a high-value purchase with an OTP instead of creating the transaction', function () {
    $response = $this->withHeader('Idempotency-Key', 'hv-key-1')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '60000',
            'payment_method' => 'wallet',
        ]);

    $response->assertStatus(428)->assertJsonPath('requires_otp', true)
        ->assertJsonPath('purpose', OtpPurpose::HighValueTransaction->value);

    expect(Otp::where('identifier', $this->user->phone_number)->where('purpose', OtpPurpose::HighValueTransaction)->exists())->toBeTrue()
        ->and(Transaction::count())->toBe(0);
});

it('lets a high-value purchase through once the correct otp_code is supplied', function () {
    $code = '482913';
    Otp::create([
        'user_id' => $this->user->id,
        'identifier' => $this->user->phone_number,
        'channel' => DeliveryChannel::Sms,
        'purpose' => OtpPurpose::HighValueTransaction,
        'code_hash' => Hash::make($code),
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->withHeader('Idempotency-Key', 'hv-key-2')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '60000',
            'payment_method' => 'wallet',
            'otp_code' => $code,
        ]);

    $response->assertStatus(202);
    expect(Transaction::count())->toBe(1);
});

it('rejects an incorrect otp_code with a 422 and does not create the transaction', function () {
    Otp::create([
        'user_id' => $this->user->id,
        'identifier' => $this->user->phone_number,
        'channel' => DeliveryChannel::Sms,
        'purpose' => OtpPurpose::HighValueTransaction,
        'code_hash' => Hash::make('111111'),
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->withHeader('Idempotency-Key', 'hv-key-3')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '60000',
            'payment_method' => 'wallet',
            'otp_code' => '000000',
        ]);

    $response->assertStatus(422)->assertJsonPath('message', 'That code is invalid or has expired.');
    expect(Transaction::count())->toBe(0);
});

it('does not challenge a purchase below the high-value threshold', function () {
    $this->withHeader('Idempotency-Key', 'hv-key-4')
        ->postJson('/api/v1/transactions', [
            'meter_id' => $this->meter->id,
            'amount' => '5000',
            'payment_method' => 'wallet',
        ])->assertStatus(202);
});
