<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Task;
use App\Services\AssetRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AssetRevisionServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private string $taskDir;

    private Task $task;

    private Asset $asset;

    private AssetRevisionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/asset-revisions-'.bin2hex(random_bytes(6));
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

        $this->service = new AssetRevisionService;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_revisions_are_parsed_and_sorted_by_integer_revision(): void
    {
        $this->writePrompt('A1-r10-signal-social.md', "---\nmodel: gpt-x\nprompt_hash: ph10\ncontent_hash: ch10\ntimestamp: 2026-05-01T10:00:00\n---\n\nPrompt ten");
        $this->writePrompt('A1-r2-signal-social.md', "---\nmodel: gpt-y\nprompt_hash: ph2\ncontent_hash: ch2\n---\n\nPrompt two");

        $result = $this->service->revisionsFor($this->asset);

        $this->assertTrue($result['available']);
        $this->assertSame([2, 10], array_column($result['revisions'], 'revision'));
        $this->assertSame('signal-social', $result['revisions'][0]['worker']);
        $this->assertSame('gpt-y', $result['revisions'][0]['model']);
        $this->assertSame('ph10', $result['revisions'][1]['prompt_hash']);
    }

    public function test_generation_log_normalizes_yaml_array(): void
    {
        $this->writeAsset("---\nid: A1\ngeneration_log:\n  - model: gpt-x\n    worker: signal-social\n    worker_version: v1\n    prompt_hash: ph\n    content_hash: ch\n    timestamp: 2026-05-01T10:00:00\n---\n\nBody");

        $result = $this->service->generationLogFor($this->asset);

        $this->assertTrue($result['available']);
        $this->assertSame('gpt-x', $result['rows'][0]['model']);
        $this->assertSame('signal-social', $result['rows'][0]['worker']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_generation_log_scalar_returns_empty_log_with_warning(): void
    {
        $this->writeAsset("---\nid: A1\ngeneration_log: nope\n---\n\nBody");

        $result = $this->service->generationLogFor($this->asset);

        $this->assertTrue($result['available']);
        $this->assertSame([], $result['rows']);
        $this->assertContains('generation_log is missing or not a YAML array.', $result['warnings']);
    }

    public function test_diff_defaults_to_previous_and_latest_revision(): void
    {
        $this->writePrompt('A1-r1-signal-social.md', "Line one\nLine two");
        $this->writePrompt('A1-r2-signal-social.md', "Line one\nLine changed");

        $result = $this->service->diffFor($this->asset);

        $this->assertTrue($result['available']);
        $this->assertSame(1, $result['from']);
        $this->assertSame(2, $result['to']);
        $this->assertContains(['type' => 'deleted', 'left' => 'Line two', 'right' => ''], $result['rows']);
        $this->assertContains(['type' => 'added', 'left' => '', 'right' => 'Line changed'], $result['rows']);
    }

    public function test_diff_refuses_oversized_revision(): void
    {
        $this->writePrompt('A1-r1-signal-social.md', 'short');
        $this->writePrompt('A1-r2-signal-social.md', str_repeat('x', 51201));

        $result = $this->service->diffFor($this->asset);

        $this->assertFalse($result['available']);
        $this->assertSame('Diff unavailable for revisions above 500 lines or 50 KB.', $result['message']);
    }

    public function test_missing_task_returns_unavailable_state(): void
    {
        $asset = new Asset([
            'task_id' => 999999,
            'asset_id' => 'A1',
            'title' => 'Orphaned Asset',
            'asset_type' => 'social-copy',
            'status' => 'done',
        ]);

        $result = $this->service->revisionsFor($asset);

        $this->assertFalse($result['available']);
        $this->assertSame('Asset task is unavailable.', $result['message']);
    }

    public function test_missing_task_folder_returns_unavailable_state(): void
    {
        File::deleteDirectory($this->taskDir);

        $result = $this->service->revisionsFor($this->asset);

        $this->assertFalse($result['available']);
        $this->assertSame('Task folder is unavailable.', $result['message']);
    }

    public function test_ambiguous_asset_file_surfaces_warning(): void
    {
        $this->writeAsset("---\nid: A1\n---\n\nBody", 'A1-linkedin.md');
        file_put_contents($this->taskDir.'/A1-twitter.md', "---\nid: A1\n---\n\nOther");

        $result = $this->service->generationLogFor($this->asset);

        $this->assertStringContainsString('Multiple asset markdown files matched A1', implode("\n", $result['warnings']));
    }

    private function writePrompt(string $filename, string $content): void
    {
        file_put_contents($this->taskDir.'/prompts/'.$filename, $content);
    }

    private function writeAsset(string $content, string $filename = 'A1-linkedin.md'): void
    {
        file_put_contents($this->taskDir.'/'.$filename, $content);
    }
}
