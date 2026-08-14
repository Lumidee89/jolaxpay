<?php

namespace App\Domain\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/** Safe Haven MFB OAuth2, transfers, and VAS client. */
class SafeHavenGateway
{
    public function createVirtualAccount(float $amount, string $reference): ?array
    {
        $response = $this->request('post', '/virtual-accounts', [
            'validFor' => config('payments.safehaven.virtual_account_ttl', 900),
            'callbackUrl' => config('payments.safehaven.webhook_url'),
            'settlementAccount' => [
                'bankCode' => config('payments.safehaven.settlement_bank_code'),
                'accountNumber' => config('payments.safehaven.debit_account_number'),
            ],
            'amountControl' => 'Fixed', 'amount' => $amount, 'externalReference' => $reference,
        ]);
        return $response['data'] ?? $response;
    }

    public function virtualAccountTransaction(string $id): ?array
    {
        $response = $this->request('get', '/virtual-accounts/'.rawurlencode($id).'/transaction');
        return $response['data'] ?? $response;
    }

    public function verifyCheckout(string $reference): ?array
    {
        $response = $this->request('get', '/checkout/'.rawurlencode($reference).'/verify');
        return $response['data'] ?? $response;
    }
    public function listBanks(): array
    {
        $data = $this->request('get', '/transfers/banks');
        return collect($data['data'] ?? $data ?? [])->map(fn (array $bank) => [
            'name' => $bank['name'] ?? $bank['bankName'] ?? '',
            'code' => $bank['bankCode'] ?? $bank['code'] ?? '',
        ])->filter(fn ($bank) => $bank['name'] && $bank['code'])->values()->all();
    }

    public function resolveAccount(string $accountNumber, string $bankCode): ?array
    {
        return Cache::remember("safehaven:account:{$bankCode}:{$accountNumber}", now()->addMinutes(20), function () use ($accountNumber, $bankCode) {
            $response = $this->request('post', '/transfers/name-enquiry', ['bankCode' => $bankCode, 'accountNumber' => $accountNumber]);
            $data = $response['data'] ?? $response;
            $name = $data['accountName'] ?? $data['name'] ?? null;
            $reference = $data['sessionId'] ?? $data['nameEnquiryReference'] ?? null;
            return $name && $reference ? ['account_number' => $accountNumber, 'account_name' => $name, 'name_enquiry_reference' => $reference] : null;
        });
    }

    public function transfer(string $accountNumber, string $bankCode, string $nameEnquiryReference, float $amount, string $reference, string $narration): ?array
    {
        $response = $this->request('post', '/transfers', [
            'nameEnquiryReference' => $nameEnquiryReference,
            'debitAccountNumber' => config('payments.safehaven.debit_account_number'),
            'beneficiaryBankCode' => $bankCode,
            'beneficiaryAccountNumber' => $accountNumber,
            'amount' => $amount,
            'saveBeneficiary' => false,
            'narration' => $narration,
            'paymentReference' => $reference,
        ]);
        return $response['data'] ?? $response;
    }

    public function verifyUtility(string $category, string $meter): ?array
    {
        $response = $this->request('post', '/vas/verify', ['serviceCategoryId' => $category, 'entityNumber' => $meter]);
        return $response['data'] ?? $response;
    }

    public function payUtility(string $category, string $meter, string $vendType, float $amount, string $reference): ?array
    {
        $response = $this->request('post', '/vas/pay/utility', [
            'serviceCategoryId' => $category, 'amount' => $amount, 'channel' => 'WEB',
            'debitAccountNumber' => config('payments.safehaven.debit_account_number'),
            'meterNumber' => $meter, 'vendType' => $vendType, 'externalReference' => $reference,
        ]);
        return $response['data'] ?? $response;
    }

    protected function request(string $method, string $path, array $data = []): ?array
    {
        try {
            $credentials = $this->credentials();
            $clientId = config('payments.safehaven.ibs_client_id') ?: ($credentials['ibs_client_id'] ?? null);
            if (! $clientId) throw new RuntimeException('Safe Haven did not return an IBS Client ID.');
            $response = Http::baseUrl(config('payments.safehaven.base_url'))->withToken($credentials['access_token'])
                ->withHeaders(['ClientID' => $clientId])->acceptJson()
                ->timeout(config('payments.safehaven.timeout', 30))->{$method}($path, $data);
            $decoded = $response->json();
            if (! $response->successful() || ! is_array($decoded)) {
                Log::warning("Safe Haven {$path} rejected", ['status' => $response->status(), 'response' => $decoded ?? $response->body()]);
                return null;
            }
            return $decoded;
        } catch (Throwable $e) {
            Log::error("Safe Haven {$path} request failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /** @return array{access_token: string, ibs_client_id?: string} */
    protected function credentials(): array
    {
        return Cache::remember('safehaven:oauth-credentials', now()->addMinutes(50), function () {
            $response = Http::baseUrl(config('payments.safehaven.base_url'))->asForm()->post('/oauth2/token', [
                'grant_type' => 'client_credentials', 'client_id' => config('payments.safehaven.oauth_client_id'),
                'client_assertion' => $this->clientAssertion(),
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            ]);
            $data = $response->json();
            $token = $data['access_token'] ?? $data['data']['access_token'] ?? null;
            if (! $response->successful() || ! $token) {
                $safeResponse = is_array($data) ? $data : ['body' => $response->body()];
                unset($safeResponse['access_token'], $safeResponse['refresh_token']);
                if (isset($safeResponse['data']) && is_array($safeResponse['data'])) {
                    unset($safeResponse['data']['access_token'], $safeResponse['data']['refresh_token']);
                }
                Log::error('Safe Haven OAuth token exchange rejected', [
                    'status' => $response->status(),
                    'base_url' => config('payments.safehaven.base_url'),
                    'response' => $safeResponse,
                ]);
                throw new RuntimeException('Safe Haven did not return an access token.');
            }
            $ibsClientId = $data['ibs_client_id'] ?? $data['data']['ibs_client_id'] ?? null;
            return array_filter(['access_token' => $token, 'ibs_client_id' => $ibsClientId]);
        });
    }

    protected function clientAssertion(): string
    {
        $encode = fn (array $value) => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        // Backdate slightly to tolerate ordinary clock drift between hosts.
        // Large differences must be fixed on the host; otherwise Safe Haven
        // correctly treats the assertion as not yet valid.
        $now = time() - 30;
        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => config('payments.safehaven.company_url'), 'sub' => config('payments.safehaven.oauth_client_id'),
            'aud' => config('payments.safehaven.base_url'), 'iat' => $now, 'exp' => $now + 300,
        ]);
        $key = config('payments.safehaven.private_key');
        if (is_string($key) && ! str_contains($key, 'BEGIN')) $key = @file_get_contents($key) ?: '';
        if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) throw new RuntimeException('Could not sign Safe Haven client assertion.');
        return $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    public function checkoutConfig(): array
    {
        return [
            'environment' => str_contains(config('payments.safehaven.base_url'), 'sandbox') ? 'sandbox' : 'production',
            'clientId' => config('payments.safehaven.oauth_client_id'),
            'bankCode' => config('payments.safehaven.settlement_bank_code'),
            'accountNumber' => config('payments.safehaven.debit_account_number'),
        ];
    }
}
