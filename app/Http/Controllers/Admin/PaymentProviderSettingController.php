<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentProviderSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/PaymentProvider/Edit', [
            'activeProvider' => PaymentProviderSetting::current()->active_provider,
            'configuredProviders' => [
                'safehaven' => $this->configured('safehaven'),
                'paystack' => $this->configured('paystack'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['active_provider' => ['required', 'in:paystack,safehaven']]);
        if (! $this->configured($data['active_provider'])) {
            return back()->withErrors(['active_provider' => ucfirst($data['active_provider']).' credentials are incomplete. Configure the provider in .env before switching.']);
        }
        PaymentProviderSetting::current()->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', ucfirst($data['active_provider']).' is now active for wallet funding, direct payments and bank withdrawals.');
    }

    private function configured(string $provider): bool
    {
        return $provider === 'paystack'
            ? filled(config('payments.paystack.secret_key')) && filled(config('payments.paystack.callback_url'))
            : filled(config('payments.safehaven.oauth_client_id'))
                && filled(config('payments.safehaven.private_key'))
                && filled(config('payments.safehaven.debit_account_number'));
    }
}
