<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ledger vs. processor/provider settlement (User Journey §7). A
 * simplified daily rollup for Phase 1 — a real settlement-file import
 * (per processor/DisCo) is Phase 2 scope.
 */
class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from') ?? now()->subDays(7)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $daily = Transaction::selectRaw("DATE(created_at) as date, COUNT(*) as transaction_count, SUM(amount + fee) as gross_amount")
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'outcome_confirmed')
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();

        $ledgerTotals = LedgerEntry::selectRaw('type, reason, SUM(amount) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('type', 'reason')
            ->get();

        return Inertia::render('Admin/Reconciliation/Index', [
            'daily' => $daily,
            'ledgerTotals' => $ledgerTotals,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
