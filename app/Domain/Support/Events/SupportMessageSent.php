<?php

namespace App\Domain\Support\Events;

use App\Models\SupportTicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * PRD §22 "live chat" — implemented as a real-time push over the existing
 * Reverb broadcaster (already wired for TransactionStatusUpdated) rather
 * than a new WhatsApp integration; see `support-ticket.{id}` in
 * routes/channels.php for who's allowed to listen. ShouldBroadcastNow,
 * same reasoning as TransactionStatusUpdated: a chat message pushed a
 * few seconds late isn't "live" anymore.
 */
class SupportMessageSent implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public SupportTicketMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("support-ticket.{$this->message->support_ticket_id}")];
    }

    public function broadcastAs(): string
    {
        return 'support.message-sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'support_ticket_id' => $this->message->support_ticket_id,
            'body' => $this->message->body,
            'is_staff_reply' => $this->message->is_staff_reply,
            'author' => $this->message->author ? ['full_name' => $this->message->author->full_name] : null,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
