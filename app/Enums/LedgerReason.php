<?php

namespace App\Enums;

enum LedgerReason: string
{
    case Purchase = 'purchase';
    case Refund = 'refund';
    case WalletFunding = 'wallet_funding';
    case ReferralReward = 'referral_reward';
    case Adjustment = 'adjustment';
    // Wallet-to-wallet transfer (LedgerService::transfer()) — the sender's
    // debit and recipient's credit each get their own reason so ledger
    // reporting can tell a P2P transfer apart from a purchase debit or a
    // wallet top-up credit.
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    // Paystack payout to a bank account (WithdrawalController::store()) —
    // debited immediately on request, same "hold now, reverse on failure"
    // pattern as a purchase; ::WithdrawalReversal is that reversal.
    case Withdrawal = 'withdrawal';
    case WithdrawalReversal = 'withdrawal_reversal';
}
