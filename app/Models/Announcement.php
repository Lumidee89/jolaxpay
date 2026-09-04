<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'body', 'is_published'])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
