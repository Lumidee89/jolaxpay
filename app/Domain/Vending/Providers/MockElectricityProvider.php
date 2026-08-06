<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Vending\Contracts\VendingProviderContract;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Stub provider used until a live DisCo integration lands (Implementation
 * Plan §2: "one live DisCo integration + a stub/mock provider for the
 * rest, for parallel frontend development"). Deterministic unless the
 * caller opts into failure via `transaction.meta.simulate_failure`, so
 * both the happy path and the retry/refund path are testable on demand.
 */
class MockElectricityProvider implements VendingProviderContract
{
    public function vend(Transaction $transaction): VendResult
    {
        if (($transaction->meta['simulate_failure'] ?? false) === true) {
            return new VendResult(
                successful: false,
                message: 'Mock provider: simulated vending failure.',
            );
        }

        $token = collect(range(1, 4))
            ->map(fn () => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT))
            ->implode('-');

        return new VendResult(
            successful: true,
            token: $token,
            providerReference: 'MOCK-'.Str::upper(Str::random(10)),
            message: 'Mock provider: token generated.',
        );
    }

    public function healthCheck(): bool
    {
        return true;
    }
}
