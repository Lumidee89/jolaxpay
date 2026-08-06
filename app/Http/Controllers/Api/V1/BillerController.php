<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Vending\VendingManager;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerifyBillerRequest;
use App\Http\Resources\BillerResource;
use App\Models\Biller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Lists airtime/data/cable_tv/education billers (+ their cached bundle
 * options) for the mobile purchase forms, and wraps VTpass's
 * merchant-verify for the billers that support it (PRD §7.9 Price
 * Transparency's "who am I paying" — same idea as MeterController::verify).
 */
class BillerController extends Controller
{
    public function __construct(private readonly VendingManager $vending) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['service_type' => ['nullable', new Enum(ServiceType::class)]]);

        $billers = Biller::query()
            ->where('is_active', true)
            ->when($request->query('service_type'), fn ($q, $type) => $q->where('service_type', $type))
            ->with('variations')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => BillerResource::collection($billers)]);
    }

    public function verify(VerifyBillerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $biller = Biller::findOrFail($data['biller_id']);

        $result = $this->vending->billerDriverFor(ServiceType::from($biller->service_type))
            ->verify($biller, $data['identifier'], $data['variation_code'] ?? null);

        return response()->json([
            'valid' => $result->valid,
            'customer_name' => $result->customerName,
            'message' => $result->message,
        ], $result->valid ? 200 : 422);
    }
}
