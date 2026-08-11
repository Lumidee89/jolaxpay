<?php

namespace App\Domain\Analytics;

use App\Enums\TransactionStatus;
use App\Models\InsightEngagement;
use App\Models\Referral;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PRD §23 — the metrics the operational Admin/Dashboard doesn't cover
 * (that one is "is anything on fire right now"; this one is "is the
 * product working the way the PRD says it should").
 */
class ProductAnalyticsService
{
    private const SUCCESSFUL = [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed];

    /** % of registered users who have completed at least one successful purchase. */
    public function activationRate(): array
    {
        $totalUsers = User::count();
        $activatedUsers = User::whereHas('transactions', fn ($q) => $q->whereIn('status', self::SUCCESSFUL))->count();

        return [
            'total_users' => $totalUsers,
            'activated_users' => $activatedUsers,
            'rate' => $totalUsers > 0 ? round(($activatedUsers / $totalUsers) * 100, 1) : null,
        ];
    }

    /** Electricity Payment Flow completion rate + average time from fee-disclosed to delivered. */
    public function electricityFlow(): array
    {
        $electricity = Transaction::where('service_type', 'electricity');
        $total = (clone $electricity)->count();
        $completed = (clone $electricity)->whereIn('status', self::SUCCESSFUL)->count();
        $failed = (clone $electricity)->where('status', TransactionStatus::Failed)->count();

        $avgMinutes = (clone $electricity)
            ->whereIn('status', self::SUCCESSFUL)
            ->whereNotNull('delivered_at')
            ->selectRaw($this->avgMinutesExpression('created_at', 'delivered_at').' as avg_minutes')
            ->value('avg_minutes');

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : null,
            'avg_time_to_completion_minutes' => $avgMinutes !== null ? round((float) $avgMinutes, 1) : null,
        ];
    }

    /** Home insight card: how often shown vs. acted on. */
    public function aiInsightEngagement(): array
    {
        $shown = InsightEngagement::where('action', 'shown')->count();
        $clicked = InsightEngagement::where('action', 'clicked')->count();

        return [
            'shown' => $shown,
            'clicked' => $clicked,
            'click_through_rate' => $shown > 0 ? round(($clicked / $shown) * 100, 1) : null,
        ];
    }

    /** Power Circle Rewards: of everyone who's actually redeemed a code (referred_user_id set), how many converted to a reward. */
    public function referralConversionRate(): array
    {
        $linked = Referral::whereNotNull('referred_user_id')->count();
        $rewarded = Referral::where('status', 'rewarded')->count();

        return [
            'linked' => $linked,
            'rewarded' => $rewarded,
            'rate' => $linked > 0 ? round(($rewarded / $linked) * 100, 1) : null,
        ];
    }

    public function supportTicketsByCategory(): array
    {
        return SupportTicket::select('category')
            ->selectRaw('count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'total' => $row->total])
            ->all();
    }

    /** SQLite (tests/local) and MySQL (production) compute an inline average-minutes expression differently. */
    protected function avgMinutesExpression(string $from, string $to): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "AVG((julianday({$to}) - julianday({$from})) * 24 * 60)";
        }

        return "AVG(TIMESTAMPDIFF(SECOND, {$from}, {$to}) / 60)";
    }
}
