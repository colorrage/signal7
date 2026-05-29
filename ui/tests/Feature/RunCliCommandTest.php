<?php

namespace Tests\Feature;

use App\Events\CommandOutputChunk;
use App\Jobs\RunCliCommand;
use App\Models\CommandLog;
use App\Models\Task;
use App\Services\CommandRegistry;
use App\Services\DiskSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RunCliCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'commands.working_directory' => base_path(),
            'commands.commands' => [
                'test.success' => [
                    'label' => 'Success',
                    'description' => 'Successful command',
                    'system' => 'signal7',
                    'tokens' => ['php', '-r', 'fwrite(STDOUT, "ok");'],
                    'requires_task' => false,
                    'timeout' => 60,
                    'arguments' => [],
                ],
                'test.failure' => [
                    'label' => 'Failure',
                    'description' => 'Failing command',
                    'system' => 'signal7',
                    'tokens' => ['php', '-r', 'fwrite(STDERR, "bad"); exit(2);'],
                    'requires_task' => false,
                    'timeout' => 60,
                    'arguments' => [],
                ],
                'test.task' => [
                    'label' => 'Task',
                    'description' => 'Task command',
                    'system' => 'signal7',
                    'tokens' => ['php', '-r', 'fwrite(STDOUT, "task");'],
                    'requires_task' => true,
                    'timeout' => 60,
                    'arguments' => [],
                ],
            ],
        ]);
    }

    public function test_job_records_success_and_output(): void
    {
        Event::fake([CommandOutputChunk::class]);
        $log = CommandLog::create([
            'command' => 'test.success',
            'args' => [],
            'status' => 'queued',
        ]);

        $this->runJob($log);
        $log->refresh();

        $this->assertSame('done', $log->status);
        $this->assertSame('ok', $log->output);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->finished_at);
        Event::assertDispatched(CommandOutputChunk::class);
    }

    public function test_job_records_failure_and_stderr_output(): void
    {
        Event::fake([CommandOutputChunk::class]);
        $log = CommandLog::create([
            'command' => 'test.failure',
            'args' => [],
            'status' => 'queued',
        ]);

        $this->runJob($log);
        $log->refresh();

        $this->assertSame('failed', $log->status);
        $this->assertSame('bad', $log->output);
        $this->assertNotNull($log->finished_at);
        Event::assertDispatched(CommandOutputChunk::class);
    }

    public function test_task_scoped_completion_invokes_disk_sync(): void
    {
        $task = Task::create([
            'system' => 'signal7',
            'task_id' => 'S1',
            'folder_name' => 'S1-test',
            'title' => 'Test',
            'phase' => 'review',
            'scope' => 'quick',
            'is_archived' => false,
            'awaiting' => null,
        ]);
        $log = CommandLog::create([
            'task_id' => $task->id,
            'command' => 'test.task',
            'args' => [],
            'status' => 'queued',
        ]);
        $sync = new class extends DiskSyncService
        {
            /** @var array<int, array{system: string, task: string}> */
            public array $calls = [];

            public function __construct() {}

            public function syncTask(string $system, string $taskId): void
            {
                $this->calls[] = ['system' => $system, 'task' => $taskId];
            }
        };

        (new RunCliCommand($log->id))->handle(app(CommandRegistry::class), $sync);

        $this->assertSame([['system' => 'signal7', 'task' => 'S1']], $sync->calls);
    }

    private function runJob(CommandLog $log): void
    {
        (new RunCliCommand($log->id))->handle(
            app(CommandRegistry::class),
            app(DiskSyncService::class),
        );
    }
}
