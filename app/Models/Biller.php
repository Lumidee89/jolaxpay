<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vendable network/provider/exam-body for a non-electricity service
 * (MTN, DSTV, WAEC, ...) — the counterpart to Disco for everything that
 * isn't electricity. See App\Enums\ServiceType::isBillerBased().
 */
#[Fillable([
    'name', 'code', 'service_type', 'api_provider_code', 'identifier_label',
    'requires_billers_code', 'requires_variation', 'supports_verify',
    'health_status', 'health_checked_at', 'is_active',
])]
class Biller extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'requires_billers_code' => 'boolean',
            'requires_variation' => 'boolean',
            'supports_verify' => 'boolean',
            'health_checked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function variations(): HasMany
    {
        return $this->hasMany(BillerVariation::class)->where('is_active', true)->orderBy('amount');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    /** For the Admin Provider Health page (User Journey §7). */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
