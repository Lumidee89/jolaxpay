<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeterGroupRequest;
use App\Http\Resources\MeterGroupResource;
use App\Models\MeterGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Smart Meter Groups (PRD §7.2) — "Church Headquarters", "Estate", etc. */
class MeterGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groups = $request->user()->meterGroups()->with('meters.disco')->get();

        return response()->json(['data' => MeterGroupResource::collection($groups)]);
    }

    public function store(StoreMeterGroupRequest $request): JsonResponse
    {
        $group = $request->user()->meterGroups()->create($request->validated());

        return response()->json(['data' => MeterGroupResource::make($group)], 201);
    }

    public function show(Request $request, MeterGroup $meterGroup): JsonResponse
    {
        abort_unless($meterGroup->user_id === $request->user()->id, 403);

        return response()->json(['data' => MeterGroupResource::make($meterGroup->load('meters.disco'))]);
    }

    public function destroy(Request $request, MeterGroup $meterGroup): JsonResponse
    {
        abort_unless($meterGroup->user_id === $request->user()->id, 403);
        $meterGroup->delete();

        return response()->json(['message' => 'Group removed.']);
    }
}
