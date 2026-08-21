<?php

namespace App\Domain\Vending\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Shared authenticated HTTP client for MoreValue Digital's vending API. */
abstract class MoreValueClient
{
    /** @return array<string, mixed>|null */
    protected function get(string $path): ?array
    {
        return $this->request('get', $path);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    protected function post(string $path, array $payload): ?array
    {
        return $this->request('post', $path, $payload);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function request(string $method, string $path, array $payload = []): ?array
    {
        try {
            $token = trim((string) config('vending.morevalue.api_token'));
            if ($token === '') {
                Log::error('MoreValue request blocked: MOREVALUE_API_TOKEN is not configured.');

                return null;
            }

            $request = Http::baseUrl(rtrim((string) config('vending.morevalue.base_url'), '/'))
                ->withHeaders(['Token' => 'Token '.$token])
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('vending.morevalue.timeout', 30));

            /** @var Response $response */
            $response = $method === 'get'
                ? $request->get($path)
                : $request->post($path, $payload);

            $decoded = $response->json();
            if (! $response->successful() || ! is_array($decoded)) {
                Log::warning("MoreValue {$path} rejected", [
                    'status' => $response->status(),
                    'response' => is_array($decoded) ? $decoded : $response->body(),
                    'reference' => $payload['ref'] ?? null,
                ]);

                return is_array($decoded) ? $decoded : null;
            }

            return $decoded;
        } catch (Throwable $e) {
            Log::error("MoreValue {$path} request failed", [
                'error' => $e->getMessage(),
                'reference' => $payload['ref'] ?? null,
            ]);

            return null;
        }
    }

    protected function succeeded(array $response): bool
    {
        return in_array(strtolower((string) ($response['status'] ?? '')), ['success', 'successful'], true)
            || in_array(strtolower((string) ($response['Status'] ?? '')), ['success', 'successful'], true);
    }

    protected function message(?array $response, string $fallback): string
    {
        return (string) ($response['msg'] ?? $response['message'] ?? $response['detail'] ?? $fallback);
    }

    public function healthCheck(): bool
    {
        $response = $this->get('/user/');

        return $response !== null
            && $this->succeeded($response)
            && array_key_exists('balance', $response);
    }
}
