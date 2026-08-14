<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A wallet -> bank account payout through the configured banking provider. */
#[Fillable([
    'user_id', 'wallet_id', 'amount', 'currency', 'bank_code', 'bank_name',
    'account_number', 'account_name', 'provider_transfer_id',
    'reference', 'status', 'failure_reason',
])]
class Withdrawal extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
