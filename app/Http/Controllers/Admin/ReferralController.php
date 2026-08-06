<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Reward issuance, abuse flags (User Journey §7). */
class ReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $referrals = Referral::with(['referrer:id,full_name,email', 'referredUser:id,full_name,email'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Referrals/Index', [
            'referrals' => $referrals,
            'filters' => $request->only(['status']),
        ]);
    }

    public function flag(Referral $referral): RedirectResponse
    {
        $referral->update(['status' => 'flagged']);

        return back()->with('success', 'Referral flagged for abuse review.');
    }

    public function approve(Referral $referral): RedirectResponse
    {
        $referral->update(['status' => 'rewarded']);

        return back()->with('success', 'Reward approved.');
    }
}
