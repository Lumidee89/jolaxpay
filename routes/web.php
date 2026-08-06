<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Admin web app (Inertia + TSX, session auth) — TRD §2.2
|--------------------------------------------------------------------------
| This app has no public customer-facing surface: the only client for
| routes/api.php is the mobile app, and the only client here is JolaxPay
| staff. There is deliberately no self-serve registration (routes/auth.php)
| — see App\Console\Commands\CreateAdminUser.
*/
Route::get('/', fn () => redirect()->route('login'));

Route::prefix('admin')->group(function () {
    require __DIR__.'/auth.php';

    // 'view-dashboard' is held by every staff role (RolesAndPermissionsSeeder)
    // — this is the blanket "are you staff at all" gate for the admin area;
    // individual actions below layer on their own narrower permission.
    Route::middleware(['auth', 'verified', 'permission:view-dashboard'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('transactions', [TransactionController::class, 'index'])->name('admin.transactions.index');
        Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('admin.transactions.show');
        Route::post('transactions/{transaction}/retry', [TransactionController::class, 'retry'])
            ->middleware('permission:manage-transactions')->name('admin.transactions.retry');
        Route::post('transactions/{transaction}/refund', [TransactionController::class, 'refund'])
            ->middleware('permission:manage-transactions')->name('admin.transactions.refund');

        Route::middleware('permission:manage-providers')->group(function () {
            Route::get('providers', [ProviderController::class, 'index'])->name('admin.providers.index');
            Route::patch('providers/{disco}/health', [ProviderController::class, 'updateHealth'])->name('admin.providers.update-health');
        });

        Route::middleware('permission:manage-support')->group(function () {
            Route::get('support', [SupportTicketController::class, 'index'])->name('admin.support.index');
            Route::get('support/{supportTicket}', [SupportTicketController::class, 'show'])->name('admin.support.show');
            Route::post('support/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->name('admin.support.reply');
            Route::patch('support/{supportTicket}/status', [SupportTicketController::class, 'updateStatus'])->name('admin.support.update-status');
        });

        Route::middleware('permission:manage-referrals')->group(function () {
            Route::get('referrals', [ReferralController::class, 'index'])->name('admin.referrals.index');
            Route::post('referrals/{referral}/flag', [ReferralController::class, 'flag'])->name('admin.referrals.flag');
            Route::post('referrals/{referral}/approve', [ReferralController::class, 'approve'])->name('admin.referrals.approve');
        });

        Route::middleware('permission:manage-users')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
            Route::get('users/{user}', [UserController::class, 'show'])->name('admin.users.show');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
            Route::delete('users/{user}/sessions/{tokenId}', [UserController::class, 'revokeSession'])->name('admin.users.revoke-session');
            Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('admin.users.toggle-active');
        });

        Route::middleware('permission:view-reconciliation')->group(function () {
            Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('admin.reconciliation.index');
        });

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

// Realtime channel auth for the admin/Inertia client (TRD §3, §8) — the
// mobile client gets its own at /api/v1/broadcasting/auth (routes/api.php),
// guarded by Sanctum instead.
Broadcast::routes();
