<?php

use App\Models\PaymentProviderSetting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the current payment route immediately without client restart or response caching', function () {
    Sanctum::actingAs(User::factory()->create());

    PaymentProviderSetting::current()->update(['active_provider' => 'paystack']);
    $paystackResponse = $this->getJson('/api/v1/payment-provider')
        ->assertOk()
        ->assertJsonPath('data.provider', 'paystack')
        ->assertJsonPath('data.wallet_funding_methods.0', 'card');
    expect($paystackResponse->headers->get('Cache-Control'))->toContain('no-store');

    PaymentProviderSetting::current()->update(['active_provider' => 'safehaven']);
    $this->getJson('/api/v1/payment-provider')
        ->assertOk()
        ->assertJsonPath('data.provider', 'safehaven')
        ->assertJsonPath('data.wallet_funding_methods.0', 'bank_transfer');
});
