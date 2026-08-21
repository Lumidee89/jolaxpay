<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['agent_id', 'key', 'name', 'threshold', 'unlocked_at'])]
class AgentAchievement extends Model
{
    protected function casts(): array
    {
        return ['threshold' => 'integer', 'unlocked_at' => 'datetime'];
    }
}
