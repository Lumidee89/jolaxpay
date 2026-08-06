<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentProcessorContract;
use App\Domain\Payments\Providers\MockPaymentProcessor;
use App\Models\Transaction;
use InvalidArgumentException;

/**
 * Resolves domestic vs. international processor by currency — a
 * Diaspora Mode transaction (non-NGN) always routes to the
 * 'international' driver (TRD §2.2, §9).
 */
class PaymentManager
{
    public function driverFor(Transaction $transaction): PaymentProcessorContract
    {
        $isDomestic = $transaction->currency === config('payments.domestic_currency', 'NGN');
        $driver = $isDomestic
            ? config('payments.domestic.driver')
            : config('payments.international.driver');

        return $this->resolve($driver);
    }

    protected function resolve(string $driver): PaymentProcessorContract
    {
        return match ($driver) {
            'mock' => app(MockPaymentProcessor::class),
            default => throw new InvalidArgumentException("Unknown payment driver [{$driver}]."),
        };
    }
}
