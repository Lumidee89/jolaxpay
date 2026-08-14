<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\SafeHavenGateway;
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
 * Wallet -> bank payout through Safe Haven MFB Transfers.
 */
class WithdrawalController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SafeHavenGateway $safeHaven,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $withdrawals = $request->user()->withdrawals()->latest()->limit(50)->get();

        return response()->json(['data' => WithdrawalResource::collection($withdrawals)]);
    }

    /** Cached a day because the NIP bank directory changes infrequently. */
    public function banks(): JsonResponse
    {
        $banks = Cache::remember('safehaven:banks', now()->addDay(), fn () => $this->safeHaven->listBanks());

        return response()->json(['data' => $banks]);
    }

    public function resolveAccount(ResolveAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $account = $this->safeHaven->resolveAccount($data['account_number'], $data['bank_code']);

        if (! $account) {
            return response()->json(['message' => 'Could not verify that account number. Double-check it and try again.'], 422);
        }

        return response()->json(['data' => $account]);
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $wallet = $this->ledger->walletFor($request->user());

        $account = $this->safeHaven->resolveAccount($data['account_number'], $data['bank_code']);

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

        $bankName = collect($this->safeHaven->listBanks())->firstWhere('code', $data['bank_code'])['name'] ?? null;
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

        $transfer = $this->safeHaven->transfer(
            $data['account_number'], $data['bank_code'], $account['name_enquiry_reference'],
            (float) $data['amount'], $reference, 'JolaxPay wallet withdrawal'
        );

        if (! $transfer) {
            $this->reverse($withdrawal, 'Could not initiate the transfer with our payout provider.');

            return response()->json(['message' => 'Withdrawal could not be started — please try again.'], 422);
        }

        $withdrawal->update(['provider_transfer_id' => $transfer['_id'] ?? $transfer['paymentReference'] ?? null]);

        // Paystack's sandbox returns 'success' synchronously (no real
        // processing happens there); live transfers come back 'pending'
        // and resolve via webhook. Marking it here too when Paystack
        // already says success is harmless — the webhook handler only
        // acts on rows still 'pending'.
        if (strtolower((string) ($transfer['status'] ?? '')) === 'completed') {
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
