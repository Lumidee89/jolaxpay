<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\PaystackGateway;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveAccountRequest;
use App\Http\Requests\Api\V1\StoreWithdrawalRequest;
use App\Http\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Wallet -> bank account payout via Paystack Transfers. A transfer is
 * always initiated as "pending" and resolved later by
 * PaystackWebhookController's `transfer.success`/`transfer.failed` — see
 * that controller and the withdrawals migration for why.
 */
class WithdrawalController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PaystackGateway $paystack,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $withdrawals = $request->user()->withdrawals()->latest()->limit(50)->get();

        return response()->json(['data' => WithdrawalResource::collection($withdrawals)]);
    }

    /** Cached a day — Paystack's bank list changes rarely and this endpoint feeds a picker on every visit to the withdraw screen. */
    public function banks(): JsonResponse
    {
        $banks = Cache::remember('paystack:banks', now()->addDay(), fn () => $this->paystack->listBanks());

        // Paystack's real /bank listing never includes their sandbox-only
        // "Test Bank" (code 001) — the one their own /bank/resolve error
        // message tells you to use once you've burned through test mode's
        // daily quota for resolving real bank codes (any account number
        // against it always resolves, quota-free). Without this it's
        // impossible to even select it, so the documented workaround is
        // unreachable through the app. Checked live (not cached) so it
        // disappears the moment real keys replace the test ones.
        //
        // It only gets you through account *resolution*, though — Paystack
        // rejects it at /transferrecipient ("invalid_bank_code") since it
        // isn't a real payout-capable bank, so a withdrawal against it will
        // always fail after the "verified" checkmark. The name says so
        // rather than letting that be a second surprise.
        if (str_starts_with((string) config('payments.paystack.secret_key'), 'sk_test_')) {
            array_unshift($banks, ['name' => 'Test Bank (sandbox — verifies only, cannot complete a withdrawal)', 'code' => '001']);
        }

        return response()->json(['data' => $banks]);
    }

    public function resolveAccount(ResolveAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $account = $this->paystack->resolveAccount($data['account_number'], $data['bank_code']);

        if (! $account) {
            return response()->json(['message' => 'Could not verify that account number. Double-check it and try again.'], 422);
        }

        return response()->json(['data' => $account]);
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $wallet = $this->ledger->walletFor($request->user());

        $account = $this->paystack->resolveAccount($data['account_number'], $data['bank_code']);

        if (! $account) {
            return response()->json(['message' => 'Could not verify that account number. Double-check it and try again.'], 422);
        }

        try {
            $this->ledger->debit($wallet, (string) $data['amount'], LedgerReason::Withdrawal, null, [
                'bank_code' => $data['bank_code'],
                'account_number' => $data['account_number'],
            ]);
        } catch (InsufficientFundsException) {
            return response()->json(['message' => 'Insufficient wallet balance.'], 422);
        }

        $bankName = collect($this->paystack->listBanks())->firstWhere('code', $data['bank_code'])['name'] ?? null;
        $reference = 'wd-'.Str::uuid();

        $withdrawal = Withdrawal::create([
            'user_id' => $request->user()->id,
            'wallet_id' => $wallet->id,
            'amount' => $data['amount'],
            'currency' => $wallet->currency,
            'bank_code' => $data['bank_code'],
            'bank_name' => $bankName,
            'account_number' => $data['account_number'],
            'account_name' => $account['account_name'],
            'reference' => $reference,
            'status' => 'pending',
        ]);

        $recipientCode = $this->paystack->createTransferRecipient($data['account_number'], $data['bank_code'], $account['account_name']);

        if (! $recipientCode) {
            $this->reverse($withdrawal, 'Could not register this bank account with our payout provider.');

            return response()->json(['message' => 'Withdrawal could not be started — please try again.'], 422);
        }

        $withdrawal->update(['paystack_recipient_code' => $recipientCode]);

        $amountKobo = (int) round(((float) $data['amount']) * 100);
        $transfer = $this->paystack->initiateTransfer($recipientCode, $amountKobo, $reference, 'JolaxPay wallet withdrawal');

        if (! $transfer) {
            $this->reverse($withdrawal, 'Could not initiate the transfer with our payout provider.');

            return response()->json(['message' => 'Withdrawal could not be started — please try again.'], 422);
        }

        $withdrawal->update(['paystack_transfer_code' => $transfer['transfer_code'] ?? null]);

        // Paystack's sandbox returns 'success' synchronously (no real
        // processing happens there); live transfers come back 'pending'
        // and resolve via webhook. Marking it here too when Paystack
        // already says success is harmless — the webhook handler only
        // acts on rows still 'pending'.
        if (($transfer['status'] ?? null) === 'success') {
            $withdrawal->update(['status' => 'success']);
        }

        return response()->json(['data' => WithdrawalResource::make($withdrawal->fresh())], 201);
    }

    /** Refunds the held amount and marks the withdrawal failed — used when Paystack rejects the withdrawal before a transfer is even in flight (nothing for its webhook to resolve). */
    protected function reverse(Withdrawal $withdrawal, string $reason): void
    {
        $this->ledger->credit($withdrawal->wallet, (string) $withdrawal->amount, LedgerReason::WithdrawalReversal, null, [
            'withdrawal_id' => $withdrawal->id,
            'reason' => $reason,
        ]);

        $withdrawal->update(['status' => 'failed', 'failure_reason' => $reason]);
    }
}
