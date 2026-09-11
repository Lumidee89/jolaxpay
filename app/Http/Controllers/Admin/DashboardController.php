<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payments\PaystackChargeReconciler;
use App\Domain\Payments\PaystackGateway;
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
            'pendingPaystackFundings' => $request->user()->can('view-reconciliation')
                ? WalletFundingIntent::with('user:id,full_name,email')
                    ->where('status', 'pending')
                    ->where('meta->provider', 'paystack')
                    ->oldest()
                    ->limit(10)
                    ->get(['id', 'user_id', 'reference', 'amount', 'currency', 'created_at'])
                : [],
        ]);
    }

    public function reconcilePaystack(
        Request $request,
        PaystackGateway $paystack,
        PaystackChargeReconciler $reconciler,
    ): RedirectResponse
    {
        $intents = WalletFundingIntent::query()
            ->where('status', 'pending')
            ->where('meta->provider', 'paystack')
            ->oldest()
            ->limit(100)
            ->get();

        $credited = 0;
        $failed = 0;
        $issue = null;
        foreach ($intents as $intent) {
            $providerData = $paystack->verifyTransaction($intent->reference);
            $providerStatus = strtolower((string) ($providerData['status'] ?? 'unknown'));

            if ($providerStatus === 'success') {
                $reconciler->markChargeSuccessful($intent->reference, $providerData);
            } elseif (in_array($providerStatus, ['failed', 'abandoned', 'reversed'], true)) {
                $reconciler->markChargeFailed($intent->reference, $providerData['gateway_response'] ?? null);
            }

            $fresh = $intent->fresh();
            if ($fresh->status === 'success') {
                $credited++;
            } elseif ($fresh->status === 'failed') {
                $failed++;
            } elseif ($providerStatus === 'success') {
                $expected = (int) round((float) $intent->amount * 100);
                $received = isset($providerData['amount']) ? (int) $providerData['amount'] : null;
                $issue ??= $received !== null && $received !== $expected
                    ? "Paystack confirms {$intent->reference}, but its amount (₦".number_format($received / 100, 2).") does not match JolaxPay's pending amount (₦".number_format($expected / 100, 2).'). Credit was safely blocked.'
                    : "Paystack confirms {$intent->reference}, but the credit was blocked. Check the server log for the safety-check reason.";
            } elseif ($providerStatus === 'unknown') {
                $issue ??= "Paystack could not verify {$intent->reference}. The production server may have an old/invalid live secret key cached.";
            } else {
                $issue ??= "Paystack currently reports {$intent->reference} as '{$providerStatus}', so it cannot be credited yet.";
            }
        }

        Log::notice('Admin triggered Paystack wallet-funding reconciliation', [
            'admin_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'verified' => $intents->count(),
            'credited' => $credited,
            'failed' => $failed,
            'issue' => $issue,
        ]);

        $message = "Checked {$intents->count()} pending Paystack payment(s); {$credited} wallet(s) credited; {$failed} marked failed.";

        return back()->with($credited > 0 ? 'success' : 'error', $credited > 0
            ? $message
            : $message.' '.($issue ?: 'There are no pending Paystack payments to reconcile.'));
    }
}
