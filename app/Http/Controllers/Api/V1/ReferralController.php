<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Referrals\ReferralService;
use App\Http\Resources\ReferralResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Power Circle Rewards (TRD §4, §10). */
class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function index(Request $request): JsonResponse
    {
        $referrals = $request->user()->referralsMade()->with('referredUser')->latest()->get();

        abort_unless($request->user()->isAgentAccount(), 403, 'Referral Centre is only available to Agents.');
        $code = $this->referrals->ensureAgentCode($request->user());

        return response()->json([
            'data' => ReferralResource::collection($referrals),
            'my_code' => $code,
        ]);
    }

    /** Returns the Agent's reusable referral code. */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAgentAccount(), 403, 'Referral Centre is only available to Agents.');

        return response()->json(['my_code' => $this->referrals->ensureAgentCode($request->user())]);
    }
}
