<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Vending\Contracts\BillerVendingProviderContract;
use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Biller;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Stub provider for airtime/data/cable_tv/education, the biller-anchored
 * counterpart to MockElectricityProvider — same "deterministic unless
 * `transaction.meta.simulate_failure`" contract, so the retry/refund path
 * is exercisable for every service type without VTpass credentials.
 */
class MockBillerProvider implements BillerVendingProviderContract
{
    public function vend(Transaction $transaction): VendResult
    {
        if (($transaction->meta['simulate_failure'] ?? false) === true) {
            return new VendResult(
                successful: false,
                message: 'Mock provider: simulated vending failure.',
            );
        }

        // Airtime/data/cable_tv recharges have nothing to hand back — the
        // recharge itself is the delivery. Education pins do.
        $token = $transaction->service_type?->value === 'education'
            ? 'PIN-'.collect(range(1, 3))->map(fn () => random_int(1000, 9999))->implode('-')
            : null;

        return new VendResult(
            successful: true,
            token: $token,
            providerReference: 'MOCK-'.Str::upper(Str::random(10)),
            message: 'Mock provider: purchase successful.',
        );
    }

    public function verify(Biller $biller, string $identifier, ?string $variationCode = null): MeterVerificationResult
    {
        return new MeterVerificationResult(
            valid: true,
            customerName: 'Mock Customer ('.$identifier.')',
            message: 'Mock provider: account verified.',
        );
    }

    public function fetchVariations(Biller $biller): array
    {
        return [
            ['variation_code' => 'mock-small', 'name' => 'Mock small bundle', 'amount' => '500.00', 'fixed_price' => true],
            ['variation_code' => 'mock-large', 'name' => 'Mock large bundle', 'amount' => '2000.00', 'fixed_price' => true],
        ];
    }

    public function healthCheck(): bool
    {
        return true;
    }
}
