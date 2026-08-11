<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category', 'question', 'answer', 'sort_order', 'is_published'])]
class FaqArticle extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
