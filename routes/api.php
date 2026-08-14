<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BeneficiaryController;
use App\Http\Controllers\Api\V1\BusinessLedgerController;
use App\Http\Controllers\Api\V1\DevicePushTokenController;
use App\Http\Controllers\Api\V1\BillerController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\InsightController;
use App\Http\Controllers\Api\V1\MeterController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\MeterGroupController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\PowerCircleController;
use App\Http\Controllers\Api\V1\ProviderStatusController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\ScheduledPurchaseController;
use App\Http\Controllers\Api\V1\SafeHavenWebhookController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile-facing API (TRD §3)
|--------------------------------------------------------------------------
| Stateless JSON under /api/v1, Sanctum personal-access-token auth. This
| is the only surface the React Native (Expo) app talks to — the Inertia
| admin (routes/web.php) is a fully separate, session-guarded surface.
*/
Route::prefix('v1')->group(function () {

    // --- Public ---
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('auth/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('providers/status', [ProviderStatusController::class, 'index']);
    Route::get('faq', [FaqController::class, 'index']);

    // Safe Haven notifies this endpoint about virtual-account and checkout payments.
    Route::post('webhooks/safehaven', [SafeHavenWebhookController::class, 'handle']);

    // --- Authenticated (Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/avatar', [AuthController::class, 'uploadAvatar']);
        Route::post('devices/push-tokens', [DevicePushTokenController::class, 'store']);
        Route::delete('devices/push-tokens', [DevicePushTokenController::class, 'destroy']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'markAllRead']);
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'index']);
        Route::patch('notification-preferences', [NotificationPreferenceController::class, 'update']);

        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/{tokenId}', [SessionController::class, 'destroy']);

        Route::get('business/entries', [BusinessLedgerController::class, 'index']);
        Route::post('business/entries', [BusinessLedgerController::class, 'store']);
        Route::delete('business/entries/{businessLedgerEntry}', [BusinessLedgerController::class, 'destroy']);
        Route::get('business/summary', [BusinessLedgerController::class, 'summary']);

        Route::get('insights', [InsightController::class, 'index']);
        Route::get('insights/summary', [InsightController::class, 'summary']);
        Route::get('insights/suggested-amount', [InsightController::class, 'suggestedAmount']);
        Route::get('insights/suggested-top-up', [InsightController::class, 'suggestedTopUp']);
        Route::post('insights/engagement', [InsightController::class, 'engagement']);

        Route::apiResource('meters', MeterController::class);
        Route::patch('meters/{meter}/favorite', [MeterController::class, 'toggleFavorite']);
        Route::post('meters/verify', [MeterController::class, 'verify']);

        Route::apiResource('meter-groups', MeterGroupController::class)->only(['index', 'store', 'show', 'destroy']);

        // Airtime/data/cable_tv/education — the biller-anchored services
        // (ServiceType::isBillerBased()). `billers` is a public-ish catalog
        // read (network/provider list + cached bundle prices); `beneficiaries`
        // are the per-user saved recipients, mirroring `meters` above.
        Route::get('billers', [BillerController::class, 'index']);
        Route::post('billers/verify', [BillerController::class, 'verify']);

        Route::apiResource('beneficiaries', BeneficiaryController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::patch('beneficiaries/{beneficiary}/favorite', [BeneficiaryController::class, 'toggleFavorite']);

        Route::apiResource('power-circle', PowerCircleController::class)->only(['index', 'store', 'destroy']);

        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/search', [TransactionController::class, 'search']);
        Route::post('transactions', [TransactionController::class, 'store'])->middleware('idempotent');
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
        Route::get('transactions/{transaction}/status', [TransactionController::class, 'status']);
        Route::post('transactions/{transaction}/outcome', [TransactionController::class, 'confirmOutcome']);
        Route::get('transactions/{transaction}/receipt', [TransactionController::class, 'receipt']);
        Route::post('transactions/{transaction}/repeat', [TransactionController::class, 'repeat'])->middleware('idempotent');

        Route::apiResource('scheduled-purchases', ScheduledPurchaseController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('wallet', [WalletController::class, 'show']);
        Route::post('wallet/fund', [WalletController::class, 'fund']);
        Route::get('wallet/fund/{reference}/status', [WalletController::class, 'fundingStatus']);
        Route::post('wallet/transfer', [WalletController::class, 'transfer']);
        Route::get('wallet/entries/{ledgerEntry}/receipt', [WalletController::class, 'entryReceipt']);

        Route::get('withdrawals', [WithdrawalController::class, 'index']);
        Route::post('withdrawals', [WithdrawalController::class, 'store']);
        Route::get('withdrawals/banks', [WithdrawalController::class, 'banks']);
        Route::post('withdrawals/resolve-account', [WithdrawalController::class, 'resolveAccount']);

        Route::get('referrals', [ReferralController::class, 'index']);
        Route::post('referrals', [ReferralController::class, 'store']);

        Route::get('support/tickets', [SupportTicketController::class, 'index']);
        Route::post('support/tickets', [SupportTicketController::class, 'store']);
        Route::get('support/tickets/{supportTicket}', [SupportTicketController::class, 'show']);
        Route::post('support/tickets/{supportTicket}/messages', [SupportTicketController::class, 'addMessage']);
    });

    // Realtime channel auth for the mobile client (TRD §3, §8) — the
    // admin/Inertia side gets its own at /broadcasting/auth (routes/web.php),
    // guarded by the session guard instead.
    Broadcast::routes(['middleware' => ['auth:sanctum']]);
});
