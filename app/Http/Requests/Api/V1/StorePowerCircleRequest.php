<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePowerCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'required_without:email', 'string', 'max:20'],
            'email' => ['nullable', 'required_without:phone_number', 'email', 'max:255'],
            'linked_meter_id' => ['nullable', 'exists:meters,id'],
        ];
    }
}
