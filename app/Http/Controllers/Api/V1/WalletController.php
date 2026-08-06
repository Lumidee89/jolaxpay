<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\PaymentManager;
use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FundWalletRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\WalletResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Wallet (PRD §7.11): balance, history, funding, automatic refund credit. */
class WalletController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PaymentManager $paymentManager,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->ledger->walletFor($request->user());

        return response()->json([
            'data' => WalletResource::make($wallet),
            'history' => LedgerEntryResource::collection(
                $wallet->ledgerEntries()->latest()->limit(50)->get()
            ),
        ]);
    }

    public function fund(FundWalletRequest $request): JsonResponse
    {
        $data = $request->validated();
        $wallet = $this->ledger->walletFor($request->user(), $data['currency'] ?? 'NGN');

        // A funding "transaction" isn't a Payment Flow purchase, so it's
        // charged directly against the processor rather than routed
        // through TransactionService's vending pipeline. The ephemeral
        // Transaction instance here is never persisted — it only carries
        // the fields PaymentManager/PaymentProcessor need to route the charge.
        $chargeContext = new Transaction(['currency' => $wallet->currency, 'payment_method' => $data['payment_method']]);
        $result = $this->paymentManager->driverFor($chargeContext)->charge($chargeContext);

        if (! $result->successful) {
            return response()->json(['message' => $result->message ?? 'Wallet funding failed.'], 422);
        }

        $entry = $this->ledger->credit(
            $wallet,
            (string) $data['amount'],
            LedgerReason::WalletFunding,
            null,
            ['processor_reference' => $result->processorReference],
        );

        return response()->json([
            'data' => WalletResource::make($wallet->fresh()),
            'entry' => LedgerEntryResource::make($entry),
        ], 201);
    }
}
