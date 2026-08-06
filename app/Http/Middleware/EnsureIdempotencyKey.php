<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the idempotency-key contract from TRD §8: every purchase
 * -initiation request carries a client-generated `Idempotency-Key` header
 * so a retry on a flaky connection replays the original response instead
 * of double-charging.
 */
class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required for this request.',
            ], 422);
        }

        $user = $request->user();
        $route = $request->path();

        $record = IdempotencyKey::where('user_id', $user->id)
            ->where('route', $route)
            ->where('key', $key)
            ->first();

        if ($record && $record->response_status !== null) {
            return response()->json($record->response_body, $record->response_status)
                ->header('Idempotency-Replayed', 'true');
        }

        if ($record && $record->locked_at?->gt(now()->subSeconds(30))) {
            return response()->json([
                'message' => 'A request with this idempotency key is already in progress.',
            ], 409);
        }

        $record ??= new IdempotencyKey([
            'user_id' => $user->id,
            'route' => $route,
            'key' => $key,
        ]);
        $record->locked_at = now();
        $record->save();

        $response = $next($request);

        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => json_decode($response->getContent(), true),
            'locked_at' => null,
        ]);

        return $response;
    }
}
