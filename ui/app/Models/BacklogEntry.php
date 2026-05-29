<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['system', 'entry_id', 'title', 'description', 'status'])]
class BacklogEntry extends Model
{
    protected $table = 'backlog_entries';

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeBySystem($query, string $system)
    {
        return $query->where('system', $system);
    }
}
