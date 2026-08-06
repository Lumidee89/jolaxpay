<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disco_id' => ['required', 'exists:discos,id'],
            'group_id' => ['nullable', 'exists:meter_groups,id'],
            'label' => ['required', 'string', 'max:255'],
            'meter_number' => ['required', 'string', 'max:50'],
            'meter_type' => ['nullable', 'in:prepaid,postpaid'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
