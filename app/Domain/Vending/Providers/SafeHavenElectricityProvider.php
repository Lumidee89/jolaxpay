<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Payments\SafeHavenGateway;
use App\Domain\Vending\Contracts\VendingProviderContract;
use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Meter;
use App\Models\Transaction;

/** Direct electricity verification and payment through Safe Haven VAS. */
class SafeHavenElectricityProvider implements VendingProviderContract
{
    public function __construct(private readonly SafeHavenGateway $safeHaven) {}

    public function verifyMeter(Meter $meter): MeterVerificationResult
    {
        $category = $meter->disco?->api_provider_code;
        if (! $category) return new MeterVerificationResult(false, message: 'This provider has no Safe Haven service category configured.');
        $data = $this->safeHaven->verifyUtility($category, $meter->meter_number);
        if (! $data) return new MeterVerificationResult(false, message: 'Could not verify this meter with Safe Haven.');

        return new MeterVerificationResult(
            valid: true,
            customerName: $data['customerName'] ?? $data['name'] ?? null,
            address: $data['address'] ?? null,
            minimumAmount: isset($data['minimumAmount']) ? (string) $data['minimumAmount'] : null,
            message: 'Meter verified.', raw: $data,
        );
    }

    public function vend(Transaction $transaction): VendResult
    {
        $meter = $transaction->meter;
        $category = $meter?->disco?->api_provider_code;
        if (! $meter || ! $category) return new VendResult(false, message: 'Safe Haven provider category is not configured.');
        $verification = $this->safeHaven->verifyUtility($category, $meter->meter_number);
        $vendType = $verification['vendType'] ?? $verification['type'] ?? $meter->meter_type;
        $data = $this->safeHaven->payUtility($category, $meter->meter_number, $vendType, (float) $transaction->amount, $transaction->reference);
        if (! $data) return new VendResult(false, message: 'Safe Haven could not complete the electricity payment.');

        $status = strtolower((string) ($data['status'] ?? 'completed'));
        $token = $data['token'] ?? $data['vendToken'] ?? $data['meterToken'] ?? null;
        return new VendResult(
            successful: in_array($status, ['completed', 'successful', 'success'], true),
            token: $token,
            providerReference: $data['paymentReference'] ?? $data['_id'] ?? $transaction->reference,
            message: $data['responseMessage'] ?? $data['message'] ?? null,
            raw: $data,
        );
    }

    public function healthCheck(): bool
    {
        return $this->safeHaven->listBanks() !== [];
    }
}
