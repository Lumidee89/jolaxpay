<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cached VTpass service-variation (data bundle / TV bouquet / exam pin type) — see SyncBillerVariations. */
#[Fillable(['biller_id', 'variation_code', 'name', 'amount', 'fixed_price', 'is_active'])]
class BillerVariation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fixed_price' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }
}
