<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Meter */
class MeterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'meter_number' => $this->meter_number,
            'meter_type' => $this->meter_type,
            'is_favorite' => $this->is_favorite,
            'is_saved' => $this->is_saved,
            'recipient_phone' => $this->recipient_phone,
            'recipient_email' => $this->recipient_email,
            'disco' => DiscoResource::make($this->whenLoaded('disco')),
            'group_id' => $this->group_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
