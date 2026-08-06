<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'contact_name', 'relationship', 'phone_number', 'email', 'linked_meter_id'])]
class PowerCircleContact extends Model
{
    use HasFactory;

    /** Migration/TRD table name is `power_circle` (singular collection noun). */
    protected $table = 'power_circle';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedMeter(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'linked_meter_id');
    }
}
