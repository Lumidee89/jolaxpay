<?php

use App\Models\PaymentProviderSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    config([
        'payments.paystack.secret_key' => 'sk_test_ready',
        'payments.paystack.callback_url' => 'https://example.test/paystack/callback',
        'payments.safehaven.oauth_client_id' => 'safe-client',
        'payments.safehaven.private_key' => '/tmp/test.pem',
        'payments.safehaven.debit_account_number' => '1234567890',
    ]);
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets provider managers switch the active payment provider', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)->get(route('admin.payment-provider.edit'))->assertOk();
    $this->patch(route('admin.payment-provider.update'), ['active_provider' => 'paystack'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(PaymentProviderSetting::current()->active_provider)->toBe('paystack')
        ->and(PaymentProviderSetting::current()->updated_by)->toBe($admin->id);

    $this->patch(route('admin.payment-provider.update'), ['active_provider' => 'safehaven'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(PaymentProviderSetting::current()->active_provider)->toBe('safehaven');
});

it('rejects unsupported providers and unauthorized staff', function () {
    $support = User::factory()->create();
    $support->assignRole('support');
    $this->actingAs($support)->get(route('admin.payment-provider.edit'))->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin)->patch(route('admin.payment-provider.update'), ['active_provider' => 'other'])
        ->assertSessionHasErrors('active_provider');
});
