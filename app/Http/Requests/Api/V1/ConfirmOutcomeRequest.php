<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OutcomeReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/** "Has electricity been restored?" (PRD §7.6). */
class ConfirmOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'boolean'],
            'reason' => ['required_if:confirmed,false', 'nullable', new Enum(OutcomeReason::class)],
        ];
    }
}
