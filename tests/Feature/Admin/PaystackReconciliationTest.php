<?php

use App\Models\User;
use App\Models\WalletFundingIntent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config([
        'payments.paystack.secret_key' => 'sk_test_secret',
        'payments.paystack.base_url' => 'https://api.paystack.co',
    ]);
});

it('allows reconciliation staff to recover a successful Paystack wallet funding', function () {
    $admin = User::factory()->create();
    $admin->assignRole('ops');
    $customer = User::factory()->create();
    $wallet = app(\App\Domain\Wallet\LedgerService::class)->walletFor($customer);
    $intent = WalletFundingIntent::factory()->for($customer)->for($wallet)->create([
        'reference' => 'fund-admin-recovery',
        'amount' => '2500.00',
        'meta' => ['provider' => 'paystack'],
    ]);

    Http::fake([
        'https://api.paystack.co/transaction/verify/fund-admin-recovery' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 250000, 'reference' => 'fund-admin-recovery'],
        ]),
    ]);

    $this->actingAs($admin)->post('/admin/reconcile-paystack')
        ->assertRedirect()
        ->assertSessionHas('success', 'Checked 1 pending Paystack payment(s); 1 wallet(s) credited.');

    expect($intent->fresh()->status)->toBe('success')
        ->and($wallet->fresh()->balance)->toBe('2500.00');
});

it('blocks support staff from running Paystack reconciliation', function () {
    $support = User::factory()->create();
    $support->assignRole('support');

    $this->actingAs($support)->post('/admin/reconcile-paystack')->assertForbidden();
});

it('shows the pending Paystack funding count on the dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    WalletFundingIntent::factory()->create(['status' => 'pending', 'meta' => ['provider' => 'paystack']]);
    WalletFundingIntent::factory()->create(['status' => 'pending', 'meta' => ['provider' => 'safehaven']]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('stats.pending_paystack_fundings', 1)
    );
});
