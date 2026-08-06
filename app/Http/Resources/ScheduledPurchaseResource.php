<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ScheduledPurchase */
class ScheduledPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meter' => MeterResource::make($this->whenLoaded('meter')),
            'amount' => (float) $this->amount,
            'frequency' => $this->frequency,
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'active' => $this->active,
        ];
    }
}
