<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Disco;
use App\Models\SupportTicket;
use App\Models\Transaction;
use Inertia\Inertia;
use Inertia\Response;

/** Overview: volumes, success rate, alerts (User Journey §7). */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = Transaction::whereDate('created_at', today());
        $totalToday = (clone $today)->count();
        $completedToday = (clone $today)->where('status', TransactionStatus::OutcomeConfirmed->value)->count();
        $failedToday = (clone $today)->where('status', TransactionStatus::Failed->value)->count();
        $inFlightToday = $totalToday - $completedToday - $failedToday;

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'transactions_today' => $totalToday,
                'completed_today' => $completedToday,
                'failed_today' => $failedToday,
                'in_flight_today' => $inFlightToday,
                'success_rate' => $totalToday > 0 ? round(($completedToday / $totalToday) * 100, 1) : null,
                'open_tickets' => SupportTicket::where('status', 'open')->count(),
                'stuck_transactions' => Transaction::whereNotIn('status', [
                    TransactionStatus::OutcomeConfirmed->value,
                    TransactionStatus::Failed->value,
                ])->where('updated_at', '<', now()->subMinutes(15))->count(),
                'degraded_providers' => Disco::where('health_status', '!=', 'healthy')->where('is_active', true)->count(),
            ],
            'recentTransactions' => Transaction::with('user:id,full_name')
                ->latest()
                ->limit(10)
                ->get(['id', 'reference', 'user_id', 'status', 'amount', 'currency', 'created_at']),
        ]);
    }
}
