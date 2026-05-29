<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncDiskStateCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $signalRoot;

    private string $hyperRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/disksync-cmd-'.bin2hex(random_bytes(6));
        $this->signalRoot = $base.'/.signal';
        $this->hyperRoot = $base.'/.hyper';
        mkdir($this->signalRoot, 0o755, true);
        mkdir($this->hyperRoot, 0o755, true);

        $taskDir = $this->hyperRoot.'/tasks/T1-cmd';
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/task.md', "---\nid: T1\ntitle: Cmd Task\nphase: implement\nscope: feature\n---\n\n# Cmd Task\n\nBody.");

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

    public function test_disk_sync_with_no_flags_syncs_both_systems(): void
    {
        $this->artisan('disk:sync')
            ->expectsOutputToContain('Synced signal7:')
            ->expectsOutputToContain('Synced hyper7: 1 tasks')
            ->assertExitCode(0);

        $this->assertSame(1, Task::where('system', 'hyper7')->count());
    }

    public function test_disk_sync_with_system_flag_scopes_to_one_system(): void
    {
        $this->artisan('disk:sync', ['--system' => 'hyper7'])
            ->expectsOutputToContain('Synced hyper7: 1 tasks')
            ->assertExitCode(0);

        $this->assertSame(1, Task::count());
    }

    public function test_disk_sync_with_system_and_task_flags_targets_one_task(): void
    {
        $this->artisan('disk:sync', ['--system' => 'hyper7', '--task' => 'T1'])
            ->expectsOutputToContain('Synced single task: hyper7/T1')
            ->assertExitCode(0);

        $this->assertSame(1, Task::where('task_id', 'T1')->count());
    }

    public function test_disk_sync_with_task_but_no_system_errors(): void
    {
        $this->artisan('disk:sync', ['--task' => 'T1'])
            ->expectsOutputToContain('--task requires --system')
            ->assertExitCode(1);

        $this->assertSame(0, Task::count());
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
