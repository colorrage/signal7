<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'command', 'args', 'status', 'output', 'started_at', 'finished_at'])]
class CommandLog extends Model
{
    protected $table = 'command_logs';

    protected function casts(): array
    {
        return [
            'args' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function appendOutput(string $chunk): void
    {
        $this->forceFill([
            'output' => ($this->output ?? '').$chunk,
        ])->save();
    }

    protected function durationSeconds(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->started_at === null || $this->finished_at === null) {
                return null;
            }

            return $this->started_at->diffInSeconds($this->finished_at);
        });
    }
}
