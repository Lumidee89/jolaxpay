<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\OtpService;
use App\Domain\Insights\TransactionSearchParser;
use App\Domain\Payments\SafeHavenGateway;
use App\Domain\Transactions\TransactionService;
use App\Enums\DeliveryChannel;
use App\Enums\OtpPurpose;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmOutcomeRequest;
use App\Http\Requests\Api\V1\RepeatTransactionRequest;
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
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly SafeHavenGateway $safeHaven,
        private readonly OtpService $otp,
        private readonly TransactionSearchParser $searchParser,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = $request->user()->transactions()
            ->with(['meter', 'biller'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('service_type'), fn ($q, $type) => $q->where('service_type', $type))
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

    /**
     * PRD §11/§17 "natural-language transaction search" — see
     * TransactionSearchParser's docblock for how "q" is interpreted.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:255']]);

        $transactions = $this->searchParser->search($request->user()->id, $data['q'])
            ->limit(30)
            ->get();

        return response()->json(['data' => TransactionResource::collection($transactions)]);
    }

    /**
     * POST /v1/transactions — behind the `idempotent` middleware (TRD §8).
     *
     * PRD §15 high-value step-up: a purchase at or above
     * config('identity.high_value_threshold') needs a verified OTP before
     * TransactionService::initiate() runs. No otp_code yet -> an OTP is
     * issued and 428 tells the client to collect one and resubmit (with a
     * fresh Idempotency-Key, same as any other retry); a wrong/expired
     * code -> 422; a correct one falls through to the normal purchase path.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['idempotency_key'] = $request->header('Idempotency-Key');

        $amountForThreshold = (float) ($data['amount_ngn'] ?? $data['amount']);

        if ($amountForThreshold >= (float) config('identity.high_value_threshold')) {
            $user = $request->user();

            if (empty($data['otp_code'])) {
                $this->otp->issue($user->phone_number, OtpPurpose::HighValueTransaction, DeliveryChannel::Sms, $user);

                return response()->json([
                    'requires_otp' => true,
                    'purpose' => OtpPurpose::HighValueTransaction->value,
                    'identifier' => $user->phone_number,
                    'message' => 'This is a high-value purchase. Enter the verification code we sent you to continue.',
                ], 428);
            }

            if (! $this->otp->verify($user->phone_number, OtpPurpose::HighValueTransaction, $data['otp_code'])) {
                return response()->json(['message' => 'That code is invalid or has expired.'], 422);
            }
        }

        $transaction = $this->transactions->initiate($request->user(), $data);

        return response()->json(['data' => TransactionDetailResource::make($transaction)], 202);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeAccess($request, $transaction);

        return response()->json(['data' => TransactionDetailResource::make($transaction->load(['meter', 'biller', 'beneficiary', 'statusHistory']))]);
    }

    /**
     * "Buy this again" — re-runs a past purchase's meter/biller/amount/
     * recipient exactly as they were, behind the same `idempotent`
     * middleware and `StoreTransactionRequest`-shaped validation path as
     * a fresh purchase (via TransactionService::initiate()), just with the
     * body assembled from the original transaction instead of the client.
     */
    public function repeat(RepeatTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeAccess($request, $transaction);

        $data = $request->validated();
        $data['idempotency_key'] = $request->header('Idempotency-Key');

        $payload = [
            'meter_id' => $transaction->meter_id,
            'meter_group_id' => $transaction->meter_group_id,
            'biller_id' => $transaction->biller_id,
            'biller_identifier' => $transaction->biller_identifier,
            'variation_code' => $transaction->variation_code,
            'service_type' => $transaction->service_type->value,
            'amount' => (string) $transaction->amount,
            'currency' => $transaction->currency,
            'fx_rate' => $transaction->fx_rate,
            'amount_ngn' => $transaction->amount_ngn,
            'payment_method' => $data['payment_method'] ?? $transaction->payment_method,
            'delivery_destination' => $transaction->delivery_destination->value,
            'recipient_name' => $transaction->recipient_name,
            'recipient_phone' => $transaction->recipient_phone,
            'recipient_email' => $transaction->recipient_email,
            'idempotency_key' => $data['idempotency_key'],
        ];

        $repeated = $this->transactions->initiate($request->user(), $payload);

        return response()->json(['data' => TransactionDetailResource::make($repeated)], 202);
    }

    /**
     * GET /v1/transactions/{id}/status — polling fallback for when the
     * `private-transaction.{id}` broadcast channel is unavailable (TRD §3).
     *
     * A transaction sitting on `payment_initiated` means it's a Paystack
     * card payment awaiting confirmation. Rather than only waiting on the
     * `charge.success` webhook (which a local-dev backend with no public
     * URL will never receive), this asks Paystack directly on every poll —
     * see PaystackChargeReconciler's docblock.
     */
    public function status(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeAccess($request, $transaction);

        if ($transaction->status === TransactionStatus::PaymentInitiated && ($transaction->meta['safehaven_reference'] ?? null)) {
            $payment = $this->safeHaven->verifyCheckout($transaction->meta['safehaven_reference']);
            $status = strtolower((string) ($payment['status'] ?? ''));
            if (in_array($status, ['completed', 'successful', 'success'], true)) {
                $this->transactions->processPayment($transaction);
                $transaction->refresh();
            }
        }

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
