<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'avatar_url' => $this->avatar_path ? Storage::disk('uploads')->url($this->avatar_path) : null,
            'phone_verified' => $this->phone_verified_at !== null,
            'email_verified' => $this->email_verified_at !== null,
            'country_code' => $this->country_code,
            'is_diaspora' => $this->is_diaspora,
            'account_type' => $this->account_type?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
