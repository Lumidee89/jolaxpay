<?php

namespace App\Domain\Vending;

use App\Domain\Vending\Contracts\BillerVendingProviderContract;
use App\Domain\Vending\Contracts\VendingProviderContract;
use App\Domain\Vending\Providers\MockBillerProvider;
use App\Domain\Vending\Providers\MockElectricityProvider;
use App\Domain\Vending\Providers\VtpassBillerProvider;
use App\Domain\Vending\Providers\VtpassElectricityProvider;
use App\Enums\ServiceType;
use InvalidArgumentException;

/**
 * Resolves the active vending driver per service type (config-driven, so a
 * failover provider is a config change, not a deploy — TRD §5). Electricity
 * is meter-anchored and returns VendingProviderContract; every other
 * service type is biller-anchored (ServiceType::isBillerBased()) and
 * returns BillerVendingProviderContract instead — see App\Models\Biller.
 */
class VendingManager
{
    /**
     * Generic resolver for TransactionService::processVending(), which
     * only ever calls the common `vend(Transaction): VendResult` shape
     * both contracts declare. Callers that need verify()/fetchVariations()
     * should use electricityDriverFor()/billerDriverFor() instead, since
     * those methods aren't on both interfaces.
     */
    public function driverFor(ServiceType $serviceType): VendingProviderContract|BillerVendingProviderContract
    {
        return $serviceType->isBillerBased()
            ? $this->billerDriverFor($serviceType)
            : $this->electricityDriverFor();
    }

    public function electricityDriverFor(): VendingProviderContract
    {
        return match (config('vending.electricity.driver')) {
            'mock' => app(MockElectricityProvider::class),
            'vtpass' => app(VtpassElectricityProvider::class),
            $driver => throw new InvalidArgumentException("Unknown electricity vending driver [{$driver}]."),
        };
    }

    public function billerDriverFor(ServiceType $serviceType): BillerVendingProviderContract
    {
        $driver = config("vending.{$serviceType->value}.driver");

        return match ($driver) {
            'mock' => app(MockBillerProvider::class),
            'vtpass' => app(VtpassBillerProvider::class),
            default => throw new InvalidArgumentException("Unknown vending driver [{$driver}] for [{$serviceType->value}]."),
        };
    }
}
