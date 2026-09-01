<?php

namespace App\Domain\Vending\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class SchedwaveClient
{
    /** @return array<string, mixed>|null */
    protected function get(string $path, array $query = []): ?array
    {
        return $this->request('get', $path, $query);
    }

    /** @return array<string, mixed>|null */
    protected function post(string $path, array $body): ?array
    {
        return $this->request('post', $path, $body);
    }

    /** @return array<string, mixed>|null */
    private function request(string $method, string $path, array $data): ?array
    {
        $key = (string) config('vending.schedwave.api_key');
        if ($key === '') {
            Log::error('Schedwave request blocked: SCHEDWAVE_API_KEY is not configured.');

            return null;
        }

        try {
            $pending = Http::baseUrl(rtrim((string) config('vending.schedwave.base_url'), '/'))
                ->acceptJson()
                ->withToken($key)
                ->timeout((int) config('vending.schedwave.timeout', 30));
            $response = $method === 'get'
                ? $pending->get($path, $data)
                : $pending->asJson()->post($path, $data);
            $decoded = $response->json();

            if (! is_array($decoded)) {
                Log::error("Schedwave {$path} returned a non-JSON response.", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            if ($response->failed() || ($decoded['error'] ?? false)) {
                Log::warning("Schedwave {$path} rejected a request.", [
                    'status' => $response->status(),
                    'error_code' => $decoded['error_code'] ?? null,
                    'message' => $decoded['message'] ?? null,
                ]);
            }

            return $decoded;
        } catch (Throwable $e) {
            Log::error("Schedwave {$path} request failed.", ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function succeeded(?array $response): bool
    {
        if ($response === null || ($response['error'] ?? false) === true) {
            return false;
        }

        return in_array(strtolower((string) ($response['status'] ?? 'completed')), ['completed', 'success', 'successful'], true);
    }

    protected function message(?array $response, string $fallback): string
    {
        return (string) ($response['message'] ?? $response['error_code'] ?? $fallback);
    }

    public function healthCheck(): bool
    {
        $response = $this->get('/balance');

        return $response !== null && ! ($response['error'] ?? true) && isset($response['balance']);
    }
}
