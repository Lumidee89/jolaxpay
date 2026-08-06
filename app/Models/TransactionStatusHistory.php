<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transaction_id', 'from_status', 'to_status', 'note', 'caused_by_user_id'])]
class TransactionStatusHistory extends Model
{
    /** Migration table is `transaction_status_history` (already plural-ish; no trailing s). */
    protected $table = 'transaction_status_history';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => TransactionStatus::class,
            'to_status' => TransactionStatus::class,
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function causedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caused_by_user_id');
    }
}
