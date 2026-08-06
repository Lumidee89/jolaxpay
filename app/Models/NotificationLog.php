<?php

namespace App\Models;

use App\Enums\DeliveryChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'channel', 'payload', 'status', 'sent_at', 'read_at'])]
class NotificationLog extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'payload' => 'array',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
