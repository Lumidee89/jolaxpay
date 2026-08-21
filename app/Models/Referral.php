<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['referrer_id', 'referred_user_id', 'code', 'status', 'attributed_at', 'activated_at', 'attribution_changed_by', 'attribution_note', 'reward_type', 'reward_value'])]
class Referral extends Model
{
    protected function casts(): array
    {
        return [
            'reward_value' => 'decimal:2',
            'attributed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
