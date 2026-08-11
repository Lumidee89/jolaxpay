<?php

use App\Models\SupportTicket;
use App\Models\Transaction;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Live transaction status channel (TRD §3, §8): `private-transaction.{id}`.
 * Authorised for the transaction's own buyer, its in-app recipient (if
 * any), or any staff user — so both the mobile status screen and the
 * open Admin Transactions/Show.tsx page update instantly.
 */
Broadcast::channel('transaction.{transactionId}', function ($user, int $transactionId) {
    $transaction = Transaction::find($transactionId);

    if (! $transaction) {
        return false;
    }

    return $user->id === $transaction->user_id
        || $user->id === $transaction->recipient_user_id
        || $user->isStaff();
});

/**
 * PRD §22 "live chat" — `private-support-ticket.{id}`. Authorised for the
 * ticket's own owner or any staff user, mirroring the transaction channel
 * above. Both the mobile thread screen and Admin's Support/Show.tsx
 * subscribe to this so a reply from either side appears instantly on the
 * other without polling.
 */
Broadcast::channel('support-ticket.{ticketId}', function ($user, int $ticketId) {
    $ticket = SupportTicket::find($ticketId);

    if (! $ticket) {
        return false;
    }

    return $user->id === $ticket->user_id || $user->isStaff();
});
