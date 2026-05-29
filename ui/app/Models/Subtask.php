<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'task_id', 'subtask_id', 'parent_subtask_id', 'title',
    'status', 'awaiting', 'role', 'depends', 'writes', 'body', 'position',
])]
class Subtask extends Model
{
    use HasFactory;

    protected $table = 'subtasks';

    protected function casts(): array
    {
        return [
            'depends' => 'array',
            'writes' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeBySubtaskId($query, string $id)
    {
        return $query->where('subtask_id', $id);
    }
}
