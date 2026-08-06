<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Transactions\TransactionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmOutcomeRequest;
use App\Http\Requests\Api\V1\StoreTransactionRequest;
use App\Http\Resources\TransactionDetailResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Initiate a purchase (any service type/group/currency), poll status,
 * confirm outcome, and fetch the Smart Receipt (TRD §3).
 */
class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = $request->user()->transactions()
            ->with(['meter', 'biller'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => TransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /** POST /v1/transactions — behind the `idempotent` middleware (TRD §8). */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['idempotency_key'] = $request->header('Idempotency-Key');

        $transaction = $this->transactions->initiate($request->user(), $data);

        return response()->json(['data' => TransactionDetailResource::make($transaction)], 202);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeAccess($request, $transaction);

        return response()->json(['data' => TransactionDetailResource::make($transaction->load(['meter', 'biller', 'beneficiary', 'statusHistory']))]);
    }

    /**
     * GET /v1/transactions/{id}/status — polling fallback for when the
     * `private-transaction.{id}` broadcast channel is unavailable (TRD §3).
     */
    public function status(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeAccess($request, $transaction);

        return response()->json(['data' => TransactionDetailResource::make($transaction)]);
    }

    /** "Has electricity been restored?" (PRD §7.6). */
    public function confirmOutcome(ConfirmOutcomeRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeAccess($request, $transaction);

        $data = $request->validated();
        $reason = isset($data['reason']) ? \App\Enums\OutcomeReason::from($data['reason']) : null;

        $transaction = $this->transactions->confirmOutcome($transaction, $data['confirmed'], $reason);

        return response()->json(['data' => TransactionDetailResource::make($transaction)]);
    }

    /** Smart Receipt PDF (PRD §7.10, §7.12). */
    public function receipt(Request $request, Transaction $transaction): Response
    {
        $this->authorizeAccess($request, $transaction);

        $purchaseCount = $request->user()->transactions()
            ->where('status', '!=', \App\Enums\TransactionStatus::Failed->value)
            ->where('created_at', '<=', $transaction->created_at)
            ->count();

        $pdf = Pdf::loadView('receipts.transaction', [
            'transaction' => $transaction->load(['meter', 'biller']),
            'purchaseCount' => $purchaseCount,
        ]);

        return $pdf->download("jolaxpay-receipt-{$transaction->reference}.pdf");
    }

    protected function authorizeAccess(Request $request, Transaction $transaction): void
    {
        $user = $request->user();
        abort_unless(
            $transaction->user_id === $user->id || $transaction->recipient_user_id === $user->id || $user->isStaff(),
            403,
        );
    }
}
