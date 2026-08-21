<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'earning_type', 'service_type', 'biller_id', 'disco_id', 'calculation_type', 'value', 'jolaxpay_margin', 'minimum_commission', 'maximum_commission', 'starts_at', 'ends_at', 'is_active'])]
class CommissionRule extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:4', 'jolaxpay_margin' => 'decimal:2', 'minimum_commission' => 'decimal:2', 'maximum_commission' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
