<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduledPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:50'],
            'frequency' => ['sometimes', 'in:weekly,biweekly,monthly,custom'],
            'custom_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'next_run_at' => ['sometimes', 'date'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
