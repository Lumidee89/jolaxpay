<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Wallet\LedgerService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Password reset, device de-auth, fraud flags (User Journey §7). */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%")
            ))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('Admin/Users/Show', [
            'targetUser' => $user->load(['wallets', 'transactions' => fn ($q) => $q->latest()->limit(10)]),
            'sessions' => $user->tokens->map(fn ($t) => [
                'id' => $t->id,
                'device_name' => $t->name,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
            ]),
        ]);
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $temporary = Str::random(12);
        $user->update(['password' => $temporary]);

        // In production this would be delivered via the Notifications
        // domain, not surfaced in the response — flashed here only
        // because this is a staff-facing admin action.
        return back()->with('success', "Temporary password issued: {$temporary}");
    }

    public function revokeSession(User $user, int $tokenId): RedirectResponse
    {
        $user->tokens()->whereKey($tokenId)->delete();

        return back()->with('success', 'Device session revoked.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Account reactivated.' : 'Account deactivated (fraud flag).');
    }
}
