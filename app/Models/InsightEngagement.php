<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'insight_type', 'action'])]
class InsightEngagement extends Model
{
    public const UPDATED_AT = null;
}
