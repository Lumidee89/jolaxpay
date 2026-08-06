<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeterRequest;
use App\Http\Requests\Api\V1\UpdateMeterRequest;
use App\Http\Resources\MeterResource;
use App\Models\Meter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Saved Meters (PRD §7.2). */
class MeterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $meters = $request->user()->meters()
            ->with('disco')
            ->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => MeterResource::collection($meters)]);
    }

    public function store(StoreMeterRequest $request): JsonResponse
    {
        $meter = $request->user()->meters()->create($request->validated());

        // create() doesn't reflect column defaults applied at the DB level
        // (meter_type, is_favorite) — refresh so the response is accurate.
        return response()->json(['data' => MeterResource::make($meter->refresh()->load('disco'))], 201);
    }

    public function show(Request $request, Meter $meter): JsonResponse
    {
        $this->authorizeOwnership($request, $meter);

        return response()->json(['data' => MeterResource::make($meter->load('disco'))]);
    }

    public function update(UpdateMeterRequest $request, Meter $meter): JsonResponse
    {
        $this->authorizeOwnership($request, $meter);

        $meter->update($request->validated());

        return response()->json(['data' => MeterResource::make($meter->fresh('disco'))]);
    }

    public function destroy(Request $request, Meter $meter): JsonResponse
    {
        $this->authorizeOwnership($request, $meter);
        $meter->delete();

        return response()->json(['message' => 'Meter removed.']);
    }

    public function toggleFavorite(Request $request, Meter $meter): JsonResponse
    {
        $this->authorizeOwnership($request, $meter);
        $meter->update(['is_favorite' => ! $meter->is_favorite]);

        return response()->json(['data' => MeterResource::make($meter->fresh('disco'))]);
    }

    protected function authorizeOwnership(Request $request, Meter $meter): void
    {
        abort_unless($meter->user_id === $request->user()->id, 403);
    }
}
