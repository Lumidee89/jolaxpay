<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Vending\Contracts\VendingProviderContract;
use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class SchedwaveElectricityProvider extends SchedwaveClient implements VendingProviderContract
{
    private const CODE_ALIASES = [
        'IKEDC' => ['IKEDC', 'IE'], 'EKEDC' => ['EKEDC', 'EKO'], 'AEDC' => ['AEDC', 'ABUJA'],
        'PHED' => ['PHED', 'PHEDC'], 'KEDCO' => ['KEDCO', 'KANO'], 'EEDC' => ['EEDC', 'ENUGU'],
        'IBEDC' => ['IBEDC', 'IBADAN'], 'BEDC' => ['BEDC', 'BENIN'], 'JED' => ['JED', 'JEDC', 'JOS'],
        'KAEDCO' => ['KAEDCO', 'KADUNA'],
    ];

    public function vend(Transaction $transaction): VendResult
    {
        $meter = $transaction->meter;
        if (! $meter?->disco) {
            return new VendResult(successful: false, message: 'No meter/DisCo is attached to this purchase.');
        }

        if ($existingOrder = $transaction->meta['schedwave_order_id'] ?? null) {
            return $this->requery((int) $existingOrder);
        }

        $providerId = $this->providerId($meter->disco);
        if ($providerId === null) {
            return new VendResult(successful: false, message: "{$meter->disco->name} is not currently available on Schedwave.");
        }

        $response = $this->post('/vtu/electricity', [
            'disco' => $providerId,
            'meter_number' => $meter->meter_number,
            'meter_type' => strtolower($meter->meter_type),
            'amount' => (int) round((float) $transaction->amount),
            'phone' => $transaction->recipient_phone ?: $transaction->user->phone_number,
        ]);
        $orderId = $response['order_id'] ?? null;
        if ($orderId) {
            $transaction->update(['meta' => [...($transaction->meta ?? []), 'schedwave_order_id' => $orderId]]);
        }

        return $this->result($response, $orderId);
    }

    public function verifyMeter(Meter $meter): MeterVerificationResult
    {
        if (! $meter->disco) {
            return new MeterVerificationResult(valid: false, message: 'This meter has no DisCo assigned.');
        }
        $providerId = $this->providerId($meter->disco);
        if ($providerId === null) {
            return new MeterVerificationResult(valid: false, message: "{$meter->disco->name} is not currently available on Schedwave.");
        }

        $response = $this->get('/vtu/electricity-validate', [
            'meter_number' => $meter->meter_number,
            'meter_type' => strtolower($meter->meter_type),
            'disco' => $providerId,
        ]);
        if ($response === null || ($response['error'] ?? true)) {
            return new MeterVerificationResult(valid: false, message: $this->message($response, 'This meter number could not be verified.'), raw: $response ?? []);
        }

        return new MeterVerificationResult(valid: true, customerName: $response['name'] ?? null,
            address: $response['address'] ?? null, message: $response['message'] ?? 'Meter verified.', raw: $response);
    }

    /** @return array<int, array{id: int, name: string, code: string}> */
    public function fetchProviders(): array
    {
        $response = $this->get('/vtu/electricity-providers');

        return collect($response['providers'] ?? [])->filter(fn ($provider) => isset($provider['id'], $provider['name']))
            ->map(fn (array $provider) => ['id' => (int) $provider['id'], 'name' => (string) $provider['name'], 'code' => strtoupper((string) ($provider['code'] ?? ''))])
            ->values()->all();
    }

    private function providerId(Disco $disco): ?int
    {
        $providers = Cache::remember('schedwave:electricity-providers', now()->addHours(6), fn () => $this->fetchProviders());
        $aliases = self::CODE_ALIASES[strtoupper($disco->code)] ?? [strtoupper($disco->code)];
        $provider = collect($providers)->first(function (array $provider) use ($aliases, $disco) {
            if (in_array($provider['code'], $aliases, true)) {
                return true;
            }
            $providerName = str($provider['name'])->lower();

            return collect(explode(' ', strtolower($disco->name)))->filter(fn ($word) => strlen($word) > 4)
                ->contains(fn ($word) => $providerName->contains($word));
        });

        return $provider['id'] ?? null;
    }

    private function requery(int $orderId): VendResult
    {
        for ($page = 1; $page <= 5; $page++) {
            $response = $this->get('/vtu/orders', ['type' => 'electricity', 'page' => $page, 'limit' => 100]);
            $order = collect($response['orders'] ?? [])->firstWhere('order_id', $orderId);
            if ($order) {
                return $this->result($order, $orderId);
            }
            if (($response['total_pages'] ?? 0) <= $page) {
                break;
            }
        }

        return new VendResult(successful: false, message: 'Schedwave electricity payment is still being reconciled.', raw: ['schedwave_order_id' => $orderId]);
    }

    private function result(?array $response, int|string|null $orderId): VendResult
    {
        if (! $this->succeeded($response)) {
            return new VendResult(successful: false, message: $this->message($response, 'Schedwave could not complete this electricity payment.'), raw: $orderId ? ['schedwave_order_id' => $orderId] : []);
        }

        return new VendResult(successful: true, token: isset($response['token']) ? (string) $response['token'] : null,
            providerReference: (string) ($response['reference'] ?? $orderId ?? ''),
            message: $this->message($response, 'Electricity payment successful.'),
            raw: ['schedwave_order_id' => $orderId, 'units' => $response['units'] ?? null, 'schedwave_response' => $response]);
    }
}
