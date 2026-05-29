<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'asset_id', 'idempotency_key', 'channel', 'status', 'published_at'])]
class PublishLogEntry extends Model
{
    protected $table = 'publish_log_entries';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
