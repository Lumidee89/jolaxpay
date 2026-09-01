<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            return new JsonResponse([
                'requires_email_verification' => true,
                'message' => 'Verify your email address before using JolaxPay.',
            ], 403);
        }

        return $next($request);
    }
}
