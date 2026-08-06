<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MeterGroup */
class MeterGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'meters' => MeterResource::collection($this->whenLoaded('meters')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
