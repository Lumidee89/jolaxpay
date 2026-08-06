<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeterController;
use App\Http\Controllers\Api\V1\MeterGroupController;
use App\Http\Controllers\Api\V1\PowerCircleController;
use App\Http\Controllers\Api\V1\ProviderStatusController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\ScheduledPurchaseController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WalletController;
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
    Route::get('providers/status', [ProviderStatusController::class, 'index']);

    // --- Authenticated (Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/{tokenId}', [SessionController::class, 'destroy']);

        Route::apiResource('meters', MeterController::class);
        Route::patch('meters/{meter}/favorite', [MeterController::class, 'toggleFavorite']);
        Route::post('meters/verify', [MeterController::class, 'verify']);

        Route::apiResource('meter-groups', MeterGroupController::class)->only(['index', 'store', 'show', 'destroy']);

        Route::apiResource('power-circle', PowerCircleController::class)->only(['index', 'store', 'destroy']);

        Route::get('transactions', [TransactionController::class, 'index']);
        Route::post('transactions', [TransactionController::class, 'store'])->middleware('idempotent');
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
        Route::get('transactions/{transaction}/status', [TransactionController::class, 'status']);
        Route::post('transactions/{transaction}/outcome', [TransactionController::class, 'confirmOutcome']);
        Route::get('transactions/{transaction}/receipt', [TransactionController::class, 'receipt']);

        Route::apiResource('scheduled-purchases', ScheduledPurchaseController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('wallet', [WalletController::class, 'show']);
        Route::post('wallet/fund', [WalletController::class, 'fund']);

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
