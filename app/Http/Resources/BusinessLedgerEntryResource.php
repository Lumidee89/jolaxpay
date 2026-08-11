<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BusinessLedgerEntry */
class BusinessLedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'amount' => (float) $this->amount,
            'note' => $this->note,
            'entry_date' => $this->entry_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
