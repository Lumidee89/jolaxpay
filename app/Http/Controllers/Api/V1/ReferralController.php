<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralResource;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Power Circle Rewards (TRD §4, §10). */
class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $referrals = $request->user()->referralsMade()->with('referredUser')->latest()->get();

        $code = $referrals->first()?->code ?? $this->codeFor($request->user());

        return response()->json([
            'data' => ReferralResource::collection($referrals),
            'my_code' => $code,
        ]);
    }

    /** Generates (or returns the existing) referral code for the buyer. */
    public function store(Request $request): JsonResponse
    {
        $existing = Referral::where('referrer_id', $request->user()->id)
            ->whereNull('referred_user_id')
            ->first();

        if ($existing) {
            return response()->json(['data' => ReferralResource::make($existing)]);
        }

        $referral = Referral::create([
            'referrer_id' => $request->user()->id,
            'code' => $this->codeFor($request->user()),
            'status' => 'pending',
        ]);

        return response()->json(['data' => ReferralResource::make($referral)], 201);
    }

    protected function codeFor(\App\Models\User $user): string
    {
        return 'JLX-'.Str::upper(Str::substr(Str::slug($user->full_name), 0, 4)).'-'.Str::upper(Str::random(4));
    }
}
