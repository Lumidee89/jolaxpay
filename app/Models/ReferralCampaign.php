<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'starts_at', 'ends_at', 'ranking_metric', 'is_active', 'promotional_message', 'reward_details'])]
class ReferralCampaign extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'reward_details' => 'array'];
    }
}
