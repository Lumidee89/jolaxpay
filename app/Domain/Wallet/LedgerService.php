<?php

namespace App\Domain\Wallet;

use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Exceptions\InvalidTransferException;
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
            ['balance' => 0, 'wallet_address' => $this->generateWalletAddress()],
        );
    }

    /** How another JolaxPay user sends this wallet money (see transfer()) — not the account's phone/email. */
    protected function generateWalletAddress(): string
    {
        do {
            $candidate = 'JLX'.Str::upper(Str::random(10));
        } while (Wallet::where('wallet_address', $candidate)->exists());

        return $candidate;
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

    /**
     * Wallet-to-wallet transfer by `wallet_address` (not phone/email —
     * see Wallet::wallet_address). Both legs are written atomically: the
     * sender's wallet is locked first, then the recipient's, in a fixed
     * order (ascending wallet id) regardless of who initiated the
     * transfer, so two concurrent opposite-direction transfers can never
     * deadlock each other.
     *
     * @return array{sender: LedgerEntry, recipient: LedgerEntry}
     *
     * @throws InvalidTransferException
     * @throws InsufficientFundsException
     */
    public function transfer(Wallet $sender, string $recipientWalletAddress, string $amount, ?string $note = null): array
    {
        $recipient = Wallet::where('wallet_address', $recipientWalletAddress)->first();

        if (! $recipient) {
            throw new InvalidTransferException('No wallet found with that address.');
        }

        if ($recipient->id === $sender->id) {
            throw new InvalidTransferException('You cannot send money to your own wallet.');
        }

        if ($recipient->currency !== $sender->currency) {
            throw new InvalidTransferException('The recipient wallet is in a different currency.');
        }

        return DB::transaction(function () use ($sender, $recipient, $amount, $note) {
            // Fixed lock order avoids the classic "A locks 1-then-2 while B
            // locks 2-then-1" deadlock between two simultaneous transfers.
            [$firstId, $secondId] = $sender->id < $recipient->id
                ? [$sender->id, $recipient->id]
                : [$recipient->id, $sender->id];

            Wallet::whereKey($firstId)->lockForUpdate()->firstOrFail();
            Wallet::whereKey($secondId)->lockForUpdate()->firstOrFail();

            $transferReference = (string) Str::uuid();

            $senderEntry = $this->debit($sender, $amount, LedgerReason::TransferOut, null, [
                'transfer_reference' => $transferReference,
                'counterparty_wallet_address' => $recipient->wallet_address,
                'counterparty_user_id' => $recipient->user_id,
                'note' => $note,
            ]);

            $recipientEntry = $this->credit($recipient, $amount, LedgerReason::TransferIn, null, [
                'transfer_reference' => $transferReference,
                'counterparty_wallet_address' => $sender->wallet_address,
                'counterparty_user_id' => $sender->user_id,
                'note' => $note,
            ]);

            return ['sender' => $senderEntry, 'recipient' => $recipientEntry];
        });
    }
}
