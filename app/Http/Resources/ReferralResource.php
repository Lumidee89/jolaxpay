<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Referral */
class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'reward_type' => $this->reward_type,
            'reward_value' => $this->reward_value !== null ? (float) $this->reward_value : null,
            'referred_user' => UserResource::make($this->whenLoaded('referredUser')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
