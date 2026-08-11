<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Business-only, same as every other action on this resource — see
        // BusinessLedgerController's class docblock for why this is
        // enforced per-request rather than with route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
        ];
    }
}
