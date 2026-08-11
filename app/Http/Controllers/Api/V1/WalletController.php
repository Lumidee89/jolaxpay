<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Payments\PaymentManager;
use App\Domain\Payments\PaystackChargeReconciler;
use App\Domain\Payments\PaystackGateway;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Exceptions\InvalidTransferException;
use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\LedgerReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FundWalletRequest;
use App\Http\Requests\Api\V1\WalletTransferRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\WalletResource;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\WalletFundingIntent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Wallet (PRD §7.11): balance, history, funding, P2P transfer, automatic refund credit. */
class WalletController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PaymentManager $paymentManager,
        private readonly PaystackGateway $paystack,
        private readonly PaystackChargeReconciler $reconciler,
        private readonly NotificationDispatcher $notifier,
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

    /**
     * When PAYMENTS_DOMESTIC_DRIVER=paystack, funding is a hosted-checkout
     * redirect (same reasoning as TransactionService's Paystack path) —
     * this returns `requires_redirect` + an authorization_url instead of
     * crediting immediately, and PaystackWebhookController does the actual
     * crediting once `charge.success` confirms it. Still mock-driver
     * compatible: unchanged synchronous-credit behavior otherwise.
     */
    public function fund(FundWalletRequest $request): JsonResponse
    {
        $data = $request->validated();
        $wallet = $this->ledger->walletFor($request->user(), $data['currency'] ?? 'NGN');

        if (config('payments.domestic.driver') === 'paystack' && $wallet->currency === config('payments.domestic_currency', 'NGN')) {
            $reference = 'fund-'.Str::uuid();
            $amountKobo = (int) round(((float) $data['amount']) * 100);

            $init = $this->paystack->initializeTransaction($request->user()->email, $amountKobo, $reference, [
                'purpose' => 'wallet_funding',
                'user_id' => $request->user()->id,
            ]);

            if (! $init) {
                return response()->json(['message' => 'Could not start card payment — please try again.'], 422);
            }

            $intent = WalletFundingIntent::create([
                'user_id' => $request->user()->id,
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'amount' => $data['amount'],
                'currency' => $wallet->currency,
                'status' => 'pending',
            ]);

            return response()->json([
                'requires_redirect' => true,
                'authorization_url' => $init['authorization_url'],
                'reference' => $intent->reference,
            ], 202);
        }

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

        $this->notifier->send($request->user(), 'wallet_funded', DeliveryChannel::InApp, [
            'amount' => (string) $data['amount'],
            'currency' => $wallet->currency,
            'balance' => (string) $wallet->fresh()->balance,
        ]);

        return response()->json([
            'data' => WalletResource::make($wallet->fresh()),
            'entry' => LedgerEntryResource::make($entry),
        ], 201);
    }

    /**
     * Polling fallback for a Paystack wallet-funding redirect, mirroring
     * transactions/{id}/status. While the intent is still pending, this
     * actively asks Paystack for the charge's real status rather than only
     * waiting on the `charge.success` webhook — the webhook can't reach a
     * backend with no public URL (local dev) and isn't instant even when
     * it can, so without this a genuinely successful card payment would
     * show as stuck forever.
     */
    public function fundingStatus(Request $request, string $reference): JsonResponse
    {
        $intent = WalletFundingIntent::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($intent->status === 'pending') {
            $this->reconciler->reconcile($reference);
            $intent->refresh();
        }

        return response()->json(['data' => ['reference' => $intent->reference, 'status' => $intent->status]]);
    }

    /** Wallet-to-wallet transfer by wallet_address (PRD-adjacent — not a Payment Flow purchase, same reasoning as fund()). */
    public function transfer(WalletTransferRequest $request): JsonResponse
    {
        $data = $request->validated();
        $wallet = $this->ledger->walletFor($request->user());

        try {
            $result = $this->ledger->transfer($wallet, $data['wallet_address'], (string) $data['amount'], $data['note'] ?? null);
        } catch (InvalidTransferException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InsufficientFundsException $e) {
            return response()->json(['message' => 'Insufficient wallet balance.'], 422);
        }

        $recipientUser = $result['recipient']->wallet->user;

        $this->notifier->send($request->user(), 'wallet_transfer_sent', DeliveryChannel::InApp, [
            'amount' => (string) $data['amount'],
            'recipient_wallet_address' => $data['wallet_address'],
        ]);
        $this->notifier->send($recipientUser, 'wallet_transfer_received', DeliveryChannel::InApp, [
            'amount' => (string) $data['amount'],
            'sender_name' => $request->user()->full_name,
        ]);

        return response()->json([
            'data' => WalletResource::make($wallet->fresh()),
            'entry' => LedgerEntryResource::make($result['sender']),
        ], 201);
    }

    /** PDF receipt for a single wallet ledger entry (funding, refund, transfer, referral reward, withdrawal, adjustment). */
    public function entryReceipt(Request $request, LedgerEntry $ledgerEntry): Response
    {
        abort_unless($ledgerEntry->wallet->user_id === $request->user()->id || $request->user()->isStaff(), 403);

        $pdf = Pdf::loadView('receipts.wallet-entry', [
            'entry' => $ledgerEntry->load('transaction'),
            'wallet' => $ledgerEntry->wallet,
        ]);

        return $pdf->download("jolaxpay-wallet-{$ledgerEntry->reference}.pdf");
    }
}
