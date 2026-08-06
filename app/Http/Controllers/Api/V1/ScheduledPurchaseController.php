<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreScheduledPurchaseRequest;
use App\Http\Requests\Api\V1\UpdateScheduledPurchaseRequest;
use App\Http\Resources\ScheduledPurchaseResource;
use App\Models\Meter;
use App\Models\ScheduledPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Scheduled/recurring purchases (PRD §7.4). */
class ScheduledPurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $scheduled = $request->user()->scheduledPurchases()->with('meter')->get();

        return response()->json(['data' => ScheduledPurchaseResource::collection($scheduled)]);
    }

    public function store(StoreScheduledPurchaseRequest $request): JsonResponse
    {
        $meter = Meter::findOrFail($request->validated('meter_id'));
        abort_unless($meter->user_id === $request->user()->id, 403);

        $scheduled = $request->user()->scheduledPurchases()->create($request->validated());

        return response()->json(['data' => ScheduledPurchaseResource::make($scheduled->load('meter'))], 201);
    }

    public function update(UpdateScheduledPurchaseRequest $request, ScheduledPurchase $scheduledPurchase): JsonResponse
    {
        abort_unless($scheduledPurchase->user_id === $request->user()->id, 403);
        $scheduledPurchase->update($request->validated());

        return response()->json(['data' => ScheduledPurchaseResource::make($scheduledPurchase->fresh('meter'))]);
    }

    public function destroy(Request $request, ScheduledPurchase $scheduledPurchase): JsonResponse
    {
        abort_unless($scheduledPurchase->user_id === $request->user()->id, 403);
        $scheduledPurchase->delete();

        return response()->json(['message' => 'Scheduled purchase cancelled.']);
    }
}
