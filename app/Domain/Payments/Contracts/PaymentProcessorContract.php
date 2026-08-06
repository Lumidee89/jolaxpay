<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DataTransferObjects\PaymentResult;
use App\Models\Transaction;

/**
 * PCI-compliant processor abstraction (TRD §5, §7): the app never handles
 * raw card data — a driver wraps a processor SDK/API and returns a
 * normalised result.
 */
interface PaymentProcessorContract
{
    public function charge(Transaction $transaction): PaymentResult;
}
