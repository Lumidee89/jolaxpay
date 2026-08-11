<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Vending\Contracts\BillerVendingProviderContract;
use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Biller;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live airtime/data/cable_tv/education vending via VTpass
 * (https://vtpass.com/documentation/) — the biller-anchored counterpart
 * to VtpassElectricityProvider. One driver covers all four service types
 * because VTpass's request shape only varies by a handful of per-biller
 * flags (see Biller::requires_billers_code / requires_variation), verified
 * against VTpass's own per-product docs:
 *
 * - Airtime (mtn/glo/airtel/etisalat): request_id + serviceID + phone + amount.
 * - Data (mtn-data/glo-data/...): + billersCode (=subscriber phone) + variation_code.
 * - Cable TV (dstv/gotv/startimes): + billersCode (=smartcard) + variation_code
 *   + subscription_type ("change"/"renew").
 * - Education (waec: variation_code + phone only; jamb: + billersCode = Profile ID).
 *
 * Same requery-not-resubmit behaviour as VtpassElectricityProvider (see
 * its class docblock) — the `vtpass_request_id` stored on
 * `transaction.meta` is reused across the bounded retry loop, and the same
 * pending-past-every-retry reconciliation gap applies here too.
 */
class VtpassBillerProvider implements BillerVendingProviderContract
{
    public function vend(Transaction $transaction): VendResult
    {
        $biller = $transaction->biller;

        if (! $biller) {
            return new VendResult(successful: false, message: 'No biller on this transaction — cannot vend.');
        }

        $existingRequestId = $transaction->meta['vtpass_request_id'] ?? null;

        if ($existingRequestId) {
            return $this->interpretResponse($this->requery($existingRequestId), $existingRequestId);
        }

        $requestId = $this->generateRequestId();
        $transaction->update(['meta' => [...($transaction->meta ?? []), 'vtpass_request_id' => $requestId]]);

        // For airtime, `biller_identifier` *is* the phone number being
        // recharged (Biller::requires_billers_code = false there, so
        // there's no separate billersCode field for it to land in below —
        // it only ever reaches VTpass via this `phone` param). Using
        // `recipient_phone` first was wrong: for delivery_destination =
        // "me" that resolves to the *buyer's own* phone_number, which
        // silently recharged the account owner's line instead of whatever
        // number the customer actually entered. Every other service type
        // here (data/cable_tv/education-jamb) already sends the entered
        // number as `billersCode` below, so `phone` there is genuinely
        // just a contact field and recipient_phone/the buyer's own number
        // are fine.
        $phone = (! $biller->requires_billers_code && $transaction->biller_identifier)
            ? $transaction->biller_identifier
            : ($transaction->recipient_phone ?: $transaction->user->phone_number);

        $payload = [
            'request_id' => $requestId,
            'serviceID' => $biller->api_provider_code,
            'phone' => $phone,
            'amount' => (float) $transaction->amount,
        ];

        if ($biller->requires_billers_code) {
            $payload['billersCode'] = $transaction->biller_identifier;
        }

        if ($biller->requires_variation) {
            $payload['variation_code'] = $transaction->variation_code;
        }

        if ($biller->service_type === 'cable_tv') {
            $payload['subscription_type'] = $transaction->meta['subscription_type'] ?? 'renew';
            $payload['quantity'] = $transaction->meta['quantity'] ?? 1;
        }

        return $this->interpretResponse($this->post('/pay', $payload), $requestId);
    }

    public function verify(Biller $biller, string $identifier, ?string $variationCode = null): MeterVerificationResult
    {
        if (! $biller->supports_verify) {
            return new MeterVerificationResult(
                valid: true,
                message: "{$biller->name} doesn't support pre-purchase verification — double-check the number before paying.",
            );
        }

        $payload = ['billersCode' => $identifier, 'serviceID' => $biller->api_provider_code];

        if ($variationCode) {
            // JAMB's merchant-verify wants the variation under `type`, not `variation_code` (VTpass docs).
            $payload['type'] = $variationCode;
        }

        $response = $this->post('/merchant-verify', $payload);

        if ($response === null) {
            return new MeterVerificationResult(valid: false, message: 'Could not reach VTpass to verify this account — try again shortly.');
        }

        $content = $response['content'] ?? [];
        $wrongBillersCode = (bool) ($content['WrongBillersCode'] ?? false);
        // DSTV/GOTV/Startimes return Can_Vend; JAMB doesn't, so a returned Customer_Name is treated as good enough there.
        $canVend = array_key_exists('Can_Vend', $content) ? ($content['Can_Vend'] === 'yes') : true;

        if (($response['code'] ?? null) !== '000' || $wrongBillersCode || ! $canVend || empty($content['Customer_Name'])) {
            // See VtpassElectricityProvider::verifyMeter()'s equivalent log
            // line — a rejected verify is a normal outcome, not a comm
            // failure, but still worth a trace of *why* VTpass said no.
            Log::info('VTpass merchant-verify rejected', [
                'billersCode' => $identifier,
                'serviceID' => $biller->api_provider_code,
                'response' => $response,
            ]);

            return new MeterVerificationResult(
                valid: false,
                message: $response['response_description'] ?? $content['error'] ?? 'This number could not be verified. Double-check it and try again.',
                raw: $response,
            );
        }

        return new MeterVerificationResult(
            valid: true,
            customerName: $content['Customer_Name'] ?? null,
            message: 'Account verified.',
            raw: $response,
        );
    }

    /** @return array<int, array{variation_code: string, name: string, amount: string, fixed_price: bool}> */
    public function fetchVariations(Biller $biller): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withHeaders($this->getHeaders())
                ->timeout($this->timeout())
                ->get('/service-variations', ['serviceID' => $biller->api_provider_code]);

            $variations = $response->json('content.variations') ?? [];
        } catch (Throwable $e) {
            Log::error('VTpass service-variations request failed', ['biller' => $biller->code, 'error' => $e->getMessage()]);

            return [];
        }

        return collect($variations)->map(fn (array $v) => [
            'variation_code' => $v['variation_code'],
            'name' => $v['name'],
            'amount' => (string) $v['variation_amount'],
            'fixed_price' => ($v['fixedPrice'] ?? 'Yes') === 'Yes',
        ])->all();
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withHeaders($this->getHeaders())
                ->timeout($this->timeout())
                ->get('/balance');

            return $response->successful() && isset($response->json()['contents']['balance']);
        } catch (Throwable $e) {
            Log::warning('VTpass health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    protected function requery(string $requestId): ?array
    {
        return $this->post('/requery', ['request_id' => $requestId]);
    }

    /**
     * @return array<string, mixed>|null null on a network/communication
     * failure, or when VTpass responds with something other than a JSON
     * object — e.g. a bare quoted string like `"Invalid Credentials."` on
     * a 401, which isn't an `array` and would otherwise crash this
     * method's own `?array` return type instead of being logged plainly.
     */
    protected function post(string $path, array $body): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withHeaders($this->postHeaders())
                ->timeout($this->timeout())
                ->post($path, $body);

            $decoded = $response->json();

            if (! is_array($decoded)) {
                Log::error("VTpass {$path} returned an unexpected (non-JSON-object) response", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $decoded;
        } catch (Throwable $e) {
            Log::error("VTpass {$path} request failed", ['error' => $e->getMessage(), 'body' => $body]);

            return null;
        }
    }

    /**
     * Maps a VTpass response onto our VendResult. Airtime/data/cable_tv
     * responses carry `content.transactions.status`, same as electricity;
     * education (waec/jamb) pin purchases don't nest a `transactions`
     * object at all — a root `code === '000'` there already means final
     * success, per VTpass's own documented examples for those products.
     */
    protected function interpretResponse(?array $data, string $requestId): VendResult
    {
        if ($data === null) {
            return new VendResult(successful: false, message: 'VTpass: network or communication error.');
        }

        $code = (string) ($data['code'] ?? '');
        $status = $data['content']['transactions']['status'] ?? null;
        $description = $data['response_description'] ?? null;

        if ($code === '000' && ($status === 'delivered' || $status === null)) {
            return new VendResult(
                successful: true,
                token: $this->extractToken($data),
                providerReference: (string) ($data['content']['transactions']['transactionId'] ?? $requestId),
                message: $description ?? 'VTpass: transaction successful.',
                raw: ['vtpass_request_id' => $requestId],
            );
        }

        if (in_array($status, ['pending', 'initiated'], true) || $code === '099') {
            return new VendResult(successful: false, message: "VTpass: transaction {$status} — will requery on next attempt.");
        }

        if ($code === '018') {
            // Our VTpass account's own wallet is low — an operational
            // emergency, not a customer-facing problem. Critical so ops
            // monitoring can't miss it (mirrors VtpassElectricityProvider).
            Log::critical('VTpass wallet balance is low — vending will keep failing until topped up.', ['response' => $data]);
        } elseif ($code !== '000') {
            // Any other rejection (e.g. code 028 "PRODUCT IS NOT
            // WHITELISTED ON YOUR ACCOUNT", an account-configuration
            // problem, not a customer one) — logged so the *why* isn't
            // only discoverable by manually requerying VTpass by hand.
            Log::warning("VTpass vend rejected (code {$code})", ['request_id' => $requestId, 'response' => $data]);
        }

        return new VendResult(
            successful: false,
            message: $description ?? "VTpass error (code {$code}).",
            raw: ['vtpass_request_id' => $requestId, 'response' => $data],
        );
    }

    /**
     * Airtime/data/cable_tv purchases have nothing to hand back (the
     * recharge itself is the delivery). Education pins come back either as
     * a labelled string (`purchased_code`, e.g. "Pin : 123...") or a
     * `cards` array (WAEC, when quantity > 1) — both are returned verbatim
     * rather than reduced to a bare code, since they already contain the
     * serial/pin labelling the customer needs.
     */
    protected function extractToken(array $data): ?string
    {
        if (isset($data['cards']) && is_array($data['cards'])) {
            return collect($data['cards'])
                ->map(fn (array $card) => trim(($card['Serial'] ?? '').' / '.($card['Pin'] ?? $card['pin'] ?? '')))
                ->implode('; ');
        }

        $raw = $data['purchased_code'] ?? null;

        return $raw !== null ? trim((string) $raw) : null;
    }

    protected function generateRequestId(): string
    {
        return now('Africa/Lagos')->format('YmdHi').Str::upper(Str::random(6));
    }

    protected function baseUrl(): string
    {
        return config('vending.vtpass.env') === 'live'
            ? config('vending.vtpass.base_url_live')
            : config('vending.vtpass.base_url_sandbox');
    }

    protected function timeout(): int
    {
        return (int) config('vending.vtpass.timeout', 30);
    }

    protected function postHeaders(): array
    {
        return [
            'api-key' => config('vending.vtpass.api_key'),
            'secret-key' => config('vending.vtpass.secret_key'),
        ];
    }

    protected function getHeaders(): array
    {
        return [
            'api-key' => config('vending.vtpass.api_key'),
            'public-key' => config('vending.vtpass.public_key'),
        ];
    }
}
