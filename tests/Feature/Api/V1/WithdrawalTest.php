<?php

use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\PaymentProviderSetting;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'payments.domestic.driver' => 'paystack',
        'payments.paystack.secret_key' => 'sk_test_secret',
        'payments.paystack.public_key' => 'pk_test_public',
    ]);

    $this->user = User::factory()->create();
    $this->ledger = app(LedgerService::class);
    $this->wallet = $this->ledger->walletFor($this->user);
    $this->ledger->credit($this->wallet, '10000.00', LedgerReason::WalletFunding);

    Sanctum::actingAs($this->user);
});

it('lists banks from Paystack with a sandbox Test Bank prepended when using test keys', function () {
    Http::fake(['api.paystack.co/bank*' => Http::response([
        'status' => true,
        'data' => [
            ['name' => 'Guaranty Trust Bank', 'code' => '058'],
            ['name' => 'Access Bank', 'code' => '044'],
        ],
    ], 200)]);

    $this->getJson('/api/v1/withdrawals/banks')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.code', '001')
        ->assertJsonPath('data.1.code', '058');
});

it('does not add the sandbox Test Bank once live keys are configured', function () {
    config(['payments.paystack.secret_key' => 'sk_live_realsecret']);
    Http::fake(['api.paystack.co/bank*' => Http::response([
        'status' => true,
        'data' => [['name' => 'Guaranty Trust Bank', 'code' => '058']],
    ], 200)]);

    $this->getJson('/api/v1/withdrawals/banks')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', '058');
});

it('debits the wallet immediately and creates a pending withdrawal', function () {
    Http::fake([
        'api.paystack.co/bank/resolve*' => Http::response(['status' => true, 'data' => ['account_number' => '0022728151', 'account_name' => 'WES GIBBONS']], 200),
        'api.paystack.co/bank*' => Http::response(['status' => true, 'data' => [['name' => 'GTBank', 'code' => '058']]], 200),
        'api.paystack.co/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_abc123']], 200),
        'api.paystack.co/transfer' => Http::response(['status' => true, 'data' => ['transfer_code' => 'TRF_xyz', 'status' => 'pending']], 200),
    ]);

    $response = $this->postJson('/api/v1/withdrawals', [
        'amount' => '2000',
        'bank_code' => '058',
        'account_number' => '0022728151',
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'pending');

    expect((float) $this->wallet->fresh()->balance)->toBe(8000.0)
        ->and(Withdrawal::where('user_id', $this->user->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('rejects a withdrawal larger than the wallet balance without calling Paystack', function () {
    $this->postJson('/api/v1/withdrawals', [
        'amount' => '50000',
        'bank_code' => '058',
        'account_number' => '0022728151',
    ])->assertStatus(422);

    expect((float) $this->wallet->fresh()->balance)->toBe(10000.0);
    Http::assertNothingSent();
});

it('refunds the wallet when the account number cannot be resolved', function () {
    Http::fake(['api.paystack.co/bank/resolve*' => Http::response(['status' => false, 'message' => 'Could not resolve account name'], 400)]);

    $this->postJson('/api/v1/withdrawals', [
        'amount' => '2000',
        'bank_code' => '058',
        'account_number' => '0000000000',
    ])->assertStatus(422);

    expect((float) $this->wallet->fresh()->balance)->toBe(10000.0);
});

it('returns the payout provider rejection reason and restores the wallet', function () {
    Http::fake([
        'api.paystack.co/bank/resolve*' => Http::response(['status' => true, 'data' => ['account_number' => '0022728151', 'account_name' => 'WES GIBBONS']], 200),
        'api.paystack.co/bank*' => Http::response(['status' => true, 'data' => [['name' => 'GTBank', 'code' => '058']]], 200),
        'api.paystack.co/transferrecipient' => Http::response([
            'status' => false,
            'message' => 'Transfers are not enabled for this business.',
        ], 403),
    ]);

    $this->postJson('/api/v1/withdrawals', [
        'amount' => '2000',
        'bank_code' => '058',
        'account_number' => '0022728151',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'withdrawal_provider_rejected')
        ->assertJsonPath('message', 'Withdrawal could not be started. Paystack: Transfers are not enabled for this business. The held amount has been returned to your wallet.');

    $withdrawal = Withdrawal::latest()->firstOrFail();
    expect((float) $this->wallet->fresh()->balance)->toBe(10000.0)
        ->and($withdrawal->status)->toBe('failed')
        ->and($withdrawal->failure_reason)->toContain('Transfers are not enabled');
});

it('gets a fresh Safe Haven name enquiry session immediately before transfer', function () {
    PaymentProviderSetting::current()->update(['active_provider' => 'safehaven']);
    Cache::flush();

    $privateKey = openssl_pkey_new(['private_key_bits' => 1024]);
    openssl_pkey_export($privateKey, $privateKeyPem);
    config([
        'payments.safehaven.base_url' => 'https://safehaven.test',
        'payments.safehaven.oauth_client_id' => 'client-id',
        'payments.safehaven.ibs_client_id' => 'ibs-id',
        'payments.safehaven.company_url' => 'https://jolaxpay.test',
        'payments.safehaven.private_key' => $privateKeyPem,
        'payments.safehaven.debit_account_number' => '9999999999',
    ]);

    $nameEnquiries = 0;
    Http::fake(function (Request $request) use (&$nameEnquiries) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'safe-token'], 200);
        }

        if (str_ends_with($request->url(), '/transfers/name-enquiry')) {
            $nameEnquiries++;

            return Http::response(['data' => [
                'accountName' => 'SAFE HAVEN USER',
                'sessionId' => "SESSION-{$nameEnquiries}",
            ]], 200);
        }

        if (str_ends_with($request->url(), '/transfers/banks')) {
            return Http::response(['data' => [['name' => 'GTBank', 'bankCode' => '058']]], 200);
        }

        if (str_ends_with($request->url(), '/transfers')) {
            expect($request->data()['nameEnquiryReference'])->toBe('SESSION-2');

            return Http::response(['data' => ['_id' => 'safe-transfer-id', 'status' => 'Created']], 200);
        }

        return Http::response([], 404);
    });

    $this->postJson('/api/v1/withdrawals/resolve-account', [
        'bank_code' => '058',
        'account_number' => '0022728151',
    ])->assertOk()->assertJsonPath('data.account_name', 'SAFE HAVEN USER');

    $this->postJson('/api/v1/withdrawals', [
        'amount' => '2000',
        'bank_code' => '058',
        'account_number' => '0022728151',
    ])->assertCreated()->assertJsonPath('data.status', 'pending');

    expect($nameEnquiries)->toBe(2)
        ->and((float) $this->wallet->fresh()->balance)->toBe(8000.0);
});

it('marks the withdrawal successful and does not double-refund on a transfer.success webhook', function () {
    Http::fake([
        'api.paystack.co/bank/resolve*' => Http::response(['status' => true, 'data' => ['account_number' => '0022728151', 'account_name' => 'WES GIBBONS']], 200),
        'api.paystack.co/bank*' => Http::response(['status' => true, 'data' => [['name' => 'GTBank', 'code' => '058']]], 200),
        'api.paystack.co/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_abc123']], 200),
        'api.paystack.co/transfer' => Http::response(['status' => true, 'data' => ['transfer_code' => 'TRF_xyz', 'status' => 'pending']], 200),
    ]);

    $response = $this->postJson('/api/v1/withdrawals', [
        'amount' => '2000',
        'bank_code' => '058',
        'account_number' => '0022728151',
    ]);
    $reference = Withdrawal::find($response->json('data.id'))?->reference ?? Withdrawal::latest()->first()->reference;

    $payload = ['event' => 'transfer.success', 'data' => ['reference' => $reference]];
    $signature = hash_hmac('sha512', json_encode($payload), 'sk_test_secret');
    $this->postJson('/api/v1/webhooks/paystack', $payload, ['x-paystack-signature' => $signature])->assertOk();

    expect(Withdrawal::where('reference', $reference)->first()->status)->toBe('success')
        ->and((float) $this->wallet->fresh()->balance)->toBe(8000.0); // still debited once, not refunded
});

it('refunds the wallet when a transfer.failed webhook arrives', function () {
    Http::fake([
        'api.paystack.co/bank/resolve*' => Http::response(['status' => true, 'data' => ['account_number' => '0022728151', 'account_name' => 'WES GIBBONS']], 200),
        'api.paystack.co/bank*' => Http::response(['status' => true, 'data' => [['name' => 'GTBank', 'code' => '058']]], 200),
        'api.paystack.co/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_abc123']], 200),
        'api.paystack.co/transfer' => Http::response(['status' => true, 'data' => ['transfer_code' => 'TRF_xyz', 'status' => 'pending']], 200),
    ]);

    $this->postJson('/api/v1/withdrawals', [
        'amount' => '2000',
        'bank_code' => '058',
        'account_number' => '0022728151',
    ]);
    $reference = Withdrawal::latest()->first()->reference;

    $payload = ['event' => 'transfer.failed', 'data' => ['reference' => $reference, 'message' => 'Insufficient balance on Paystack side']];
    $signature = hash_hmac('sha512', json_encode($payload), 'sk_test_secret');
    $this->postJson('/api/v1/webhooks/paystack', $payload, ['x-paystack-signature' => $signature])->assertOk();

    expect(Withdrawal::where('reference', $reference)->first()->status)->toBe('failed')
        ->and((float) $this->wallet->fresh()->balance)->toBe(10000.0); // refunded back
});
