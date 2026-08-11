<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Insights\InsightService;
use App\Http\Controllers\Controller;
use App\Models\InsightEngagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** PRD §6/§9/§17 — rules-based "AI" insights (see InsightService's docblock). */
class InsightController extends Controller
{
    public function __construct(private readonly InsightService $insights) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->insights->homeInsight($request->user())]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->insights->monthlySummary($request->user())]);
    }

    public function suggestedAmount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_type' => ['required', 'string'],
            'meter_id' => ['nullable', 'integer'],
            'biller_id' => ['nullable', 'integer'],
        ]);

        $amount = $this->insights->suggestedPurchaseAmount(
            $request->user(),
            $data['service_type'],
            $data['meter_id'] ?? null,
            $data['biller_id'] ?? null,
        );

        return response()->json(['data' => ['amount' => $amount]]);
    }

    public function suggestedTopUp(Request $request): JsonResponse
    {
        return response()->json(['data' => ['amount' => $this->insights->suggestedTopUpAmount($request->user())]]);
    }

    /** PRD §23 "AI insight engagement" — best-effort ping, see InsightEngagement's docblock. */
    public function engagement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'insight_type' => ['required', 'string', 'max:50'],
            'action' => ['required', 'in:shown,clicked'],
        ]);

        InsightEngagement::create([
            'user_id' => $request->user()->id,
            'insight_type' => $data['insight_type'],
            'action' => $data['action'],
        ]);

        return response()->json(['data' => ['recorded' => true]], 201);
    }
}
