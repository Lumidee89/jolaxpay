<?php

namespace App\Domain\Vending\Providers;

use App\Domain\Vending\Contracts\VendingProviderContract;
use App\Domain\Vending\DataTransferObjects\MeterVerificationResult;
use App\Domain\Vending\DataTransferObjects\VendResult;
use App\Models\Meter;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live electricity vending via VTpass (https://vtpass.com/documentation/).
 * `Disco::api_provider_code` holds VTpass's `serviceID` for that biller
 * (e.g. "ikeja-electric") — see DiscoSeeder.
 *
 * VTpass's own guidance is: don't resubmit a transaction that comes back
 * "pending" — requery the same `request_id` until it resolves. So this
 * driver stores the VTpass `request_id` on `transaction.meta` the first
 * time it pays, and every subsequent call from the bounded retry loop in
 * TransactionService::processVending() requeries that same id instead of
 * paying again (which would otherwise risk a duplicate charge or VTpass's
 * "REQUEST ID ALREADY EXIST" error).
 *
 * Known limitation (documented, not silently ignored): if every bounded
 * retry still comes back "pending", TransactionService marks the purchase
 * Failed and auto-refunds the wallet — but VTpass may still resolve that
 * request to "delivered" asynchronously afterward. Closing this gap for
 * real needs a VTpass webhook receiver + a reconciliation job cross-
 * checking `transactions` against VTpass's own history; that's Phase 2
 * work alongside the Reconciliation admin page already scaffolded.
 */
class VtpassElectricityProvider implements VendingProviderContract
{
    public function vend(Transaction $transaction): VendResult
    {
        $meter = $transaction->meter;

        if (! $meter || ! $meter->disco) {
            return new VendResult(successful: false, message: 'No meter/DisCo on this transaction — cannot vend.');
        }

        $serviceId = $meter->disco->api_provider_code;
        $existingRequestId = $transaction->meta['vtpass_request_id'] ?? null;

        if ($existingRequestId) {
            $response = $this->requery($existingRequestId);

            return $this->interpretResponse($response, $existingRequestId);
        }

        $requestId = $this->generateRequestId();
        $transaction->update(['meta' => [...($transaction->meta ?? []), 'vtpass_request_id' => $requestId]]);

        $phone = $transaction->recipient_phone ?: $transaction->user->phone_number;

        $response = $this->post('/pay', [
            'request_id' => $requestId,
            'serviceID' => $serviceId,
            'billersCode' => $meter->meter_number,
            'variation_code' => $meter->meter_type, // 'prepaid' or 'postpaid'
            'amount' => (float) $transaction->amount,
            'phone' => $phone,
        ]);

        return $this->interpretResponse($response, $requestId);
    }

    public function verifyMeter(Meter $meter): MeterVerificationResult
    {
        if (! $meter->disco) {
            return new MeterVerificationResult(valid: false, message: 'This meter has no DisCo assigned.');
        }

        $response = $this->post('/merchant-verify', [
            'billersCode' => $meter->meter_number,
            'serviceID' => $meter->disco->api_provider_code,
            'type' => $meter->meter_type,
        ]);

        if ($response === null) {
            return new MeterVerificationResult(valid: false, message: 'Could not reach VTpass to verify this meter — try again shortly.');
        }

        $content = $response['content'] ?? [];
        $wrongBillersCode = (bool) ($content['WrongBillersCode'] ?? false);
        $canVend = ($content['Can_Vend'] ?? 'no') === 'yes';

        if (($response['code'] ?? null) !== '000' || $wrongBillersCode || ! $canVend) {
            // Unlike a network/comm failure (logged in post()), a *rejected*
            // verify is a normal outcome (wrong meter number, wrong DisCo,
            // ...) — logged at info rather than error, but still logged,
            // since otherwise the only trace of *why* VTpass said no is
            // whatever generic message the mobile app shows.
            Log::info('VTpass merchant-verify rejected', [
                'billersCode' => $meter->meter_number,
                'serviceID' => $meter->disco->api_provider_code,
                'response' => $response,
            ]);

            return new MeterVerificationResult(
                valid: false,
                message: $response['response_description'] ?? $content['error'] ?? 'This meter number could not be verified. Double-check it and try again.',
                raw: $response,
            );
        }

        return new MeterVerificationResult(
            valid: true,
            customerName: $content['Customer_Name'] ?? null,
            address: $content['Address'] ?? null,
            minimumAmount: ($content['Min_Purchase_Amount'] ?? null) ?: ($content['Minimum_Amount'] ?? null) ?: null,
            message: 'Meter verified.',
            raw: $response,
        );
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

    /**
     * VTpass's live electricity billers list (GET /services?identifier=
     * electricity-bill) — the authoritative source `DiscoSeeder`'s
     * hardcoded list only bootstraps. See
     * App\Console\Commands\SyncDiscosFromVtpass, which upserts `discos`
     * from this so new/renamed/retired DisCos don't need a code change.
     *
     * @return array<int, array{service_id: string, name: string}>
     */
    public function fetchElectricityServices(): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withHeaders($this->getHeaders())
                ->timeout($this->timeout())
                ->get('/services', ['identifier' => 'electricity-bill']);

            $services = $response->json('content');
        } catch (Throwable $e) {
            Log::error('VTpass /services request failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! is_array($services)) {
            Log::error('VTpass /services returned an unexpected (non-JSON-array) response');

            return [];
        }

        return collect($services)
            ->filter(fn ($service) => isset($service['serviceID'], $service['name']))
            ->map(fn (array $service) => [
                'service_id' => $service['serviceID'],
                'name' => $service['name'],
            ])
            ->values()
            ->all();
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
     * Maps a VTpass response onto our VendResult. See response code table:
     * https://vtpass.com/documentation/response-codes/ — 000+delivered is
     * the only success case; pending/initiated/099 are non-final (the
     * caller's retry loop will requery next time, see class docblock).
     */
    protected function interpretResponse(?array $data, string $requestId): VendResult
    {
        if ($data === null) {
            return new VendResult(successful: false, message: 'VTpass: network or communication error.');
        }

        $code = (string) ($data['code'] ?? '');
        $status = $data['content']['transactions']['status'] ?? null;
        $description = $data['response_description'] ?? null;

        if ($code === '000' && $status === 'delivered') {
            return new VendResult(
                successful: true,
                token: $this->extractToken($data),
                providerReference: (string) ($data['content']['transactions']['transactionId'] ?? $requestId),
                message: $description ?? 'VTpass: delivered.',
                raw: [
                    'vtpass_request_id' => $requestId,
                    'units' => $data['units'] ?? null,
                    'token_amount' => $data['tokenAmount'] ?? null,
                ],
            );
        }

        if (in_array($status, ['pending', 'initiated'], true) || $code === '099') {
            return new VendResult(successful: false, message: "VTpass: transaction {$status} — will requery on next attempt.");
        }

        if ($code === '018') {
            // Our VTpass account's own wallet is low — an operational
            // emergency, not a customer-facing "meter problem". Logged at
            // critical so it's impossible to miss in ops monitoring.
            Log::critical('VTpass wallet balance is low — electricity vending will keep failing until topped up.', ['response' => $data]);
        } elseif ($code !== '000') {
            // Any other rejection (e.g. code 028 "PRODUCT IS NOT
            // WHITELISTED ON YOUR ACCOUNT", an account-configuration
            // problem, not a customer/meter one) — logged so the *why*
            // isn't only discoverable by manually requerying VTpass by hand.
            Log::warning("VTpass vend rejected (code {$code})", ['request_id' => $requestId, 'response' => $data]);
        }

        return new VendResult(
            successful: false,
            message: $description ?? "VTpass error (code {$code}).",
            raw: ['vtpass_request_id' => $requestId, 'response' => $data],
        );
    }

    protected function extractToken(array $data): ?string
    {
        $raw = $data['token'] ?? $data['purchased_code'] ?? null;

        if (! $raw) {
            return null; // postpaid purchases settle a bill and have no token
        }

        return trim(preg_replace('/^token\s*:\s*/i', '', (string) $raw));
    }

    protected function generateRequestId(): string
    {
        // VTpass: "a string in unix format YYYYMMDDHHII", min 12 chars, the
        // first 12 numeric and representing today's date in Africa/Lagos —
        // must also be unique per request, hence the random suffix.
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

    /** POST requests: api-key + secret-key (VTpass auth docs). */
    protected function postHeaders(): array
    {
        return [
            'api-key' => config('vending.vtpass.api_key'),
            'secret-key' => config('vending.vtpass.secret_key'),
        ];
    }

    /** GET requests: api-key + public-key (VTpass auth docs). */
    protected function getHeaders(): array
    {
        return [
            'api-key' => config('vending.vtpass.api_key'),
            'public-key' => config('vending.vtpass.public_key'),
        ];
    }
}
