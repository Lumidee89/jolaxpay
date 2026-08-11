<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** Re-runs a past purchase's meter/biller/amount as-is — only the payment method is worth letting the caller change. */
class RepeatTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'in:card,bank_transfer,ussd,apple_pay,google_pay,wallet'],
        ];
    }
}
