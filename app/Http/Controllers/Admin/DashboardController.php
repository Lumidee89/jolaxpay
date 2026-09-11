<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payments\PaystackChargeReconciler;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Biller;
use App\Models\Disco;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\WalletFundingIntent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** Overview: volumes, success rate, alerts (User Journey §7). */
class DashboardController extends Controller
{
    public function index(Request $request): Response
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
                'degraded_providers' => Disco::where('health_status', '!=', 'healthy')->where('is_active', true)->count()
                    + Biller::where('health_status', '!=', 'healthy')->where('is_active', true)->count(),
                'pending_paystack_fundings' => WalletFundingIntent::where('status', 'pending')
                    ->where('meta->provider', 'paystack')
                    ->count(),
            ],
            'recentTransactions' => Transaction::with('user:id,full_name')
                ->latest()
                ->limit(10)
                ->get(['id', 'reference', 'user_id', 'status', 'amount', 'currency', 'created_at']),
            'canReconcilePaystack' => $request->user()->can('view-reconciliation'),
        ]);
    }

    public function reconcilePaystack(Request $request, PaystackChargeReconciler $reconciler): RedirectResponse
    {
        $intents = WalletFundingIntent::query()
            ->where('status', 'pending')
            ->where('meta->provider', 'paystack')
            ->oldest()
            ->limit(100)
            ->get();

        $credited = 0;
        foreach ($intents as $intent) {
            $reconciler->reconcile($intent->reference);
            if ($intent->fresh()->status === 'success') {
                $credited++;
            }
        }

        Log::notice('Admin triggered Paystack wallet-funding reconciliation', [
            'admin_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'verified' => $intents->count(),
            'credited' => $credited,
        ]);

        $message = "Checked {$intents->count()} pending Paystack payment(s); {$credited} wallet(s) credited.";

        return back()->with($credited > 0 ? 'success' : 'error', $credited > 0
            ? $message
            : $message.' If Paystack shows a successful payment, confirm this server contains its fund reference and has the current live secret key.');
    }
}
