<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Review/dismiss auto-generated fraud flags (PRD §15, manage-fraud permission). */
class FraudFlagController extends Controller
{
    public function index(Request $request): Response
    {
        $flags = FraudFlag::with(['user:id,full_name,email', 'transaction:id,reference,amount,status'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('rule'), fn ($q, $rule) => $q->where('rule', $rule))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/FraudFlags/Index', [
            'flags' => $flags,
            'filters' => $request->only(['status', 'rule']),
        ]);
    }

    public function review(FraudFlag $fraudFlag): RedirectResponse
    {
        $fraudFlag->update(['status' => 'reviewed']);

        return back()->with('success', 'Flag marked as reviewed.');
    }

    public function dismiss(FraudFlag $fraudFlag): RedirectResponse
    {
        $fraudFlag->update(['status' => 'dismissed']);

        return back()->with('success', 'Flag dismissed.');
    }
}
