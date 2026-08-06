<?php

namespace App\Domain\Wallet;

use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Enums\LedgerEntryType;
use App\Enums\LedgerReason;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Double-entry ledger for every wallet credit/debit (TRD §5). This is the
 * highest-priority correctness surface in the app (Implementation Plan §5)
 * — every mutation runs inside a DB transaction with a row lock on the
 * wallet, and `ledger_entries` is append-only: nothing here is ever
 * updated or deleted, only reversed by a new entry.
 */
class LedgerService
{
    public function walletFor(User $user, string $currency = 'NGN'): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => $currency],
            ['balance' => 0],
        );
    }

    public function credit(
        Wallet $wallet,
        string $amount,
        LedgerReason $reason,
        ?Transaction $transaction = null,
        array $meta = [],
    ): LedgerEntry {
        return DB::transaction(function () use ($wallet, $amount, $reason, $transaction, $meta) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $balanceAfter = bcadd((string) $locked->balance, $amount, 2);
            $locked->update(['balance' => $balanceAfter]);

            return LedgerEntry::create([
                'wallet_id' => $locked->id,
                'transaction_id' => $transaction?->id,
                'type' => LedgerEntryType::Credit,
                'reason' => $reason,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'currency' => $locked->currency,
                'reference' => (string) Str::uuid(),
                'meta' => $meta,
            ]);
        });
    }

    /**
     * @throws InsufficientFundsException
     */
    public function debit(
        Wallet $wallet,
        string $amount,
        LedgerReason $reason,
        ?Transaction $transaction = null,
        array $meta = [],
    ): LedgerEntry {
        return DB::transaction(function () use ($wallet, $amount, $reason, $transaction, $meta) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $locked->balance, $amount, 2) < 0) {
                throw new InsufficientFundsException(
                    "Wallet #{$locked->id} has insufficient funds for a debit of {$amount} {$locked->currency}."
                );
            }

            $balanceAfter = bcsub((string) $locked->balance, $amount, 2);
            $locked->update(['balance' => $balanceAfter]);

            return LedgerEntry::create([
                'wallet_id' => $locked->id,
                'transaction_id' => $transaction?->id,
                'type' => LedgerEntryType::Debit,
                'reason' => $reason,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'currency' => $locked->currency,
                'reference' => (string) Str::uuid(),
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Automatic wallet refund fallback after a failed vend/delivery
     * (TRD §6, PRD §7.11). Idempotent: a transaction already marked
     * refunded is not refunded twice.
     */
    public function refundToWallet(Transaction $transaction): ?LedgerEntry
    {
        if ($transaction->refunded_to_wallet) {
            return null;
        }

        $wallet = $this->walletFor($transaction->user, $transaction->currency);

        $entry = $this->credit(
            $wallet,
            $transaction->total(),
            LedgerReason::Refund,
            $transaction,
            ['reason' => 'Automatic refund after failed vend/delivery'],
        );

        $transaction->update(['refunded_to_wallet' => true]);

        return $entry;
    }
}
