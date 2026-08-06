<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'purpose' => ['required', new Enum(OtpPurpose::class)],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
