<?php

namespace Tests\Feature;

use App\Contracts\CliCommandDispatcher;
use App\Filament\Clusters\Signal7\Resources\TaskResource\Pages\ViewTask;
use App\Livewire\PublishPanel;
use App\Models\Asset;
use App\Models\PublishLogEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublishPanelTest extends TestCase
{
    use RefreshDatabase;

    private const COMPLIANCE_CLEAR_MD = <<<'MD'
---
status: clear
---

# Compliance
MD;

    private const COMPLIANCE_BLOCKED_MD = <<<'MD'
---
status: blocked
---

# Compliance

Blocked pending legal review.
MD;

    private string $signalRoot;

    private string $taskDir;

    /** @var array<int, array{task_id: int|null, command: string, args: array}> */
    private array $dispatchCalls = [];

    /** @var array<int, string> */
    private array $tempRoots = [];

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/publish-panel-'.bin2hex(random_bytes(6));
        $this->signalRoot = $base.'/.signal';
        $this->taskDir = $this->signalRoot.'/tasks/S1-test-task';
        mkdir($this->taskDir, 0o755, true);
        $this->tempRoots[] = $base;
        config(['disksync.signal' => $this->signalRoot]);

        $calls = &$this->dispatchCalls;
        $this->app->bind(CliCommandDispatcher::class, function () use (&$calls) {
            return new class($calls) implements CliCommandDispatcher
            {
                public function __construct(private array &$calls) {}

                public function dispatch(Task $task, string $command, array $args = []): string
                {
                    $this->calls[] = [
                        'task_id' => $task->id ?? null,
                        'command' => $command,
                        'args' => $args,
                    ];

                    return 'fake-'.count($this->calls);
                }

                public function dispatchTaskless(string $command, array $args = []): string
                {
                    $this->calls[] = [
                        'task_id' => null,
                        'command' => $command,
                        'args' => $args,
                    ];

                    return 'fake-'.count($this->calls);
                }
            };
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $this->rmTree($root);
        }

        parent::tearDown();
    }

    public function test_ready_assets_show_ready_badge(): void
    {
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);
        $task = $this->makeTask();
        $this->makeAsset($task, ['asset_id' => 'A1', 'title' => 'Ready Asset']);

        Livewire::test(PublishPanel::class, ['task' => $task])
            ->assertSee('Ready to publish')
            ->assertSee('Ready Asset')
            ->assertSee('signal7:S1:A1:social-copy')
            ->assertSee('No publish log entries yet.');
    }

    public function test_blocked_assets_show_blocked_count(): void
    {
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);
        $task = $this->makeTask();
        $this->makeAsset($task, [
            'asset_id' => 'A1',
            'external_gate' => ['status' => 'pending', 'description' => 'Legal approval required'],
        ]);
        $this->makeAsset($task, [
            'asset_id' => 'A2',
            'expires_at' => now()->subDay(),
        ]);

        Livewire::test(PublishPanel::class, ['task' => $task])
            ->assertSee('2 assets blocked')
            ->assertSee('External gate: pending')
            ->assertSee('Asset has expired');
    }

    public function test_publish_all_dispatches_signal_publish_without_extra_args(): void
    {
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);
        $task = $this->makeTask();
        $this->makeAsset($task);

        Livewire::test(PublishPanel::class, ['task' => $task])
            ->call('publishAll');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame($task->id, $this->dispatchCalls[0]['task_id']);
        $this->assertSame('signal.publish', $this->dispatchCalls[0]['command']);
        $this->assertSame([], $this->dispatchCalls[0]['args']);
    }

    public function test_publish_selected_dispatches_asset_filter(): void
    {
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);
        $task = $this->makeTask();
        $this->makeAsset($task, ['asset_id' => 'A1']);
        $this->makeAsset($task, ['asset_id' => 'A2']);

        Livewire::test(PublishPanel::class, ['task' => $task])
            ->set('selectedAssets', ['A2', 'A1'])
            ->call('publishSelected');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame('signal.publish', $this->dispatchCalls[0]['command']);
        $this->assertSame(['assets' => 'A2,A1'], $this->dispatchCalls[0]['args']);
    }

    public function test_compliance_block_prevents_publish_dispatch(): void
    {
        $this->writeFile('compliance.md', self::COMPLIANCE_BLOCKED_MD);
        $task = $this->makeTask();
        $this->makeAsset($task);

        Livewire::test(PublishPanel::class, ['task' => $task])
            ->call('publishAll');

        $this->assertSame([], $this->dispatchCalls);
    }

    public function test_publish_log_entries_render(): void
    {
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);
        $task = $this->makeTask();
        $this->makeAsset($task, ['asset_id' => 'A1']);

        PublishLogEntry::create([
            'task_id' => $task->id,
            'asset_id' => 'A1',
            'idempotency_key' => 'signal7:S1:A1:social-copy',
            'channel' => 'linkedin',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Livewire::test(PublishPanel::class, ['task' => $task])
            ->assertSee('Publish Log')
            ->assertSee('linkedin')
            ->assertSee('published')
            ->assertSee('signal7:S1:A1:social-copy');
    }

    public function test_signal7_view_task_publish_tab_is_visible_only_for_publish_phase(): void
    {
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);
        $task = $this->makeTask(['phase' => 'publish']);
        $this->makeAsset($task, ['asset_id' => 'A1']);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('Overview')
            ->assertSee('Publish')
            ->set('activeTaskTab', 'publish')
            ->assertSee('Publish Readiness')
            ->assertSee('Publish All');
    }

    public function test_signal7_view_task_hides_publish_tab_before_publish_phase(): void
    {
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);
        $task = $this->makeTask(['phase' => 'review']);
        $this->makeAsset($task, ['asset_id' => 'A1']);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ViewTask::class, ['record' => $task->getKey()])
            ->assertDontSee('Publish Readiness')
            ->assertDontSee('Publish All');
    }

    private function makeTask(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'system' => 'signal7',
            'task_id' => 'S1',
            'folder_name' => 'S1-test-task',
            'title' => 'Test Campaign',
            'phase' => 'publish',
            'scope' => 'campaign',
            'is_archived' => false,
            'awaiting' => null,
        ], $overrides));
    }

    private function makeAsset(Task $task, array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'task_id' => $task->id,
            'asset_id' => 'A1',
            'title' => 'Test Asset',
            'asset_type' => 'social-copy',
            'channel' => 'linkedin',
            'language' => 'en',
            'status' => 'done',
            'revision' => 1,
            'depends' => null,
            'publish_at' => null,
            'external_gate' => null,
            'expires_at' => null,
            'body' => null,
            'prompt' => null,
        ], $overrides));
    }

    private function writeFile(string $filename, string $content): void
    {
        file_put_contents($this->taskDir.'/'.$filename, $content);
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            is_dir($path) ? $this->rmTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
