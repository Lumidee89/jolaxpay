<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DeliveryDestination;
use App\Enums\ServiceType;
use App\Models\Beneficiary;
use App\Models\Biller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Initiates a purchase for any service type, group, or currency (TRD §3).
 * The `Idempotency-Key` header (not a body field) is enforced by
 * EnsureIdempotencyKey and threaded through by the controller.
 *
 * Electricity is meter-anchored (meter_id/meter_group_id); every other
 * service type is biller-anchored (biller_id or a saved beneficiary_id,
 * plus biller_identifier/variation_code as that biller requires) — see
 * ServiceType::isBillerBased(). Which of those is actually required
 * depends on a biller lookup, so it's enforced in withValidator() rather
 * than as static rules.
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
            'meter_id' => ['nullable', 'exists:meters,id'],
            'meter_group_id' => ['nullable', 'exists:meter_groups,id'],
            'biller_id' => ['nullable', 'exists:billers,id'],
            'beneficiary_id' => ['nullable', 'exists:beneficiaries,id'],
            'biller_identifier' => ['nullable', 'string', 'max:50'],
            'variation_code' => ['nullable', 'string', 'max:100'],
            'service_type' => ['nullable', new Enum(ServiceType::class)],
            'amount' => ['required', 'numeric', 'min:50'],
            'currency' => ['nullable', 'string', 'size:3'],
            'fx_rate' => ['nullable', 'numeric'],
            // Whether this is actually required depends on `currency`
            // (see withValidator() below) — Laravel's required_if only does
            // exact-value matching, not comparison operators, so a static
            // `required_if:currency,!=,NGN` rule here would silently parse
            // as "required if currency equals the literal string '!=' or
            // 'NGN'", wrongly requiring this on every ordinary NGN purchase.
            'amount_ngn' => ['nullable', 'numeric'],
            'payment_method' => ['required', 'in:card,bank_transfer,ussd,apple_pay,google_pay,wallet'],
            'delivery_destination' => ['nullable', new Enum(DeliveryDestination::class)],
            // Only meaningful (and required) when delivery_destination = someone_else;
            // for "me"/"meter_owner" the recipient is resolved server-side.
            'recipient_name' => ['required_if:delivery_destination,someone_else', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'meta' => ['nullable', 'array'],
            // Only required/checked when this purchase clears
            // config('identity.high_value_threshold') — see
            // TransactionController::store().
            'otp_code' => ['nullable', 'string', 'size:6'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('delivery_destination') === DeliveryDestination::SomeoneElse->value
                && ! $this->filled('recipient_phone') && ! $this->filled('recipient_email')) {
                $validator->errors()->add('recipient_phone', 'Provide a phone number or email for the recipient.');
            }

            // An omitted currency defaults to NGN (same as
            // TransactionService::initiate()'s `$data['currency'] ?? 'NGN'`)
            // — only a genuinely foreign currency needs the naira-equivalent
            // snapshot.
            $currency = $this->input('currency') ?: 'NGN';

            if ($currency !== 'NGN' && ! $this->filled('amount_ngn')) {
                $validator->errors()->add('amount_ngn', 'The amount ngn field is required when currency is not NGN.');
            }

            $serviceType = ServiceType::tryFrom($this->input('service_type', 'electricity')) ?? ServiceType::Electricity;

            $serviceType->isBillerBased()
                ? $this->validateBillerPurchase($validator, $serviceType)
                : $this->validateElectricityPurchase($validator);
        });
    }

    protected function validateElectricityPurchase($validator): void
    {
        if (! $this->filled('meter_id') && ! $this->filled('meter_group_id')) {
            $validator->errors()->add('meter_id', 'Provide a meter_id or meter_group_id for an electricity purchase.');
        }
    }

    protected function validateBillerPurchase($validator, ServiceType $serviceType): void
    {
        if (! $this->filled('biller_id') && ! $this->filled('beneficiary_id')) {
            $validator->errors()->add('biller_id', 'Provide a biller_id or a saved beneficiary_id for this purchase.');

            return;
        }

        $beneficiary = $this->filled('beneficiary_id') ? Beneficiary::find($this->input('beneficiary_id')) : null;
        $biller = $this->filled('biller_id') ? Biller::find($this->input('biller_id')) : $beneficiary?->biller;

        if (! $biller) {
            $validator->errors()->add('biller_id', 'This biller could not be found.');

            return;
        }

        if ($beneficiary && $beneficiary->user_id !== $this->user()->id) {
            $validator->errors()->add('beneficiary_id', 'This beneficiary does not belong to you.');

            return;
        }

        if ($biller->service_type !== $serviceType->value) {
            $validator->errors()->add('biller_id', "This biller is for {$biller->service_type}, not {$serviceType->value}.");

            return;
        }

        if ($biller->requires_billers_code && ! $this->filled('biller_identifier') && ! $beneficiary) {
            $validator->errors()->add('biller_identifier', 'This biller requires an account number (biller_identifier).');
        }

        if ($biller->requires_variation && ! $this->filled('variation_code')) {
            $validator->errors()->add('variation_code', 'This biller requires a variation_code (bundle/plan selection).');
        }
    }
}
