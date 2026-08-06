<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'disco_id', 'group_id', 'label', 'meter_number', 'meter_type', 'recipient_phone', 'recipient_email', 'is_favorite'])]
class Meter extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disco(): BelongsTo
    {
        return $this->belongsTo(Disco::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MeterGroup::class, 'group_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
