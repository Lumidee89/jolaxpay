<?php

namespace App\Domain\Vending\Contracts;

use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Biller;
use App\Models\Transaction;

/**
 * Provider-abstraction interface for every non-electricity service
 * (airtime, data, cable_tv, education — App\Enums\ServiceType::isBillerBased()).
 * Parallels VendingProviderContract, but keyed on a generic Biller +
 * identifier instead of a saved Meter, since none of these services have
 * a meter-shaped saved record.
 */
interface BillerVendingProviderContract
{
    public function vend(Transaction $transaction): VendResult;

    /** Pre-purchase account lookup (smartcard/profile ID) — not every biller supports this, see Biller::supports_verify. */
    public function verify(Biller $biller, string $identifier, ?string $variationCode = null): MeterVerificationResult;

    /**
     * Fetches the current bundle/bouquet/pin-type options for a biller
     * that needs one, for App\Console\Commands\SyncBillerVariations to
     * cache into `biller_variations`.
     *
     * @return array<int, array{variation_code: string, name: string, amount: string, fixed_price: bool}>
     */
    public function fetchVariations(Biller $biller): array;

    /** Cheap reachability check feeding the Provider Health Dashboard. */
    public function healthCheck(): bool;
}
