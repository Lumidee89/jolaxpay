<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Disco */
class DiscoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'region' => $this->region,
            'service_type' => $this->service_type,
            'health_status' => $this->health_status,
            'health_checked_at' => $this->health_checked_at?->toIso8601String(),
        ];
    }
}
