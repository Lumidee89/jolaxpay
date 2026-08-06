<?php

namespace App\Domain\Transactions\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts on `private-transaction.{id}` (TRD §3, §8) so the mobile
 * status screen and an open Admin Transactions/Show.tsx page both update
 * within ~2s of a backend state change, with polling
 * `GET /v1/transactions/{id}/status` as the documented fallback.
 *
 * ShouldBroadcastNow (not queued) — status pushes are latency-sensitive
 * and cheap; queuing them would reintroduce the delay this event exists
 * to remove.
 */
class TransactionStatusUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public Transaction $transaction) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("transaction.{$this->transaction->id}")];
    }

    public function broadcastAs(): string
    {
        return 'transaction.status-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->transaction->id,
            'reference' => $this->transaction->reference,
            'status' => $this->transaction->status->value,
            'status_label' => $this->transaction->status->label(),
            'token' => $this->transaction->status->value === 'delivered' || $this->transaction->status->value === 'outcome_confirmed'
                ? $this->transaction->token
                : null,
            'updated_at' => $this->transaction->updated_at?->toIso8601String(),
        ];
    }
}
