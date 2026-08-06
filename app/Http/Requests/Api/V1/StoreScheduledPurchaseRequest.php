<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduledPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meter_id' => ['required', 'exists:meters,id'],
            'amount' => ['required', 'numeric', 'min:50'],
            'frequency' => ['required', 'in:weekly,biweekly,monthly,custom'],
            'custom_interval_days' => ['required_if:frequency,custom', 'nullable', 'integer', 'min:1', 'max:365'],
            'payment_method_id' => ['nullable', 'string'],
            'next_run_at' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
