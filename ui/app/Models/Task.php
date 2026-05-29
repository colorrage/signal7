<?php

namespace App\Models;

use App\Services\ComplianceParser;
use App\Services\ReviewParser;
use App\Support\GateState\AssetGateSummary;
use App\Support\GateState\ComplianceState;
use App\Support\GateState\GateState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['system', 'task_id', 'folder_name', 'title', 'phase', 'scope', 'is_archived', 'awaiting', 'created_at_disk', 'dashboard_summary', 'compliance_status'])]
class Task extends Model
{
    protected $table = 'tasks';

    /**
     * Per-request memoisation cache for gate_state.
     * Cleared when the model is refreshed from the database.
     */
    protected ?GateState $_gateStateCache = null;

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'created_at_disk' => 'datetime',
        ];
    }

    /**
     * Resolve the on-disk folder path for this task.
     *
     * Mirrors the path convention used by DiskSyncService:
     *   <base_path>/{tasks|archive}/<folder_name>
     */
    public function diskFolderPath(): string
    {
        $basePath = $this->system === 'signal7'
            ? config('disksync.signal')
            : config('disksync.hyper');

        $subdir = $this->is_archived ? 'archive' : 'tasks';

        return $basePath.'/'.$subdir.'/'.$this->folder_name;
    }

    /**
     * Read a file from the task's disk folder, returning empty string if missing.
     */
    protected function readDiskFile(string $filename): string
    {
        $path = $this->diskFolderPath().'/'.$filename;

        if (! file_exists($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }

    /**
     * Computed, read-only gate state value object.
     *
     * Composes:
     * - awaiting (passthrough from model)
     * - approvers[] from review.md via ReviewParser
     * - compliance from compliance.md via ComplianceParser
     * - assets[] summarised from the loaded Asset relations
     *
     * The result is memoised for the lifetime of the request to avoid
     * re-parsing the same files on each Livewire render cycle.
     * Call $task->refresh() to clear the cache when the model is reloaded.
     */
    public function getGateStateAttribute(): GateState
    {
        if ($this->_gateStateCache !== null) {
            return $this->_gateStateCache;
        }

        // Parse review.md
        $reviewMarkdown = $this->readDiskFile('review.md');
        $reviewResult = ReviewParser::parse($reviewMarkdown);
        $approvers = $reviewResult['approvers'];

        // Parse compliance.md
        $complianceMarkdown = $this->readDiskFile('compliance.md');
        $complianceResult = ComplianceParser::parse($complianceMarkdown);
        $compliance = new ComplianceState(
            status: $complianceResult['status'],
            openQuestions: $complianceResult['open_questions'] ?? [],
        );

        // Build asset summaries from the loaded relation.
        // Tolerates missing columns (expires_at, external_gate JSON) by falling back to null.
        $assetSummaries = [];
        if ($this->relationLoaded('assets')) {
            foreach ($this->assets as $asset) {
                $externalGate = null;
                if (! empty($asset->external_gate)) {
                    $raw = $asset->external_gate;
                    // After T10.3 lands, the cast decodes this to an array automatically.
                    // Before T10.3, the column may hold a raw JSON string or null.
                    if (is_array($raw)) {
                        $externalGate = $raw;
                    } elseif (is_string($raw) && $raw !== '') {
                        $decoded = json_decode($raw, true);
                        $externalGate = is_array($decoded) ? $decoded : null;
                    }
                }

                $expiresAt = null;
                // expires_at is a Carbon/DateTime after T10.3 lands; tolerate its absence.
                if (isset($asset->expires_at) && $asset->expires_at !== null) {
                    try {
                        $expiresAt = new \DateTimeImmutable($asset->expires_at instanceof \DateTimeInterface
                            ? $asset->expires_at->format(\DateTimeInterface::ATOM)
                            : (string) $asset->expires_at
                        );
                    } catch (\Throwable) {
                        $expiresAt = null;
                    }
                }

                $assetSummaries[] = new AssetGateSummary(
                    assetId: (string) ($asset->asset_id ?? ''),
                    externalGate: $externalGate,
                    expiresAt: $expiresAt,
                );
            }
        }

        $this->_gateStateCache = new GateState(
            awaiting: $this->awaiting,
            approvers: $approvers,
            compliance: $compliance,
            assets: $assetSummaries,
        );

        return $this->_gateStateCache;
    }

    /**
     * Override refresh() to clear the gate_state memoisation cache.
     */
    public function refresh(): static
    {
        $this->_gateStateCache = null;

        return parent::refresh();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }

    public function commandLogs(): HasMany
    {
        return $this->hasMany(CommandLog::class);
    }

    public function publishLogEntries(): HasMany
    {
        return $this->hasMany(PublishLogEntry::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeBySystem($query, string $system)
    {
        return $query->where('system', $system);
    }

    public function scopeAwaiting($query)
    {
        return $query->whereNotNull('awaiting');
    }
}
