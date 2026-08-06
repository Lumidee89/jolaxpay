<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DiscoResource;
use App\Models\Disco;
use Illuminate\Http\JsonResponse;

/**
 * Per-DisCo/provider health (TRD §3, §5) — feeds both the mobile status
 * strip and the Admin Provider Health page.
 */
class ProviderStatusController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => DiscoResource::collection(Disco::where('is_active', true)->orderBy('name')->get()),
        ]);
    }
}
