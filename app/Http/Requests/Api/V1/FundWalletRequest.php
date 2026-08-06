<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class FundWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['required', 'in:card,bank_transfer,ussd,apple_pay,google_pay'],
        ];
    }
}
