<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
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
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'delivery_destination' => $this->delivery_destination,
            'meter' => MeterResource::make($this->whenLoaded('meter')),
            'outcome_confirmed' => $this->outcome_confirmed,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
