<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use App\Enums\LedgerReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable double-entry ledger row. Never update or delete a posted entry
 * — reverse it with a new entry instead (see LedgerService).
 */
#[Fillable(['wallet_id', 'transaction_id', 'type', 'reason', 'amount', 'balance_after', 'currency', 'reference', 'meta'])]
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'reason' => LedgerReason::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
