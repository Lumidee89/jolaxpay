<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Biller */
class BillerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'service_type' => $this->service_type,
            'identifier_label' => $this->identifier_label,
            'requires_billers_code' => $this->requires_billers_code,
            'requires_variation' => $this->requires_variation,
            'supports_verify' => $this->supports_verify,
            'health_status' => $this->health_status,
            'variations' => BillerVariationResource::collection($this->whenLoaded('variations')),
        ];
    }
}
