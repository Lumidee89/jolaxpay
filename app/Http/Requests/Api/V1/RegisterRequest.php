<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

/** PRD §8: "Register with only name, phone, email, password" — no BVN/NIN. */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20', 'unique:users,phone_number'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'country_code' => ['nullable', 'string', 'size:2'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            // PRD §13: chosen at registration, 'individual' by default —
            // see AccountType's docblock for what this gates.
            'account_type' => ['nullable', new Enum(AccountType::class)],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }
}
