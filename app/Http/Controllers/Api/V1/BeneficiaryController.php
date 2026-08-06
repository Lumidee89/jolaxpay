<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBeneficiaryRequest;
use App\Http\Requests\Api\V1\UpdateBeneficiaryRequest;
use App\Http\Resources\BeneficiaryResource;
use App\Models\Beneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Saved airtime/data/cable_tv/education recipients — the non-electricity counterpart to MeterController. */
class BeneficiaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $beneficiaries = $request->user()->beneficiaries()
            ->with('biller')
            ->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => BeneficiaryResource::collection($beneficiaries)]);
    }

    public function store(StoreBeneficiaryRequest $request): JsonResponse
    {
        $beneficiary = $request->user()->beneficiaries()->create($request->validated());

        return response()->json(['data' => BeneficiaryResource::make($beneficiary->load('biller'))], 201);
    }

    public function show(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        $this->authorizeOwnership($request, $beneficiary);

        return response()->json(['data' => BeneficiaryResource::make($beneficiary->load('biller'))]);
    }

    public function update(UpdateBeneficiaryRequest $request, Beneficiary $beneficiary): JsonResponse
    {
        $this->authorizeOwnership($request, $beneficiary);

        $beneficiary->update($request->validated());

        return response()->json(['data' => BeneficiaryResource::make($beneficiary->fresh('biller'))]);
    }

    public function destroy(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        $this->authorizeOwnership($request, $beneficiary);
        $beneficiary->delete();

        return response()->json(['message' => 'Beneficiary removed.']);
    }

    public function toggleFavorite(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        $this->authorizeOwnership($request, $beneficiary);
        $beneficiary->update(['is_favorite' => ! $beneficiary->is_favorite]);

        return response()->json(['data' => BeneficiaryResource::make($beneficiary->fresh('biller'))]);
    }

    protected function authorizeOwnership(Request $request, Beneficiary $beneficiary): void
    {
        abort_unless($beneficiary->user_id === $request->user()->id, 403);
    }
}
