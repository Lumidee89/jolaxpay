<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'biller_id' => ['required', 'exists:billers,id'],
            'label' => ['required', 'string', 'max:255'],
            'identifier' => ['required', 'string', 'max:50'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
