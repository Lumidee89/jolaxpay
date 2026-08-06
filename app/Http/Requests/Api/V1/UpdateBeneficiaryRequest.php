<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
