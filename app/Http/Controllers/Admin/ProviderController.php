<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Disco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Per-DisCo success rate & response time (User Journey §7). */
class ProviderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Providers/Health', [
            'providers' => Disco::withCount(['meters', 'transactions'])
                ->withCount(['transactions as successful_transactions_count' => fn ($q) => $q->where('status', 'outcome_confirmed')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateHealth(Request $request, Disco $disco): RedirectResponse
    {
        $request->validate(['health_status' => 'required|in:healthy,degraded,down,unknown']);

        $disco->update([
            'health_status' => $request->input('health_status'),
            'health_checked_at' => now(),
        ]);

        return back()->with('success', "{$disco->name} marked {$request->input('health_status')}.");
    }
}
