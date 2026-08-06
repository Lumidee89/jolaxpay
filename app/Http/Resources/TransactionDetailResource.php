<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full detail view — `GET /v1/transactions/{id}/status` and the
 * transaction-status broadcast payload's shape mirror this subset
 * (TRD §3, §8).
 *
 * @mixin \App\Models\Transaction
 */
class TransactionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'service_type' => $this->service_type,
            'amount' => (float) $this->amount,
            'fee' => (float) $this->fee,
            'total' => (float) $this->total(),
            'currency' => $this->currency,
            'amount_ngn' => $this->amount_ngn !== null ? (float) $this->amount_ngn : null,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'payment_method' => $this->payment_method,
            'token' => in_array($this->status->value, ['delivered', 'outcome_confirmed'], true) ? $this->token : null,
            'delivery_destination' => $this->delivery_destination,
            'delivery_channel' => $this->delivery_channel,
            'recipient_name' => $this->recipient_name,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'outcome_confirmed' => $this->outcome_confirmed,
            'outcome_reason' => $this->outcome_reason,
            'meter' => MeterResource::make($this->whenLoaded('meter')),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'note' => $h->note,
                'at' => $h->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
