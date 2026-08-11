<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class WalletTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wallet_address' => ['required', 'string', 'exists:wallets,wallet_address'],
            'amount' => ['required', 'numeric', 'min:50'],
            'note' => ['nullable', 'string', 'max:140'],
        ];
    }
}
