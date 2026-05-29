<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['system', 'name', 'filename', 'content_hash'])]
class Recipe extends Model
{
    protected $table = 'recipes';

    public function scopeBySystem($query, string $system)
    {
        return $query->where('system', $system);
    }
}
