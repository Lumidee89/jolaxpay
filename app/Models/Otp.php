<?php

namespace App\Models;

use App\Enums\DeliveryChannel;
use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

#[Fillable(['user_id', 'identifier', 'channel', 'purpose', 'code_hash', 'attempts', 'expires_at', 'consumed_at'])]
class Otp extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }
}
