<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Transactions\TransactionService;
use App\Http\Controllers\Controller;
use App\Models\Biller;
use App\Models\Disco;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Ops resolves a stuck transaction" (User Journey §7): filter, inspect
 * full state history, manual retry / refund / escalate.
 */
class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function index(Request $request): Response
    {
        $transactions = Transaction::with(['user:id,full_name,email', 'meter:id,label,disco_id', 'meter.disco:id,name', 'biller:id,name,service_type'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('service_type'), fn ($q, $type) => $q->where('service_type', $type))
            ->when($request->query('disco_id'), fn ($q, $discoId) => $q->whereHas('meter', fn ($m) => $m->where('disco_id', $discoId)))
            ->when($request->query('biller_id'), fn ($q, $billerId) => $q->where('biller_id', $billerId))
            ->when($request->query('search'), fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%"))
            ))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $transactions,
            'discos' => Disco::orderBy('name')->get(['id', 'name']),
            'billers' => Biller::orderBy('name')->get(['id', 'name', 'service_type']),
            'filters' => $request->only(['status', 'service_type', 'disco_id', 'biller_id', 'search']),
        ]);
    }

    public function show(Transaction $transaction): Response
    {
        $transaction->load(['user', 'meter.disco', 'biller', 'beneficiary', 'statusHistory.causedByUser', 'ledgerEntries', 'supportTickets']);

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    public function retry(Transaction $transaction): RedirectResponse
    {
        try {
            $this->transactions->manualRetry($transaction, auth()->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Retry triggered.');
    }

    public function refund(Transaction $transaction): RedirectResponse
    {
        $this->transactions->manualRefund($transaction, auth()->user());

        return back()->with('success', 'Refund processed to wallet.');
    }
}
