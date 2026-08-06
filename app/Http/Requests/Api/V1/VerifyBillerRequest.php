<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBillerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'biller_id' => ['required', 'exists:billers,id'],
            'identifier' => ['required', 'string', 'max:50'],
            'variation_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}
