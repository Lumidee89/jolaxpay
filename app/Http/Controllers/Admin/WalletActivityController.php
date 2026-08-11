<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LedgerReason;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WalletFundingIntent;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ops visibility into the three ways money moves in or out of a wallet
 * outside the Payment Flow's own Transaction model: card-funded top-ups,
 * bank withdrawals, and wallet-to-wallet transfers by address (WalletController,
 * WithdrawalController, LedgerService::transfer()). Each is its own
 * paginator on one page rather than a single merged feed — a failed
 * funding intent, for instance, never produces a ledger entry at all
 * (nothing was credited), so a ledger-only view would hide it entirely,
 * and ops specifically wants to see failures here, not just money that
 * successfully moved.
 */
class WalletActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $fundings = WalletFundingIntent::with('user:id,full_name,email')
            ->when($request->query('funding_status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20, ['*'], 'fundings_page')
            ->withQueryString();

        $withdrawals = Withdrawal::with('user:id,full_name,email')
            ->when($request->query('withdrawal_status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20, ['*'], 'withdrawals_page')
            ->withQueryString();

        // A transfer is two ledger rows sharing a transfer_reference in
        // meta — one `transfer_out` on the sender's wallet, one
        // `transfer_in` on the recipient's. Only the sender side is
        // listed so each transfer appears once; the recipient is read
        // straight out of meta rather than joined, since LedgerEntry has
        // no direct relation to "the other" entry.
        $transfers = LedgerEntry::with('wallet.user:id,full_name,email')
            ->where('reason', LedgerReason::TransferOut->value)
            ->latest()
            ->paginate(20, ['*'], 'transfers_page')
            ->withQueryString();

        // The recipient side lives only as a user_id inside this entry's
        // own meta (see the docblock above) — no relation to join, so
        // it's resolved with one bulk lookup instead of N+1 queries.
        $recipientNames = User::whereIn('id', $transfers->getCollection()->pluck('meta.counterparty_user_id')->filter()->unique())
            ->pluck('full_name', 'id');
        $transfers->getCollection()->transform(function (LedgerEntry $entry) use ($recipientNames) {
            $entry->recipient_name = $recipientNames[$entry->meta['counterparty_user_id'] ?? null] ?? null;

            return $entry;
        });

        return Inertia::render('Admin/WalletActivity/Index', [
            'fundings' => $fundings,
            'withdrawals' => $withdrawals,
            'transfers' => $transfers,
            'filters' => $request->only(['funding_status', 'withdrawal_status']),
        ]);
    }
}
