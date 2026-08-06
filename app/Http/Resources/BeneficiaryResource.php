<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Beneficiary */
class BeneficiaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'identifier' => $this->identifier,
            'is_favorite' => $this->is_favorite,
            'recipient_phone' => $this->recipient_phone,
            'recipient_email' => $this->recipient_email,
            'biller' => BillerResource::make($this->whenLoaded('biller')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
