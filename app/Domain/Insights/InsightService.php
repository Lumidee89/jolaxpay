<?php

namespace App\Domain\Insights;

use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Enums\ServiceType;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;

/**
 * PRD §6/§9/§17 "AI" behavior — implemented as rules-based heuristics over
 * the user's own history rather than an LLM call (no external AI provider
 * is configured for this project). Everything here is plain arithmetic and
 * template copy: a suggested amount is an average of past purchases, a
 * "summary" is a formatted aggregate, and the Home insight card just picks
 * the single most relevant one of a few fixed rules.
 */
class InsightService
{
    private const SUCCESSFUL = [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed];

    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * One card for Home — priority order: low balance, a recharge that's
     * "about due" based on the user's own cadence, then a monthly recap.
     * Returns null if none of the rules have anything worth surfacing yet
     * (e.g. a brand-new account with no history).
     */
    public function homeInsight(User $user): ?array
    {
        return $this->lowBalanceInsight($user)
            ?? $this->repeatDueInsight($user)
            ?? $this->spendingSummaryInsight($user);
    }

    protected function lowBalanceInsight(User $user): ?array
    {
        $wallet = $this->ledger->walletFor($user);
        $lastPurchase = Transaction::where('user_id', $user->id)
            ->whereIn('status', self::SUCCESSFUL)
            ->latest('created_at')
            ->first();

        if (! $lastPurchase) {
            return null;
        }

        $usualAmount = (float) $this->recentAverage($user, $lastPurchase->service_type->value, $lastPurchase->meter_id, $lastPurchase->biller_id)
            ?? (float) $lastPurchase->total();

        if ($usualAmount <= 0 || (float) $wallet->balance >= $usualAmount) {
            return null;
        }

        return [
            'type' => 'low_balance',
            'title' => 'Your wallet balance is low',
            'body' => sprintf(
                'Your balance is ₦%s, below your usual ₦%s recharge. Top up so you\'re not caught short.',
                number_format((float) $wallet->balance, 2),
                number_format($usualAmount, 2),
            ),
            'action' => ['type' => 'top_up'],
        ];
    }

    protected function repeatDueInsight(User $user): ?array
    {
        $recent = Transaction::where('user_id', $user->id)
            ->whereIn('status', self::SUCCESSFUL)
            ->where('service_type', ServiceType::Electricity)
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'created_at', 'meter_id']);

        if ($recent->count() < 3) {
            return null;
        }

        // Average gap between consecutive purchases establishes this
        // user's own cadence — "about due" means at least that long has
        // passed since the last one.
        $gaps = [];
        for ($i = 0; $i < $recent->count() - 1; $i++) {
            $gaps[] = $recent[$i]->created_at->diffInHours($recent[$i + 1]->created_at);
        }
        $averageGapHours = array_sum($gaps) / count($gaps);
        $hoursSinceLast = $recent->first()->created_at->diffInHours(now());

        if ($hoursSinceLast < $averageGapHours) {
            return null;
        }

        $days = max(1, (int) round($averageGapHours / 24));

        return [
            'type' => 'repeat_due',
            'title' => 'Time for your next recharge?',
            'body' => "You usually recharge about every {$days} day(s), and it's been longer than that since your last one. Repeat your last purchase in one tap.",
            'action' => ['type' => 'repeat', 'transaction_id' => $recent->first()->id],
        ];
    }

    protected function spendingSummaryInsight(User $user): ?array
    {
        $summary = $this->monthlySummary($user);

        if ($summary['this_month_total'] <= 0) {
            return null;
        }

        return [
            'type' => 'spending_summary',
            'title' => 'Your month so far',
            'body' => $summary['sentence'],
            'action' => ['type' => 'history'],
        ];
    }

    /** Plain-language month-over-month spend comparison — GET /insights/summary. */
    public function monthlySummary(User $user): array
    {
        $thisMonthTotal = (float) Transaction::where('user_id', $user->id)
            ->whereIn('status', self::SUCCESSFUL)
            ->whereBetween('created_at', [now()->startOfMonth(), now()])
            ->selectRaw('COALESCE(SUM(amount + fee), 0) as total')
            ->value('total');

        $lastMonthTotal = (float) Transaction::where('user_id', $user->id)
            ->whereIn('status', self::SUCCESSFUL)
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->selectRaw('COALESCE(SUM(amount + fee), 0) as total')
            ->value('total');

        $count = Transaction::where('user_id', $user->id)
            ->whereIn('status', self::SUCCESSFUL)
            ->whereBetween('created_at', [now()->startOfMonth(), now()])
            ->count();

        $sentence = "You've spent ₦".number_format($thisMonthTotal, 2)." this month across {$count} purchase(s).";
        if ($lastMonthTotal > 0) {
            $change = (($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100;
            $direction = $change >= 0 ? 'more' : 'less';
            $sentence .= ' That\'s '.number_format(abs($change), 0)."% {$direction} than last month.";
        }

        return [
            'this_month_total' => $thisMonthTotal,
            'last_month_total' => $lastMonthTotal,
            'purchase_count' => $count,
            'sentence' => $sentence,
        ];
    }

    /**
     * Suggested purchase amount for the Purchase screen's amount field —
     * average of the user's own last 5 matching purchases, or a sensible
     * flat default when they have no history to go on yet.
     */
    public function suggestedPurchaseAmount(User $user, string $serviceType, ?int $meterId = null, ?int $billerId = null): float
    {
        $average = $this->recentAverage($user, $serviceType, $meterId, $billerId);

        if ($average !== null) {
            return $average;
        }

        return match ($serviceType) {
            'electricity' => 5000.0,
            'airtime' => 1000.0,
            'data' => 2000.0,
            'cable_tv' => 4000.0,
            'education' => 1500.0,
            default => 2000.0,
        };
    }

    protected function recentAverage(User $user, string $serviceType, ?int $meterId, ?int $billerId): ?float
    {
        $query = Transaction::where('user_id', $user->id)
            ->whereIn('status', self::SUCCESSFUL)
            ->where('service_type', $serviceType);

        if ($meterId) {
            $query->where('meter_id', $meterId);
        } elseif ($billerId) {
            $query->where('biller_id', $billerId);
        }

        $amounts = $query->latest('created_at')->limit(5)->pluck('amount');

        if ($amounts->isEmpty()) {
            return null;
        }

        return round((float) $amounts->avg(), -2); // nearest ₦100
    }

    /** Suggested wallet top-up amount — average of the user's own last 5 successful fundings. */
    public function suggestedTopUpAmount(User $user): float
    {
        $wallet = $this->ledger->walletFor($user);

        $amounts = $wallet->ledgerEntries()
            ->where('reason', LedgerReason::WalletFunding)
            ->latest('created_at')
            ->limit(5)
            ->pluck('amount');

        if ($amounts->isEmpty()) {
            return 2000.0;
        }

        return round((float) $amounts->avg(), -2);
    }
}
