<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Services\AssetRevisionService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AssetRevisionAuditPanel extends Component
{
    public int $assetId;

    public ?int $fromRevision = null;

    public ?int $toRevision = null;

    public function mount(int $assetId): void
    {
        $this->assetId = $assetId;
    }

    public function selectRevisions(?int $fromRevision, ?int $toRevision): void
    {
        $this->fromRevision = $fromRevision;
        $this->toRevision = $toRevision;
    }

    public function render(): View
    {
        $asset = Asset::with('task')->find($this->assetId);
        $service = app(AssetRevisionService::class);

        if ($asset === null || $asset->task === null) {
            return view('livewire.asset-revision-audit-panel', [
                'asset' => $asset,
                'available' => false,
                'message' => 'Asset task is unavailable.',
                'warnings' => ['Asset or task could not be resolved.'],
                'revisions' => [],
                'generationLog' => ['available' => false, 'rows' => [], 'warnings' => [], 'message' => null],
                'diff' => ['available' => false, 'rows' => [], 'warnings' => [], 'message' => null, 'from' => null, 'to' => null],
                'prompt' => null,
            ]);
        }

        $revisionResult = $service->revisionsFor($asset);
        $revisions = $revisionResult['revisions'];
        $revisionNumbers = array_map(fn (array $row): int => (int) $row['revision'], $revisions);

        if ($this->toRevision === null && count($revisionNumbers) > 0) {
            $this->toRevision = max($revisionNumbers);
        }

        if ($this->fromRevision === null && count($revisionNumbers) > 1) {
            $toIndex = array_search($this->toRevision, $revisionNumbers, true);
            $this->fromRevision = $toIndex !== false && $toIndex > 0
                ? $revisionNumbers[$toIndex - 1]
                : $revisionNumbers[0];
        }

        $generationLog = $service->generationLogFor($asset);
        $diff = $service->diffFor($asset, $this->fromRevision, $this->toRevision);
        $prompt = $service->promptFor($asset, $this->toRevision);

        $warnings = array_values(array_unique(array_merge(
            $revisionResult['warnings'],
            $generationLog['warnings'],
            $diff['warnings'],
        )));

        return view('livewire.asset-revision-audit-panel', [
            'asset' => $asset,
            'available' => $revisionResult['available'],
            'message' => $revisionResult['message'],
            'warnings' => $warnings,
            'revisions' => $revisions,
            'generationLog' => $generationLog,
            'diff' => $diff,
            'prompt' => $prompt,
        ]);
    }
}
