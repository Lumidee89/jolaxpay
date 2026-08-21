<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Referrals\ReferralPerformanceService;
use App\Enums\AccountType;
use App\Enums\DeliveryChannel;
use App\Http\Controllers\Controller;
use App\Models\AgentReward;
use App\Models\Biller;
use App\Models\CommissionRule;
use App\Models\Disco;
use App\Models\Referral;
use App\Models\ReferralCampaign;
use App\Models\ReferralSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralPerformanceService $performance, private readonly NotificationDispatcher $notifier) {}

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:today,week,month,previous_month,custom'], 'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:period,custom'], 'metric' => ['nullable', 'in:total,active'], 'status' => ['nullable', 'string'],
        ]);
        [$from, $to, $label] = $this->performance->period($data['period'] ?? 'month', $data['from'] ?? null, $data['to'] ?? null);
        $metric = $data['metric'] ?? ReferralSetting::current()->ranking_metric;

        return Inertia::render('Admin/Referrals/Index', [
            'leaderboard' => $this->performance->leaderboard($from, $to, $metric),
            'dateRange' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $label],
            'filters' => [...$data, 'metric' => $metric],
            'referrals' => Referral::with(['referrer:id,full_name,email', 'referredUser:id,full_name,email'])->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(25)->withQueryString(),
            'settings' => ReferralSetting::current(), 'rules' => CommissionRule::latest()->get(), 'campaigns' => ReferralCampaign::latest('starts_at')->get(),
            'rewards' => AgentReward::with('agent:id,full_name')->latest()->limit(50)->get(),
            'billers' => Biller::orderBy('name')->get(['id', 'name', 'service_type']), 'discos' => Disco::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        CommissionRule::create($request->validate([
            'name' => ['required', 'string', 'max:100'], 'earning_type' => ['required', 'in:direct,referral'], 'service_type' => ['nullable', 'in:electricity,airtime,data,cable_tv,education'],
            'biller_id' => ['nullable', 'exists:billers,id'], 'disco_id' => ['nullable', 'exists:discos,id'], 'calculation_type' => ['required', 'in:fixed,percentage'],
            'value' => ['required', 'numeric', 'min:0'], 'jolaxpay_margin' => ['nullable', 'numeric', 'min:0'], 'minimum_commission' => ['nullable', 'numeric', 'min:0'],
            'maximum_commission' => ['nullable', 'numeric', 'gte:minimum_commission'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'Commission rule created.');
    }

    public function updateRule(Request $request, CommissionRule $commissionRule): RedirectResponse
    {
        $commissionRule->update($request->validate(['is_active' => ['required', 'boolean']]));

        return back()->with('success', 'Commission rule updated.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        ReferralSetting::current()->update($request->validate([
            'leaderboard_enabled' => ['required', 'boolean'], 'ranking_metric' => ['required', 'in:total,active'], 'active_min_transactions' => ['required', 'integer', 'min:1'],
            'visible_positions' => ['required', 'integer', 'min:1', 'max:100'], 'ranking_period' => ['required', 'in:weekly,monthly,campaign'], 'milestones' => ['required', 'array'],
            'milestones.*.threshold' => ['required', 'integer', 'min:1'], 'milestones.*.name' => ['required', 'string', 'max:100'], 'promotional_message' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('success', 'Referral settings updated.');
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        ReferralCampaign::create($request->validate([
            'name' => ['required', 'string', 'max:120'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'ranking_metric' => ['required', 'in:total,active'], 'is_active' => ['required', 'boolean'], 'promotional_message' => ['nullable', 'string', 'max:500'], 'reward_details' => ['nullable', 'array'],
        ]));

        return back()->with('success', 'Referral campaign created.');
    }

    public function reward(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agent_ids' => ['required', 'array', 'min:1'], 'agent_ids.*' => ['integer', Rule::exists('users', 'id')->where('account_type', AccountType::Agent->value)],
            'campaign_id' => ['nullable', 'exists:referral_campaigns,id'], 'status' => ['required', 'in:planned,rewarded'], 'period_key' => ['nullable', 'string', 'max:50'],
            'reward' => ['nullable', 'string', 'max:1000'], 'rewarded_at' => ['nullable', 'date'], 'internal_note' => ['nullable', 'string', 'max:2000'], 'notify' => ['boolean'],
        ]);
        User::whereIn('id', $data['agent_ids'])->get()->each(function (User $agent) use ($data, $request) {
            AgentReward::create(['agent_id' => $agent->id, 'campaign_id' => $data['campaign_id'] ?? null, 'issued_by' => $request->user()->id, 'status' => $data['status'],
                'period_key' => $data['period_key'] ?? null, 'reward' => $data['reward'] ?? null,
                'rewarded_at' => $data['status'] === 'rewarded' ? ($data['rewarded_at'] ?? now()) : null,
                'internal_note' => $data['internal_note'] ?? null]);
            if ($data['notify'] ?? false) {
                $this->notifier->send($agent, 'agent_reward', DeliveryChannel::InApp, ['title' => 'Referral performance reward', 'body' => $data['reward'] ?: 'You received a referral performance recognition from JolaxPay.']);
            }
        });

        return back()->with('success', count($data['agent_ids']).' Agent reward record(s) created.');
    }

    public function reassign(Request $request, Referral $referral): RedirectResponse
    {
        $data = $request->validate(['agent_id' => ['required', Rule::exists('users', 'id')->where('account_type', AccountType::Agent->value)], 'note' => ['required', 'string', 'max:2000']]);
        $agent = User::findOrFail($data['agent_id']);
        $referral->update(['referrer_id' => $agent->id, 'code' => $agent->referral_code, 'attribution_changed_by' => $request->user()->id, 'attribution_note' => $data['note']]);

        return back()->with('success', 'Referral attribution reassigned with an audit note.');
    }

    public function flag(Referral $referral): RedirectResponse
    {
        $referral->update(['status' => 'flagged']);

        return back()->with('success', 'Referral flagged.');
    }

    public function approve(Referral $referral): RedirectResponse
    {
        $referral->update(['status' => $referral->activated_at ? 'active' : 'qualified']);

        return back()->with('success', 'Referral approved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:today,week,month,previous_month,custom'],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:period,custom'],
            'metric' => ['nullable', 'in:total,active'],
        ]);
        [$from, $to] = $this->performance->period($data['period'] ?? 'month', $data['from'] ?? null, $data['to'] ?? null);
        $rows = $this->performance->leaderboard($from, $to, $data['metric'] ?? 'active');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Position', 'Agent Name', 'Agent ID', 'Total Referrals', 'Active Referrals', 'Referral Transactions', 'Referral Earnings', 'Direct Earnings', 'From', 'To']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['position'], $row['name'], $row['merchant_id'], $row['total_referrals'], $row['active_referrals'], $row['referral_transactions'], $row['referral_earnings'], $row['direct_earnings'], $row['date_from'], $row['date_to']]);
            }
            fclose($out);
        }, 'agent-referral-leaderboard-'.$from->toDateString().'-'.$to->toDateString().'.csv');
    }
}
