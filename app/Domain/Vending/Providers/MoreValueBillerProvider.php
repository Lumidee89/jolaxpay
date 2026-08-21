<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Vending\Contracts\BillerVendingProviderContract;
use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Biller;
use App\Models\Transaction;

/** Airtime, data, and cable vending through MoreValue Digital. */
class MoreValueBillerProvider extends MoreValueClient implements BillerVendingProviderContract
{
    public function vend(Transaction $transaction): VendResult
    {
        $biller = $transaction->biller;
        if (! $biller || ! $biller->api_provider_code) {
            return new VendResult(successful: false, message: 'This provider has not been configured for MoreValue Digital.');
        }

        $phone = $transaction->biller_identifier
            ?: ($transaction->recipient_phone ?: $transaction->user->phone_number);
        $reference = $transaction->reference;

        [$path, $payload] = match ($biller->service_type) {
            'airtime' => ['/airtime/', [
                'network' => (string) $biller->api_provider_code,
                'amount' => (float) $transaction->amount,
                'phone' => $phone,
                'ref' => $reference,
            ]],
            'data' => ['/data/', [
                'network' => (string) $biller->api_provider_code,
                'plan' => (string) $transaction->variation_code,
                'phone' => $phone,
                'ref' => $reference,
            ]],
            'cable_tv' => ['/cabletv/', [
                'provider' => (string) $biller->api_provider_code,
                'iucnumber' => (string) $transaction->biller_identifier,
                'plan' => (string) $transaction->variation_code,
                'phone' => $transaction->recipient_phone ?: $transaction->user->phone_number,
                'ref' => $reference,
            ]],
            default => [null, []],
        };

        if ($path === null) {
            return new VendResult(successful: false, message: "MoreValue does not support {$biller->service_type} through this purchase flow.");
        }

        $response = $this->post($path, $payload);
        if ($response === null || ! $this->succeeded($response)) {
            return new VendResult(
                successful: false,
                message: $this->message($response, 'MoreValue Digital could not complete this purchase.'),
                raw: ['morevalue_reference' => $reference],
            );
        }

        return new VendResult(
            successful: true,
            token: $this->extractToken($response),
            providerReference: (string) ($response['reference'] ?? $response['ref'] ?? $response['transaction_id'] ?? $reference),
            message: $this->message($response, 'Purchase successful.'),
            raw: ['morevalue_reference' => $reference, 'morevalue_response' => $response],
        );
    }

    public function verify(Biller $biller, string $identifier, ?string $variationCode = null): MeterVerificationResult
    {
        if ($biller->service_type !== 'cable_tv' || ! $biller->supports_verify) {
            return new MeterVerificationResult(
                valid: true,
                message: "{$biller->name} does not require pre-purchase account verification.",
            );
        }

        if (! $biller->api_provider_code) {
            return new MeterVerificationResult(valid: false, message: 'This cable provider has not been configured for MoreValue Digital.');
        }

        $response = $this->post('/cabletv/verify/', [
            'provider' => (string) $biller->api_provider_code,
            'iucnumber' => $identifier,
        ]);

        if ($response === null || ! $this->succeeded($response)) {
            return new MeterVerificationResult(
                valid: false,
                message: $this->message($response, 'This smartcard/IUC number could not be verified.'),
                raw: $response ?? [],
            );
        }

        return new MeterVerificationResult(
            valid: true,
            customerName: $response['customer_name'] ?? $response['name'] ?? null,
            message: 'Account verified.',
            raw: $response,
        );
    }

    /** MoreValue keeps live plan IDs confidential; manage them in biller_variations. */
    public function fetchVariations(Biller $biller): array
    {
        return $biller->variations()->get()->map(fn ($variation) => [
            'variation_code' => $variation->variation_code,
            'name' => $variation->name,
            'amount' => (string) $variation->amount,
            'fixed_price' => (bool) $variation->fixed_price,
        ])->all();
    }

    private function extractToken(array $response): ?string
    {
        if (isset($response['pins']) && is_array($response['pins'])) {
            return collect($response['pins'])
                ->map(fn (array $pin) => trim(($pin['serial'] ?? '').' / '.($pin['pin'] ?? '')))
                ->implode('; ');
        }

        return isset($response['token']) ? (string) $response['token'] : null;
    }
}
