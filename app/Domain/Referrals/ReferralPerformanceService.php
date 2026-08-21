<?php

namespace App\Domain\Referrals;

use App\Enums\AccountType;
use App\Enums\TransactionStatus;
use App\Models\AgentCommission;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ReferralPerformanceService
{
    /** @return array{0: CarbonInterface, 1: CarbonInterface, 2: string} */
    public function period(string $preset = 'month', ?string $from = null, ?string $to = null): array
    {
        $now = now();

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today'],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'This week'],
            'previous_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'Previous month'],
            'custom' => [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay(), 'Custom range'],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'This month'],
        };
    }

    public function leaderboard(CarbonInterface $from, CarbonInterface $to, string $metric = 'active'): Collection
    {
        $agents = User::where('account_type', AccountType::Agent->value)->whereNotNull('agent_approved_at')->get();

        $rows = $agents->map(fn (User $agent) => $this->row($agent, $from, $to));
        $sortKey = $metric === 'total' ? 'total_referrals' : 'active_referrals';

        return $rows->sortByDesc($sortKey)->values()->map(function (array $row, int $index) {
            $row['position'] = $index + 1;

            return $row;
        });
    }

    public function row(User $agent, CarbonInterface $from, CarbonInterface $to): array
    {
        $referrals = Referral::where('referrer_id', $agent->id)
            ->whereBetween('attributed_at', [$from, $to]);
        $referredIds = (clone $referrals)->whereNotNull('referred_user_id')->pluck('referred_user_id');
        $successful = [TransactionStatus::Delivered->value, TransactionStatus::OutcomeConfirmed->value];

        return [
            'agent_id' => $agent->id,
            'merchant_id' => 'AGT-'.str_pad((string) $agent->id, 6, '0', STR_PAD_LEFT),
            'name' => $agent->full_name,
            'masked_name' => $this->mask($agent->full_name),
            'total_referrals' => (clone $referrals)->count(),
            'active_referrals' => (clone $referrals)->whereNotNull('activated_at')->count(),
            'referral_transactions' => Transaction::whereIn('user_id', $referredIds)->whereIn('status', $successful)->whereBetween('created_at', [$from, $to])->count(),
            'referral_earnings' => (float) AgentCommission::where('agent_id', $agent->id)->where('earning_type', 'referral')->whereIn('status', ['available', 'paid'])->whereBetween('created_at', [$from, $to])->sum('amount'),
            'direct_earnings' => (float) AgentCommission::where('agent_id', $agent->id)->where('earning_type', 'direct')->whereIn('status', ['available', 'paid'])->whereBetween('created_at', [$from, $to])->sum('amount'),
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ];
    }

    public function dashboard(User $agent): array
    {
        [$from, $to] = $this->period('month');
        [$previousFrom, $previousTo] = $this->period('previous_month');
        $settings = ReferralSetting::current();
        $board = $this->leaderboard($from, $to, $settings->ranking_metric);
        $current = $board->firstWhere('agent_id', $agent->id) ?? [...$this->row($agent, $from, $to), 'position' => null];
        $previous = $this->leaderboard($previousFrom, $previousTo, $settings->ranking_metric)->firstWhere('agent_id', $agent->id);
        $leader = $board->first();
        $metricKey = $settings->ranking_metric === 'total' ? 'total_referrals' : 'active_referrals';
        $score = (int) $current[$metricKey];
        $gap = max(0, (int) ($leader[$metricKey] ?? 0) - $score);
        $milestones = collect($settings->milestones ?? [])->sortBy('threshold')->values();
        $next = $milestones->first(fn ($item) => (int) ($item['threshold'] ?? 0) > $score);

        return [
            'my_code' => $agent->referral_code,
            'share_link' => rtrim((string) config('app.mobile_url', 'https://jolaxpay.com/register'), '/').'?ref='.$agent->referral_code,
            'current' => $current,
            'previous_position' => $previous['position'] ?? null,
            'lifetime_referrals' => Referral::where('referrer_id', $agent->id)->count(),
            'lifetime_active_referrals' => Referral::where('referrer_id', $agent->id)->whereNotNull('activated_at')->count(),
            'message' => $this->message($current['position'], $previous['position'] ?? null, $gap, $score, $next),
            'next_milestone' => $next ? [
                'name' => $next['name'],
                'threshold' => (int) $next['threshold'],
                'progress' => $score,
                'remaining' => max(0, (int) $next['threshold'] - $score),
            ] : null,
            'leaderboard_enabled' => $settings->leaderboard_enabled,
            'leaderboard' => $settings->leaderboard_enabled ? $board->take($settings->visible_positions)->map(fn ($row) => [
                'position' => $row['position'],
                'agent_id' => $row['agent_id'],
                'name' => $row['agent_id'] === $agent->id ? 'You' : $row['masked_name'],
                'total_referrals' => $row['total_referrals'],
                'active_referrals' => $row['active_referrals'],
            ])->values() : [],
            'ranking_metric' => $settings->ranking_metric,
            'promotional_message' => $settings->promotional_message,
        ];
    }

    private function message(?int $position, ?int $previous, int $gap, int $score, ?array $next): string
    {
        if ($position === 1) {
            return '🏆 You are currently #1 this month. Keep referring to maintain your position.';
        }
        if ($previous && $position && $position < $previous) {
            return "🚀 You moved from #{$previous} to #{$position}. Keep going!";
        }
        if ($gap > 0) {
            return "🔥 You are only {$gap} active referral".($gap === 1 ? '' : 's').' away from #1.';
        }
        if ($next) {
            return 'Only '.max(0, (int) $next['threshold'] - $score).' more active referrals to reach '.$next['name'].'.';
        }

        return 'Keep sharing your referral link to grow your network.';
    }

    private function mask(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))->map(fn ($part) => mb_substr($part, 0, 1).str_repeat('*', max(2, mb_strlen($part) - 1)))->implode(' ');
    }
}
