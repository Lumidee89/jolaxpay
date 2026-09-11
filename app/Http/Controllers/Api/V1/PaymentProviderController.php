<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\PaymentProviderSelector;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PaymentProviderController extends Controller
{
    public function show(PaymentProviderSelector $selector): JsonResponse
    {
        $provider = $selector->active();

        return response()->json(['data' => [
            'provider' => $provider,
            'wallet_funding_methods' => $provider === 'safehaven'
                ? ['bank_transfer', 'card']
                : ['card'],
        ]])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
