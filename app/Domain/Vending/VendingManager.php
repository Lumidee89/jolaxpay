<?php

namespace App\Domain\Vending;

use App\Domain\Vending\Contracts\VendingProviderContract;
use App\Domain\Vending\Providers\MockElectricityProvider;
use App\Enums\ServiceType;
use InvalidArgumentException;

/**
 * Resolves the active VendingProviderContract driver per service type
 * (config-driven, so a failover provider is a config change, not a
 * deploy — TRD §5).
 */
class VendingManager
{
    public function driverFor(ServiceType $serviceType): VendingProviderContract
    {
        return match ($serviceType) {
            ServiceType::Electricity => $this->resolve(config('vending.electricity.driver')),
            default => throw new InvalidArgumentException("No vending driver configured for [{$serviceType->value}] yet."),
        };
    }

    protected function resolve(string $driver): VendingProviderContract
    {
        return match ($driver) {
            'mock' => app(MockElectricityProvider::class),
            default => throw new InvalidArgumentException("Unknown vending driver [{$driver}]."),
        };
    }
}
