<?php

namespace App\Domain\Payments;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper over the Paystack REST API (https://paystack.com/docs/api/)
 * — used directly by WalletController, TransactionService, and
 * WithdrawalController rather than through PaymentManager's
 * PaymentProcessorContract, because Paystack's hosted checkout is a
 * redirect-and-confirm-later flow (initialize -> customer pays on
 * Paystack's page -> `charge.success` webhook), not the synchronous
 * "charge and get a result back" shape that contract assumes.
 *
 * Every method returns null (and logs) on a network failure or an
 * unexpected non-JSON-object response, mirroring the same discipline
 * applied to VTpass's provider classes — a rejected/failed call is a
 * normal outcome for a caller to handle, a malformed response is not
 * something any caller here should have to defend against individually.
 */
class PaystackGateway
{
    /** @return array{authorization_url: string, access_code: string, reference: string}|null */
    public function initializeTransaction(string $email, int $amountKobo, string $reference, array $metadata = []): ?array
    {
        $response = $this->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $amountKobo,
            'reference' => $reference,
            'callback_url' => config('payments.paystack.callback_url'),
            'metadata' => $metadata,
        ]);

        return $response['data'] ?? null;
    }

    /** @return array<string, mixed>|null Paystack's transaction data payload (status/reference/amount/gateway_response/...). */
    public function verifyTransaction(string $reference): ?array
    {
        $response = $this->get('/transaction/verify/'.rawurlencode($reference));

        return $response['data'] ?? null;
    }

    /** @return array<int, array{name: string, code: string}> */
    public function listBanks(): array
    {
        $response = $this->get('/bank', ['country' => 'nigeria', 'currency' => 'NGN', 'perPage' => 100]);

        return collect($response['data'] ?? [])
            ->map(fn (array $bank) => ['name' => $bank['name'], 'code' => $bank['code']])
            ->values()
            ->all();
    }

    /**
     * @return array{account_number: string, account_name: string}|null null if the account/bank pair can't be resolved.
     *
     * Cached per (bank, account) pair — Paystack's test mode allows only a
     * handful of real account-number resolutions per day (it errors with
     * "Test mode daily limit ... exceeded" past that), and the same pair
     * legitimately gets resolved twice in the normal withdrawal flow: once
     * client-side while the customer is typing, and again server-side in
     * WithdrawalController::store() before ever moving money — that second
     * check is deliberate (never pay out on an unverified client claim) and
     * stays in place, this just stops it from costing a second live call.
     */
    public function resolveAccount(string $accountNumber, string $bankCode): ?array
    {
        $cacheKey = "paystack:resolved-account:{$bankCode}:{$accountNumber}";

        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($accountNumber, $bankCode) {
            $response = $this->get('/bank/resolve', ['account_number' => $accountNumber, 'bank_code' => $bankCode]);

            return $response['data'] ?? null;
        });
    }

    public function createTransferRecipient(string $accountNumber, string $bankCode, string $accountName): ?string
    {
        $response = $this->post('/transferrecipient', [
            'type' => 'nuban',
            'name' => $accountName,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'NGN',
        ]);

        return $response['data']['recipient_code'] ?? null;
    }

    /** @return array{transfer_code: string, status: string}|null */
    public function initiateTransfer(string $recipientCode, int $amountKobo, string $reference, string $reason): ?array
    {
        $response = $this->post('/transfer', [
            'source' => 'balance',
            'amount' => $amountKobo,
            'recipient' => $recipientCode,
            'reference' => $reference,
            'reason' => $reason,
        ]);

        return $response['data'] ?? null;
    }

    /**
     * https://paystack.com/docs/payments/webhooks/ — the x-paystack-signature
     * header is a hex-encoded HMAC-SHA512 of the *raw* request body, keyed
     * with the secret key. Must be checked against the raw bytes, before
     * any JSON decoding, or a semantically-identical-but-differently-
     * formatted body would fail verification.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, (string) config('payments.paystack.secret_key'));

        return hash_equals($expected, $signatureHeader);
    }

    protected function post(string $path, array $body): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withToken($this->secretKey())
                ->timeout($this->timeout())
                ->post($path, $body);

            return $this->decode($path, $response);
        } catch (Throwable $e) {
            Log::error("Paystack {$path} request failed", ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function get(string $path, array $query = []): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withToken($this->secretKey())
                ->timeout($this->timeout())
                ->get($path, $query);

            return $this->decode($path, $response);
        } catch (Throwable $e) {
            Log::error("Paystack {$path} request failed", ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function decode(string $path, Response $response): ?array
    {
        $decoded = $response->json();

        if (! is_array($decoded)) {
            Log::error("Paystack {$path} returned an unexpected (non-JSON-object) response", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        if (($decoded['status'] ?? null) !== true) {
            // A well-formed-but-unsuccessful Paystack response (e.g.
            // "Invalid key", account not whitelisted for transfers, an
            // unresolvable account number) — not a comm failure, but
            // still worth a trace of *why* Paystack said no, same
            // reasoning as VTpass's "merchant-verify rejected" logging.
            Log::info("Paystack {$path} rejected", ['response' => $decoded]);
        }

        return $decoded;
    }

    protected function baseUrl(): string
    {
        return config('payments.paystack.base_url');
    }

    protected function secretKey(): string
    {
        return (string) config('payments.paystack.secret_key');
    }

    protected function timeout(): int
    {
        return (int) config('payments.paystack.timeout', 30);
    }
}
