<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agent_id', 'campaign_id', 'issued_by', 'status', 'period_key', 'reward', 'rewarded_at', 'internal_note'])]
class AgentReward extends Model
{
    protected function casts(): array
    {
        return ['rewarded_at' => 'datetime'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ReferralCampaign::class, 'campaign_id');
    }
}
