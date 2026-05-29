<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetRevisionHistoryModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_detail_modal_labels_cached_revision(): void
    {
        $asset = $this->makeAsset(['revision' => 3]);

        $html = view('filament.clusters.signal7.asset-detail-modal', ['record' => $asset])->render();

        $this->assertStringContainsString('Cached revision:', $html);
        $this->assertStringContainsString('3', $html);
    }

    public function test_revision_history_modal_embeds_audit_panel_by_asset_id(): void
    {
        $asset = $this->makeAsset();

        $html = view('filament.clusters.signal7.asset-revision-history-modal', ['record' => $asset])->render();

        $this->assertStringContainsString('asset-revision-audit-'.$asset->id, $html);
    }

    private function makeAsset(array $overrides = []): Asset
    {
        $task = Task::create([
            'system' => 'signal7',
            'task_id' => 'S99',
            'folder_name' => 'S99-test-campaign',
            'title' => 'Test Campaign',
            'phase' => 'create',
            'scope' => 'campaign',
            'is_archived' => false,
        ]);

        return Asset::create(array_merge([
            'task_id' => $task->id,
            'asset_id' => 'A1',
            'title' => 'Asset One',
            'asset_type' => 'social-copy',
            'status' => 'done',
            'revision' => 0,
        ], $overrides));
    }
}
