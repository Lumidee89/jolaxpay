<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePowerCircleRequest;
use App\Http\Resources\PowerCircleResource;
use App\Models\PowerCircleContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Power Circle (PRD §7.2) — trusted people/organisations a user buys for. */
class PowerCircleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contacts = $request->user()->powerCircle()->with('linkedMeter')->get();

        return response()->json(['data' => PowerCircleResource::collection($contacts)]);
    }

    public function store(StorePowerCircleRequest $request): JsonResponse
    {
        $contact = $request->user()->powerCircle()->create($request->validated());

        return response()->json(['data' => PowerCircleResource::make($contact->load('linkedMeter'))], 201);
    }

    public function destroy(Request $request, PowerCircleContact $powerCircle): JsonResponse
    {
        abort_unless($powerCircle->user_id === $request->user()->id, 403);
        $powerCircle->delete();

        return response()->json(['message' => 'Contact removed.']);
    }
}
