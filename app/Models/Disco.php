<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'code', 'region', 'service_type', 'api_provider_code', 'health_status', 'health_checked_at', 'is_active'])]
class Disco extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'health_checked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    /** For the Admin Provider Health page (User Journey §7). */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, Meter::class);
    }
}
