<?php

namespace Tests\Feature;

use App\Contracts\CliCommandDispatcher;
use App\Jobs\RunCliCommand;
use App\Models\CommandLog;
use App\Models\Task;
use App\Services\QueuedCliCommandDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class QueuedCliCommandDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'commands.commands' => [
                'test.task' => [
                    'label' => 'Task command',
                    'description' => 'Task command',
                    'system' => 'signal7',
                    'tokens' => ['php', '-r', 'echo "task";'],
                    'requires_task' => true,
                    'timeout' => 60,
                    'arguments' => [],
                ],
                'test.taskless' => [
                    'label' => 'Taskless command',
                    'description' => 'Taskless command',
                    'system' => 'signal7',
                    'tokens' => ['php', '-r', 'echo "taskless";'],
                    'requires_task' => false,
                    'timeout' => 60,
                    'arguments' => [],
                ],
            ],
        ]);
    }

    public function test_container_resolves_real_dispatcher(): void
    {
        $this->assertInstanceOf(QueuedCliCommandDispatcher::class, app(CliCommandDispatcher::class));
    }

    public function test_dispatch_creates_command_log_and_queues_job(): void
    {
        Queue::fake();
        $task = $this->makeTask();

        $id = app(CliCommandDispatcher::class)->dispatch($task, 'test.task');

        $this->assertDatabaseHas('command_logs', [
            'id' => (int) $id,
            'task_id' => $task->id,
            'command' => 'test.task',
            'status' => 'queued',
        ]);
        Queue::assertPushed(RunCliCommand::class);
    }

    public function test_dispatch_taskless_creates_unattached_command_log(): void
    {
        Queue::fake();

        $id = app(CliCommandDispatcher::class)->dispatchTaskless('test.taskless');

        $this->assertDatabaseHas('command_logs', [
            'id' => (int) $id,
            'task_id' => null,
            'command' => 'test.taskless',
            'status' => 'queued',
        ]);
        Queue::assertPushed(RunCliCommand::class);
    }

    public function test_dispatch_rejects_running_command_for_same_task(): void
    {
        Queue::fake();
        $task = $this->makeTask();
        CommandLog::create([
            'task_id' => $task->id,
            'command' => 'test.task',
            'args' => [],
            'status' => 'running',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already running');

        app(CliCommandDispatcher::class)->dispatch($task, 'test.task');
    }

    private function makeTask(): Task
    {
        return Task::create([
            'system' => 'signal7',
            'task_id' => 'S1',
            'folder_name' => 'S1-test',
            'title' => 'Test',
            'phase' => 'review',
            'scope' => 'quick',
            'is_archived' => false,
            'awaiting' => null,
        ]);
    }
}
