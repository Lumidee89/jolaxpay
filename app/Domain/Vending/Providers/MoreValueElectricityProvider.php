<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Vending\Contracts\VendingProviderContract;
use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Meter;
use App\Models\Transaction;

/** Prepaid and postpaid electricity vending through MoreValue Digital. */
class MoreValueElectricityProvider extends MoreValueClient implements VendingProviderContract
{
    public function vend(Transaction $transaction): VendResult
    {
        $meter = $transaction->meter;
        if (! $meter || ! $meter->disco?->api_provider_code) {
            return new VendResult(successful: false, message: 'This electricity provider has not been configured for MoreValue Digital.');
        }

        $response = $this->post('/electricity/', [
            'provider' => (string) $meter->disco->api_provider_code,
            'meternumber' => $meter->meter_number,
            'metertype' => strtolower($meter->meter_type),
            'amount' => (float) $transaction->amount,
            'phone' => $transaction->recipient_phone ?: $transaction->user->phone_number,
            'ref' => $transaction->reference,
        ]);

        if ($response === null || ! $this->succeeded($response)) {
            return new VendResult(
                successful: false,
                message: $this->message($response, 'MoreValue Digital could not complete this electricity payment.'),
                raw: ['morevalue_reference' => $transaction->reference],
            );
        }

        return new VendResult(
            successful: true,
            token: isset($response['token']) ? (string) $response['token'] : null,
            providerReference: (string) ($response['reference'] ?? $response['ref'] ?? $response['transaction_id'] ?? $transaction->reference),
            message: $this->message($response, 'Electricity payment successful.'),
            raw: [
                'morevalue_reference' => $transaction->reference,
                'units' => $response['units'] ?? null,
                'morevalue_response' => $response,
            ],
        );
    }

    public function verifyMeter(Meter $meter): MeterVerificationResult
    {
        if (! $meter->disco?->api_provider_code) {
            return new MeterVerificationResult(valid: false, message: 'This electricity provider has not been configured for MoreValue Digital.');
        }

        $response = $this->post('/electricity/verify/', [
            'provider' => (string) $meter->disco->api_provider_code,
            'meternumber' => $meter->meter_number,
            'metertype' => strtolower($meter->meter_type),
        ]);

        if ($response === null || ! $this->succeeded($response)) {
            return new MeterVerificationResult(
                valid: false,
                message: $this->message($response, 'This meter number could not be verified.'),
                raw: $response ?? [],
            );
        }

        return new MeterVerificationResult(
            valid: true,
            customerName: $response['customer_name'] ?? $response['name'] ?? null,
            address: $response['address'] ?? null,
            minimumAmount: isset($response['minimum_amount']) ? (string) $response['minimum_amount'] : null,
            message: 'Meter verified.',
            raw: $response,
        );
    }
}
