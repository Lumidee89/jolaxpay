<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agent_id', 'transaction_id', 'referred_user_id', 'commission_rule_id', 'earning_type', 'amount', 'status', 'reversal_of_id', 'available_at', 'paid_at', 'reversed_at', 'meta'])]
class AgentCommission extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'available_at' => 'datetime', 'paid_at' => 'datetime', 'reversed_at' => 'datetime', 'meta' => 'array'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }
}
