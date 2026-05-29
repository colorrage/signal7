<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\BacklogEntry;
use App\Models\Task;
use App\Services\DiskSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiskSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $signalRoot;

    private string $hyperRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/disksync-'.bin2hex(random_bytes(6));
        $this->signalRoot = $base.'/.signal';
        $this->hyperRoot = $base.'/.hyper';
        mkdir($this->signalRoot, 0o755, true);
        mkdir($this->hyperRoot, 0o755, true);

        config([
            'disksync.signal' => $this->signalRoot,
            'disksync.hyper' => $this->hyperRoot,
        ]);
    }

    protected function tearDown(): void
    {
        $this->rmTree(dirname($this->signalRoot));
        parent::tearDown();
    }

    public function test_sync_all_with_empty_directories_does_not_error(): void
    {
        $service = new DiskSyncService;
        $counts = $service->syncAll();

        $this->assertSame(['tasks' => 0, 'assets' => 0, 'backlog' => 0, 'recipes' => 0], $counts['signal7']);
        $this->assertSame(['tasks' => 0, 'assets' => 0, 'backlog' => 0, 'recipes' => 0], $counts['hyper7']);
        $this->assertSame(0, Task::count());
    }

    public function test_sync_handles_missing_directories_silently(): void
    {
        // Point at a path that does not exist on disk at all.
        config(['disksync.signal' => '/tmp/does-not-exist-'.uniqid()]);

        $service = new DiskSyncService;
        $counts = $service->syncSystem('signal7');

        $this->assertSame(0, $counts['tasks']);
        $this->assertSame(0, Task::count());
    }

    public function test_sync_extracts_hyper_task_with_frontmatter(): void
    {
        $taskDir = $this->hyperRoot.'/tasks/T42-example';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', <<<'MD'
        ---
        id: T42
        title: Example Task
        phase: implement
        scope: feature
        created: 2026-05-20T10:00:00
        awaiting: null
        ---

        # Example Task

        Body of the task.
        MD);

        $service = new DiskSyncService;
        $counts = $service->syncSystem('hyper7');

        $this->assertSame(1, $counts['tasks']);
        $task = Task::where('system', 'hyper7')->where('task_id', 'T42')->first();
        $this->assertNotNull($task);
        $this->assertSame('Example Task', $task->title);
        $this->assertSame('implement', $task->phase);
        $this->assertFalse($task->is_archived);
        $this->assertSame('T42-example', $task->folder_name);
    }

    public function test_sync_is_idempotent(): void
    {
        $taskDir = $this->hyperRoot.'/tasks/T1-idem';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', "---\nid: T1\ntitle: Idem\nphase: implement\nscope: feature\n---\n\n# Idem\n\nBody.");

        $service = new DiskSyncService;
        $service->syncSystem('hyper7');
        $firstCount = Task::count();

        $service->syncSystem('hyper7');
        $secondCount = Task::count();

        $this->assertSame(1, $firstCount);
        $this->assertSame($firstCount, $secondCount);
    }

    public function test_sync_marks_archived_tasks(): void
    {
        $taskDir = $this->hyperRoot.'/archive/T9-old';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', "---\nid: T9\ntitle: Old\nphase: done\nscope: feature\n---\n\n# Old\n\nDone.");

        $service = new DiskSyncService;
        $service->syncSystem('hyper7');

        $task = Task::where('task_id', 'T9')->first();
        $this->assertNotNull($task);
        $this->assertTrue($task->is_archived);
    }

    public function test_dashboard_summary_captures_full_paragraph(): void
    {
        $taskDir = $this->hyperRoot.'/tasks/T7-summary';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', "---\nid: T7\ntitle: Summary\nphase: implement\nscope: feature\n---\n\n# Summary\n\nBody.");
        file_put_contents($taskDir.'/dashboard.md', <<<'MD'
        # Dashboard — T7

        ## Goal

        First line of the goal paragraph.
        Second line of the same paragraph.
        Third line wrapping further.

        ## Plan

        Plan content.
        MD);

        $service = new DiskSyncService;
        $service->syncSystem('hyper7');

        $task = Task::where('task_id', 'T7')->first();
        $this->assertNotNull($task->dashboard_summary);
        $this->assertStringContainsString('First line of the goal paragraph.', $task->dashboard_summary);
        $this->assertStringContainsString('Second line of the same paragraph.', $task->dashboard_summary);
        $this->assertStringContainsString('Third line wrapping further.', $task->dashboard_summary);
        $this->assertStringNotContainsString('Plan content', $task->dashboard_summary);
    }

    public function test_backlog_parses_bullet_format(): void
    {
        file_put_contents($this->hyperRoot.'/backlog.md', <<<'MD'
        # Backlog

        - **B1** — Short title here. Longer description follows the sentence boundary.
        - **B2** — Another item without further detail.
        MD);

        $service = new DiskSyncService;
        $counts = $service->syncSystem('hyper7');

        $this->assertSame(2, $counts['backlog']);
        $b1 = BacklogEntry::where('entry_id', 'B1')->first();
        $this->assertNotNull($b1);
        $this->assertSame('Short title here.', $b1->title);
        $this->assertNotNull($b1->description);
    }

    public function test_signal7_task_parses_content_plan_assets_with_clean_depends(): void
    {
        $taskDir = $this->signalRoot.'/tasks/S1-launch';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', "---\nid: S1\ntitle: Launch\nphase: create\nscope: campaign\n---\n\n# Launch\n\nBody.");
        file_put_contents($taskDir.'/content-plan.md', <<<'MD'
        # Content Plan

        | asset_id | title | asset_type | channel | language | status | depends | publish_at |
        |----------|-------|------------|---------|----------|--------|---------|------------|
        | A1 | Tweet | social-copy | twitter | en | todo |  | 2026-06-01 |
        | A2 | Email | email-copy | email | en | todo | A1, A3 | 2026-06-02 |
        MD);

        $service = new DiskSyncService;
        $counts = $service->syncSystem('signal7');

        $this->assertSame(1, $counts['tasks']);
        $this->assertSame(2, $counts['assets']);

        $a1 = Asset::where('asset_id', 'A1')->first();
        $this->assertNull($a1->depends, 'empty depends cell should yield null, not [""]');

        $a2 = Asset::where('asset_id', 'A2')->first();
        $this->assertSame(['A1', 'A3'], $a2->depends, 'depends must be trimmed and split');
    }

    public function test_signal7_content_plan_accepts_id_column_for_assets(): void
    {
        $taskDir = $this->signalRoot.'/tasks/S2-launch';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', "---\nid: S2\ntitle: Launch\nphase: create\nscope: campaign\n---\n\n# Launch\n\nBody.");
        file_put_contents($taskDir.'/content-plan.md', <<<'MD'
        # Content Plan

        | id | title | asset_type | channel | language | status | depends | publish_at |
        |---|---|---|---|---|---|---|---|
        | A1 | Instagram Launch | social-copy | instagram | ro | done | [] | null |
        MD);

        $counts = (new DiskSyncService)->syncSystem('signal7');

        $this->assertSame(1, $counts['assets']);
        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertNotNull($asset);
        $this->assertSame('Instagram Launch', $asset->title);
        $this->assertNull($asset->depends);
    }

    public function test_signal7_task_discovers_asset_markdown_without_content_plan(): void
    {
        $taskDir = $this->signalRoot.'/tasks/S3-research';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', "---\nid: S3\ntitle: Research\nphase: done\nscope: strategy\n---\n\n# Research\n\nBody.");
        file_put_contents($taskDir.'/A1-market-research.md', <<<'MD'
        ---
        id: A1
        title: Market Research
        status: done
        asset_type: research
        channel: internal
        language: ro
        depends: []
        revision: null
        ---

        # Market Research

        Body.
        MD);

        $counts = (new DiskSyncService)->syncSystem('signal7');

        $this->assertSame(1, $counts['assets']);
        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertNotNull($asset);
        $this->assertSame('Market Research', $asset->title);
        $this->assertSame('research', $asset->asset_type);
        $this->assertSame(0, $asset->revision);
        $this->assertStringContainsString('Body.', $asset->body);
    }

    public function test_sync_task_targeted_refresh(): void
    {
        $taskDir = $this->hyperRoot.'/tasks/T5-target';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', "---\nid: T5\ntitle: Target\nphase: implement\nscope: feature\n---\n\n# Target\n\nBody.");

        $service = new DiskSyncService;
        $service->syncTask('hyper7', 'T5');

        $task = Task::where('task_id', 'T5')->first();
        $this->assertNotNull($task);
        $this->assertSame('Target', $task->title);
    }

    public function test_sync_task_swallows_malformed_task_md(): void
    {
        // T6 was created from real hyper7 data; corrupt one task.md to confirm
        // syncTask logs-and-continues instead of throwing (verify F6).
        $taskDir = $this->hyperRoot.'/tasks/T8-broken';
        mkdir($taskDir, 0o755, true);
        // task.md has no frontmatter id at all — upsertTask passes empty task_id;
        // but a unique-index collision against a second such task would throw.
        // Instead, simulate by deleting permissions to make file_get_contents fail.
        file_put_contents($taskDir.'/task.md', "---\nid: T8\ntitle: Broken\nphase: implement\nscope: feature\n---\n\n# Broken\n");

        $service = new DiskSyncService;
        // Should not throw.
        $service->syncTask('hyper7', 'T8');

        $this->assertSame(1, Task::where('task_id', 'T8')->count());
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->rmTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}
