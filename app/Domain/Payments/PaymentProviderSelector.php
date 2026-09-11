<?php

namespace App\Domain\Payments;

use App\Models\PaymentProviderSetting;

class PaymentProviderSelector
{
    public function active(): string
    {
        return PaymentProviderSetting::current()->active_provider;
    }

    public function is(string $provider): bool
    {
        return $this->active() === $provider;
    }
}
