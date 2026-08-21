<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['leaderboard_enabled', 'ranking_metric', 'active_min_transactions', 'visible_positions', 'ranking_period', 'milestones', 'promotional_message'])]
class ReferralSetting extends Model
{
    protected function casts(): array
    {
        return ['leaderboard_enabled' => 'boolean', 'active_min_transactions' => 'integer', 'visible_positions' => 'integer', 'milestones' => 'array'];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], ['milestones' => []]);
    }
}
