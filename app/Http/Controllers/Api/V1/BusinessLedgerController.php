<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBusinessLedgerEntryRequest;
use App\Http\Resources\BusinessLedgerEntryResource;
use App\Models\BusinessLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * PRD §13 Business Dashboard — a manual income/expense ledger, gated to
 * users.account_type = 'business' (chosen at registration). Deliberately
 * separate from the wallet/Payment Flow domains: this is a business
 * account's own bookkeeping, not money actually moving through JolaxPay.
 */
class BusinessLedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeBusinessAccount($request);

        $entries = $request->user()->businessLedgerEntries()
            ->when($request->query('month'), function ($q, $month) {
                [$start, $end] = $this->monthRange($month);

                return $q->whereBetween('entry_date', [$start, $end]);
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => BusinessLedgerEntryResource::collection($entries)]);
    }

    public function store(StoreBusinessLedgerEntryRequest $request): JsonResponse
    {
        $this->authorizeBusinessAccount($request);

        $entry = $request->user()->businessLedgerEntries()->create($request->validated());

        return response()->json(['data' => BusinessLedgerEntryResource::make($entry)], 201);
    }

    public function destroy(Request $request, BusinessLedgerEntry $businessLedgerEntry): JsonResponse
    {
        $this->authorizeBusinessAccount($request);
        abort_unless($businessLedgerEntry->user_id === $request->user()->id, 403);

        $businessLedgerEntry->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** Monthly income/expense/net summary — GET /business/summary. */
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeBusinessAccount($request);

        $thisMonth = $this->totalsFor($request->user()->id, now()->format('Y-m'));
        $lastMonth = $this->totalsFor($request->user()->id, now()->subMonthNoOverflow()->format('Y-m'));

        return response()->json(['data' => [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
        ]]);
    }

    protected function totalsFor(int $userId, string $yearMonth): array
    {
        [$start, $end] = $this->monthRange($yearMonth);

        $income = (float) BusinessLedgerEntry::where('user_id', $userId)
            ->where('type', 'income')
            ->whereBetween('entry_date', [$start, $end])
            ->sum('amount');

        $expense = (float) BusinessLedgerEntry::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('entry_date', [$start, $end])
            ->sum('amount');

        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }

    /** @return array{0: string, 1: string} [start-of-month, end-of-month] as Y-m-d, portable across SQLite (tests) and MySQL (production). */
    protected function monthRange(string $yearMonth): array
    {
        $date = Carbon::createFromFormat('Y-m-d', "{$yearMonth}-01");

        return [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()];
    }

    protected function authorizeBusinessAccount(Request $request): void
    {
        abort_unless($request->user()->isBusinessAccount(), 403, 'This is only available for business accounts.');
    }
}
