<?php

namespace App\Domain\Vending\Contracts;

use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Meter;
use App\Models\Transaction;

/**
 * Provider-abstraction interface (TRD §5): a failing DisCo/telecom
 * provider can be swapped for a failover implementation without
 * user-facing disruption. Every concrete driver (mock or live) implements
 * this and nothing else in the app talks to a provider SDK directly.
 */
interface VendingProviderContract
{
    public function vend(Transaction $transaction): VendResult;

    /** Pre-purchase meter/customer lookup — shown before the user pays. */
    public function verifyMeter(Meter $meter): MeterVerificationResult;

    /** Cheap reachability check feeding the Provider Health Dashboard. */
    public function healthCheck(): bool;
}
