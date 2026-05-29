<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AssetRevisionAuditPanel;
use App\Models\Asset;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class AssetRevisionAuditPanelTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private string $taskDir;

    private Task $task;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/asset-audit-panel-'.bin2hex(random_bytes(6));
        $signalRoot = $this->root.'/.signal';
        $this->taskDir = $signalRoot.'/tasks/S99-test-campaign';
        mkdir($this->taskDir.'/prompts', 0755, true);
        config(['disksync.signal' => $signalRoot]);

        $this->task = Task::create([
            'system' => 'signal7',
            'task_id' => 'S99',
            'folder_name' => 'S99-test-campaign',
            'title' => 'Test Campaign',
            'phase' => 'create',
            'scope' => 'campaign',
            'is_archived' => false,
        ]);

        $this->asset = Asset::create([
            'task_id' => $this->task->id,
            'asset_id' => 'A1',
            'title' => 'Asset One',
            'asset_type' => 'social-copy',
            'status' => 'done',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_renders_revision_prompt_generation_log_and_default_diff(): void
    {
        $this->writeAsset("---\nid: A1\ngeneration_log:\n  - model: gpt-x\n    worker: signal-social\n    worker_version: v1\n    prompt_hash: ph2\n    content_hash: ch2\n    timestamp: 2026-05-01T10:00:00\n---\n\nBody");
        $this->writePrompt('A1-r1-signal-social.md', "---\nmodel: gpt-x\nprompt_hash: ph1\ncontent_hash: ch1\n---\n\nFirst line\nOld line");
        $this->writePrompt('A1-r2-signal-social.md', "---\nmodel: gpt-x\nprompt_hash: ph2\ncontent_hash: ch2\n---\n\nFirst line\nNew line");

        Livewire::test(AssetRevisionAuditPanel::class, ['assetId' => $this->asset->id])
            ->assertSet('assetId', $this->asset->id)
            ->assertSee('Revision History')
            ->assertSee('r1')
            ->assertSee('r2')
            ->assertSee('Prompt Audit')
            ->assertSee('ph2')
            ->assertSee('Generation Log')
            ->assertSee('v1')
            ->assertSee('Old line')
            ->assertSee('New line');
    }

    public function test_selected_revision_comparison_updates_diff(): void
    {
        $this->writePrompt('A1-r1-signal-social.md', "Alpha\nBeta");
        $this->writePrompt('A1-r2-signal-social.md', "Alpha\nGamma");
        $this->writePrompt('A1-r3-signal-social.md', "Alpha\nDelta");

        Livewire::test(AssetRevisionAuditPanel::class, ['assetId' => $this->asset->id])
            ->call('selectRevisions', 1, 3)
            ->assertSet('fromRevision', 1)
            ->assertSet('toRevision', 3)
            ->assertSee('Beta')
            ->assertSee('Delta');
    }

    public function test_missing_task_renders_unavailable_state(): void
    {
        Livewire::test(AssetRevisionAuditPanel::class, ['assetId' => 999999])
            ->assertSee('Asset task is unavailable.')
            ->assertSee('Asset or task could not be resolved.');
    }

    public function test_oversized_diff_message_renders(): void
    {
        $this->writePrompt('A1-r1-signal-social.md', 'short');
        $this->writePrompt('A1-r2-signal-social.md', str_repeat('x', 51201));

        Livewire::test(AssetRevisionAuditPanel::class, ['assetId' => $this->asset->id])
            ->assertSee('Diff unavailable for revisions above 500 lines or 50 KB.');
    }

    private function writePrompt(string $filename, string $content): void
    {
        file_put_contents($this->taskDir.'/prompts/'.$filename, $content);
    }

    private function writeAsset(string $content): void
    {
        file_put_contents($this->taskDir.'/A1-linkedin.md', $content);
    }
}
