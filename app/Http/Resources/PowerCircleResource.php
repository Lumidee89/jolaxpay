<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PowerCircleContact */
class PowerCircleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_name' => $this->contact_name,
            'relationship' => $this->relationship,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'linked_meter' => MeterResource::make($this->whenLoaded('linkedMeter')),
        ];
    }
}
