<?php

namespace App\Models;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryDestination;
use App\Enums\OutcomeReason;
use App\Enums\ServiceType;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

// 'status' is deliberately excluded — only TransactionStateMachine may set
// it (via forceFill), so no accidental mass-assignment path can skip the
// transition validation in TransactionStatus::canTransitionTo(). Everything
// below is written exclusively by the domain layer (never from raw
// FormRequest input — see StoreTransactionRequest's much narrower rule
// set), so mass-assignment guarding is not a security boundary for these.
#[Fillable([
    'user_id', 'meter_id', 'meter_group_id', 'service_type', 'amount', 'fee',
    'currency', 'fx_rate', 'amount_ngn', 'payment_method', 'payment_reference',
    'delivery_destination', 'recipient_name', 'recipient_phone', 'recipient_email',
    'recipient_user_id', 'idempotency_key', 'meta', 'token', 'delivered_at',
    'delivery_channel', 'refunded_to_wallet', 'outcome_confirmed', 'outcome_reason',
    'outcome_confirmed_at',
])]
class Transaction extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            $transaction->reference ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'fx_rate' => 'decimal:6',
            'amount_ngn' => 'decimal:2',
            'status' => TransactionStatus::class,
            'service_type' => ServiceType::class,
            'delivery_destination' => DeliveryDestination::class,
            'delivery_channel' => DeliveryChannel::class,
            'outcome_reason' => OutcomeReason::class,
            'outcome_confirmed' => 'boolean',
            'refunded_to_wallet' => 'boolean',
            'delivered_at' => 'datetime',
            'outcome_confirmed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function meterGroup(): BelongsTo
    {
        return $this->belongsTo(MeterGroup::class);
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TransactionStatusHistory::class)->orderBy('created_at');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function total(): string
    {
        return bcadd((string) $this->amount, (string) $this->fee, 2);
    }
}
