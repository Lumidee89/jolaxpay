<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['active_provider', 'updated_by'])]
class PaymentProviderSetting extends Model
{
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'active_provider' => config('payments.domestic.driver', 'safehaven'),
        ]);
    }
}
