<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disco_id' => ['required', 'exists:discos,id'],
            'meter_number' => ['required', 'string', 'max:50'],
            'meter_type' => ['nullable', 'in:prepaid,postpaid'],
        ];
    }
}
