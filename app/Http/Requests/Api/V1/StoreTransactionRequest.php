<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DeliveryDestination;
use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Initiates a purchase for any service type, group, or currency (TRD §3).
 * The `Idempotency-Key` header (not a body field) is enforced by
 * EnsureIdempotencyKey and threaded through by the controller.
 */
class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meter_id' => ['required_without:meter_group_id', 'nullable', 'exists:meters,id'],
            'meter_group_id' => ['required_without:meter_id', 'nullable', 'exists:meter_groups,id'],
            'service_type' => ['nullable', new Enum(ServiceType::class)],
            'amount' => ['required', 'numeric', 'min:50'],
            'currency' => ['nullable', 'string', 'size:3'],
            'fx_rate' => ['nullable', 'numeric'],
            'amount_ngn' => ['required_if:currency,!=,NGN', 'nullable', 'numeric'],
            'payment_method' => ['required', 'in:card,bank_transfer,ussd,apple_pay,google_pay,wallet'],
            'delivery_destination' => ['nullable', new Enum(DeliveryDestination::class)],
            // Only meaningful (and required) when delivery_destination = someone_else;
            // for "me"/"meter_owner" the recipient is resolved server-side.
            'recipient_name' => ['required_if:delivery_destination,someone_else', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('delivery_destination') === DeliveryDestination::SomeoneElse->value
                && ! $this->filled('recipient_phone') && ! $this->filled('recipient_email')) {
                $validator->errors()->add('recipient_phone', 'Provide a phone number or email for the recipient.');
            }
        });
    }
}
