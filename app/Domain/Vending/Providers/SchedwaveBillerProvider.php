<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Vending\Contracts\BillerVendingProviderContract;
use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Biller;
use App\Models\Transaction;

class SchedwaveBillerProvider extends SchedwaveClient implements BillerVendingProviderContract
{
    private const NETWORK_IDS = ['MTN' => 1, 'AIRTEL' => 2, 'GLO' => 3, '9MOBILE' => 4];

    private const CABLE_IDS = ['GOTV' => 1, 'DSTV' => 2, 'STARTIMES' => 3];

    private const EXAM_IDS = ['WAEC' => 1, 'NECO' => 2, 'NABTEB' => 3, 'JAMB' => 4];

    public function vend(Transaction $transaction): VendResult
    {
        $biller = $transaction->biller;
        if (! $biller) {
            return new VendResult(successful: false, message: 'No utility provider is attached to this purchase.');
        }

        if ($existingOrder = $transaction->meta['schedwave_order_id'] ?? null) {
            return $this->requery((int) $existingOrder, $biller->service_type);
        }

        $phone = $transaction->biller_identifier ?: ($transaction->recipient_phone ?: $transaction->user->phone_number);
        [$path, $payload] = match ($biller->service_type) {
            'airtime' => ['/vtu/airtime', [
                'network' => $this->networkId($biller), 'phone' => $phone,
                'amount' => (int) round((float) $transaction->amount),
            ]],
            'data' => ['/vtu/data', [
                'network' => $this->networkId($biller), 'phone' => $phone,
                'plan_id' => (int) $transaction->variation_code,
            ]],
            'cable_tv' => ['/vtu/cable', [
                'cable' => $this->cableId($biller),
                'iuc' => (string) $transaction->biller_identifier,
                'cable_plan' => (int) $transaction->variation_code,
            ]],
            'education' => ['/vtu/exam', [
                'exam_id' => $this->examId($biller),
                'quantity' => (int) ($transaction->meta['quantity'] ?? 1),
            ]],
            default => [null, []],
        };

        if ($path === null || in_array(null, $payload, true)) {
            return new VendResult(successful: false, message: "{$biller->name} is not supported by Schedwave.");
        }

        $response = $this->post($path, $payload);
        $orderId = $response['order_id'] ?? null;
        if ($orderId) {
            $transaction->update(['meta' => [...($transaction->meta ?? []), 'schedwave_order_id' => $orderId]]);
        }

        return $this->result($response, $orderId);
    }

    public function verify(Biller $biller, string $identifier, ?string $variationCode = null): MeterVerificationResult
    {
        if ($biller->service_type !== 'cable_tv') {
            return new MeterVerificationResult(valid: true, message: "{$biller->name} does not require account verification.");
        }

        $cableId = $this->cableId($biller);
        if ($cableId === null) {
            return new MeterVerificationResult(valid: false, message: 'This cable provider is not supported by Schedwave.');
        }

        $response = $this->get('/vtu/cable-validate', ['iuc' => $identifier, 'cable' => $cableId]);
        if ($response === null || ($response['error'] ?? true)) {
            return new MeterVerificationResult(valid: false, message: $this->message($response, 'This smartcard/IUC number could not be verified.'), raw: $response ?? []);
        }

        return new MeterVerificationResult(valid: true, customerName: $response['name'] ?? null, message: $response['message'] ?? 'Account verified.', raw: $response);
    }

    public function fetchVariations(Biller $biller): array
    {
        $items = match ($biller->service_type) {
            'data' => $this->dataPlans($biller),
            'cable_tv' => $this->cablePlans($biller),
            'education' => $this->examTypes($biller),
            default => [],
        };

        return array_values($items);
    }

    private function dataPlans(Biller $biller): array
    {
        $network = $this->networkName($biller);
        $response = $this->get('/vtu/data-plans', $network ? ['network' => $network] : []);

        return collect($response['plans'] ?? [])->map(fn (array $plan) => [
            'variation_code' => (string) $plan['plan_id'],
            'name' => trim(($plan['network'] ?? '').' '.($plan['datasize'] ?? '').' '.($plan['type'] ?? '').' '.($plan['validity'] ?? '').' days'),
            'amount' => $this->customerPlanPrice($plan['price']), 'fixed_price' => true,
        ])->all();
    }

    private function cablePlans(Biller $biller): array
    {
        $cable = array_search($this->cableId($biller), self::CABLE_IDS, true);
        $response = $cable ? $this->get('/vtu/cable-plans', ['cable' => $cable]) : null;

        return collect($response['plans'] ?? [])->map(fn (array $plan) => [
            'variation_code' => (string) $plan['plan_id'], 'name' => (string) $plan['name'],
            'amount' => $this->customerPlanPrice($plan['price']), 'fixed_price' => true,
        ])->all();
    }

    private function examTypes(Biller $biller): array
    {
        $examId = $this->examId($biller);
        if ($examId === null) {
            return [];
        }
        $response = $this->get('/vtu/exam-types');

        return collect($response['exams'] ?? [])->where('exam_id', $examId)->map(fn (array $exam) => [
            'variation_code' => (string) $exam['exam_id'], 'name' => (string) $exam['name'].' PIN',
            'amount' => $this->customerPlanPrice($exam['price']), 'fixed_price' => true,
        ])->values()->all();
    }

    private function requery(int $orderId, string $serviceType): VendResult
    {
        $type = ['cable_tv' => 'cable', 'education' => 'exam'][$serviceType] ?? $serviceType;
        for ($page = 1; $page <= 5; $page++) {
            $response = $this->get('/vtu/orders', ['type' => $type, 'page' => $page, 'limit' => 100]);
            $order = collect($response['orders'] ?? [])->firstWhere('order_id', $orderId);
            if ($order) {
                return $this->result($order, $orderId);
            }
            if (($response['total_pages'] ?? 0) <= $page) {
                break;
            }
        }

        return new VendResult(successful: false, message: 'Schedwave purchase is still being reconciled.', raw: ['schedwave_order_id' => $orderId]);
    }

    private function result(?array $response, int|string|null $orderId): VendResult
    {
        if (! $this->succeeded($response)) {
            return new VendResult(successful: false, message: $this->message($response, 'Schedwave could not complete this purchase.'), raw: $orderId ? ['schedwave_order_id' => $orderId] : []);
        }

        $token = $response['token'] ?? $response['pin'] ?? null;
        if (isset($response['pins']) && is_array($response['pins'])) {
            $token = collect($response['pins'])->map(fn ($pin) => is_array($pin) ? ($pin['pin'] ?? json_encode($pin)) : $pin)->implode('; ');
        }

        return new VendResult(successful: true, token: $token ? (string) $token : null,
            providerReference: (string) ($response['reference'] ?? $orderId ?? ''),
            message: $this->message($response, 'Purchase successful.'),
            raw: ['schedwave_order_id' => $orderId, 'schedwave_response' => $response]);
    }

    private function networkId(Biller $biller): ?int
    {
        return self::NETWORK_IDS[$this->networkName($biller)] ?? null;
    }

    private function customerPlanPrice(int|float|string $providerPrice): string
    {
        return bcadd(
            (string) $providerPrice,
            (string) config('vending.schedwave.plan_markup', '50.00'),
            2,
        );
    }

    private function networkName(Biller $biller): string
    {
        return str($biller->code)->before('_')->upper()->value();
    }

    private function cableId(Biller $biller): ?int
    {
        return self::CABLE_IDS[strtoupper($biller->code)] ?? null;
    }

    private function examId(Biller $biller): ?int
    {
        return self::EXAM_IDS[strtoupper($biller->code)] ?? null;
    }
}
