<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Referrals\ReferralPerformanceService;
use App\Domain\Referrals\ReferralService;
use App\Http\Controllers\Controller;
use App\Models\AgentCommission;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentReferralController extends Controller
{
    public function __construct(private readonly ReferralPerformanceService $performance, private readonly ReferralService $referrals) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->agentOnly($request);
        $this->referrals->ensureAgentCode($request->user());

        $earnings = AgentCommission::where('agent_id', $request->user()->id)
            ->selectRaw("SUM(CASE WHEN earning_type = 'direct' AND status IN ('available','paid') THEN amount ELSE 0 END) direct")
            ->selectRaw("SUM(CASE WHEN earning_type = 'referral' AND status IN ('available','paid') THEN amount ELSE 0 END) referral")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) pending")
            ->selectRaw("SUM(CASE WHEN status = 'available' THEN amount ELSE 0 END) available")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) paid")
            ->first();

        return response()->json(['data' => [
            ...$this->performance->dashboard($request->user()->fresh()),
            'earnings' => [
                'direct' => (float) ($earnings->direct ?? 0), 'referral' => (float) ($earnings->referral ?? 0),
                'total' => (float) ($earnings->direct ?? 0) + (float) ($earnings->referral ?? 0),
                'pending' => (float) ($earnings->pending ?? 0), 'available' => (float) ($earnings->available ?? 0), 'paid' => (float) ($earnings->paid ?? 0),
            ],
        ]]);
    }

    public function customers(Request $request): JsonResponse
    {
        $this->agentOnly($request);
        $rows = Referral::with('referredUser:id,full_name,phone_number,created_at')
            ->where('referrer_id', $request->user()->id)->latest('attributed_at')->paginate(25);
        $rows->getCollection()->transform(fn (Referral $referral) => [
            'id' => $referral->id,
            'name' => $this->mask($referral->referredUser?->full_name ?? 'Personal User'),
            'identifier' => $this->maskPhone($referral->referredUser?->phone_number),
            'date_joined' => $referral->attributed_at?->toIso8601String(),
            'active' => $referral->activated_at !== null,
            'eligible_transactions' => AgentCommission::where('agent_id', $request->user()->id)->where('referred_user_id', $referral->referred_user_id)->where('earning_type', 'referral')->count(),
            'earnings_generated' => (float) AgentCommission::where('agent_id', $request->user()->id)->where('referred_user_id', $referral->referred_user_id)->where('earning_type', 'referral')->whereIn('status', ['available', 'paid'])->sum('amount'),
        ]);

        return response()->json($rows);
    }

    public function commissions(Request $request): JsonResponse
    {
        $this->agentOnly($request);

        return response()->json(AgentCommission::where('agent_id', $request->user()->id)->with('transaction:id,reference,service_type,created_at')->latest()->paginate(30));
    }

    private function agentOnly(Request $request): void
    {
        abort_unless($request->user()->isAgentAccount(), 403, 'This is only available to Agent accounts.');
    }

    private function mask(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))->map(fn ($part) => mb_substr($part, 0, 1).str_repeat('*', max(2, mb_strlen($part) - 1)))->implode(' ');
    }

    private function maskPhone(?string $phone): ?string
    {
        return $phone && strlen($phone) >= 7 ? substr($phone, 0, 3).str_repeat('*', strlen($phone) - 6).substr($phone, -3) : null;
    }
}
