<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'reason' => $this->reason,
            'amount' => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'currency' => $this->currency,
            'transaction_id' => $this->transaction_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
