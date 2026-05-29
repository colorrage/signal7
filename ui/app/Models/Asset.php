<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'asset_id', 'title', 'asset_type', 'channel', 'language', 'status', 'revision', 'depends', 'external_gate', 'publish_at', 'expires_at', 'body', 'prompt'])]
class Asset extends Model
{
    protected $table = 'assets';

    protected function casts(): array
    {
        return [
            'depends' => 'array',
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
            'external_gate' => 'array',
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

    public function scopeByType($query, string $type)
    {
        return $query->where('asset_type', $type);
    }
}
