<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Payments\Contracts\PaymentProcessorContract;
use App\Domain\Payments\DataTransferObjects\PaymentResult;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Stub processor so checkout is fully testable before a live
 * Flutterwave/Paystack/Stripe integration exists. Opt into failure via
 * `transaction.meta.simulate_payment_failure`.
 */
class MockPaymentProcessor implements PaymentProcessorContract
{
    public function charge(Transaction $transaction): PaymentResult
    {
        if ($transaction->payment_method === 'wallet') {
            // Wallet debits are handled by LedgerService, not a processor call.
            return new PaymentResult(successful: true, processorReference: 'WALLET');
        }

        if (($transaction->meta['simulate_payment_failure'] ?? false) === true) {
            return new PaymentResult(successful: false, message: 'Mock processor: card declined.');
        }

        return new PaymentResult(
            successful: true,
            processorReference: 'MOCK-PAY-'.Str::upper(Str::random(12)),
            message: 'Mock processor: payment captured.',
        );
    }
}
