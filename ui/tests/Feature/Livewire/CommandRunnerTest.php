<?php

namespace Tests\Feature\Livewire;

use App\Contracts\CliCommandDispatcher;
use App\Livewire\CommandRunner;
use App\Models\CommandLog;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandRunnerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{task_id: int|null, command: string, args: array}> */
    private array $dispatchCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'commands.commands' => [
                'signal.run' => [
                    'label' => 'Continue Signal7 task',
                    'description' => 'Run Signal7',
                    'system' => 'signal7',
                    'tokens' => ['claude', '/signal'],
                    'requires_task' => false,
                    'timeout' => 600,
                    'arguments' => [
                        ['name' => 'input', 'label' => 'Input', 'type' => 'textarea', 'required' => false, 'placement' => 'positional'],
                    ],
                ],
                'signal.approve' => [
                    'label' => 'Approve Signal7 gate',
                    'description' => 'Approve',
                    'system' => 'signal7',
                    'tokens' => ['claude', '/signal'],
                    'requires_task' => true,
                    'timeout' => 600,
                    'arguments' => [
                        ['name' => 'response', 'label' => 'Response', 'type' => 'hidden', 'required' => true, 'default' => 'continue', 'placement' => 'positional'],
                    ],
                ],
            ],
        ]);

        $calls = &$this->dispatchCalls;
        $this->app->bind(CliCommandDispatcher::class, function () use (&$calls) {
            return new class($calls) implements CliCommandDispatcher
            {
                public function __construct(private array &$calls) {}

                public function dispatch(Task $task, string $command, array $args = []): string
                {
                    $this->calls[] = ['task_id' => $task->id, 'command' => $command, 'args' => $args];

                    return (string) CommandLog::create([
                        'task_id' => $task->id,
                        'command' => $command,
                        'args' => $args,
                        'status' => 'queued',
                        'output' => '',
                    ])->id;
                }

                public function dispatchTaskless(string $command, array $args = []): string
                {
                    $this->calls[] = ['task_id' => null, 'command' => $command, 'args' => $args];

                    return (string) CommandLog::create([
                        'command' => $command,
                        'args' => $args,
                        'status' => 'queued',
                        'output' => '',
                    ])->id;
                }
            };
        });
    }

    public function test_renders_command_selector_and_preview(): void
    {
        Livewire::test(CommandRunner::class)
            ->set('selectedCommand', 'signal.run')
            ->set('arguments.input', 'hello world')
            ->assertSee('Continue Signal7 task')
            ->assertSee("claude /signal 'hello world'");
    }

    public function test_prefilled_query_hydrates_confirmation_state(): void
    {
        Livewire::withQueryParams([
            'command' => 'signal.run',
            'arg_input' => 'continue',
        ])
            ->test(CommandRunner::class)
            ->assertSet('selectedCommand', 'signal.run')
            ->assertSet('confirming', true)
            ->assertSee('claude /signal continue');
    }

    public function test_run_dispatches_taskless_command_and_loads_log(): void
    {
        Livewire::test(CommandRunner::class)
            ->set('selectedCommand', 'signal.run')
            ->set('arguments.input', 'continue')
            ->call('confirm')
            ->call('run')
            ->assertSet('status', 'queued');

        $this->assertSame('signal.run', $this->dispatchCalls[0]['command']);
        $this->assertNull($this->dispatchCalls[0]['task_id']);
    }

    public function test_run_dispatches_task_scoped_command(): void
    {
        $task = Task::create([
            'system' => 'signal7',
            'task_id' => 'S1',
            'folder_name' => 'S1-test',
            'title' => 'Test',
            'phase' => 'review',
            'scope' => 'quick',
            'is_archived' => false,
            'awaiting' => 'user-approval',
        ]);

        Livewire::test(CommandRunner::class)
            ->set('selectedCommand', 'signal.approve')
            ->set('taskContext', (string) $task->id)
            ->call('confirm')
            ->call('run');

        $this->assertSame($task->id, $this->dispatchCalls[0]['task_id']);
        $this->assertSame('signal.approve', $this->dispatchCalls[0]['command']);
    }

    public function test_recent_history_renders_output(): void
    {
        CommandLog::create([
            'command' => 'signal.run',
            'args' => [],
            'status' => 'done',
            'output' => 'finished',
        ]);

        Livewire::test(CommandRunner::class)
            ->assertSee('Recent Commands')
            ->assertSee('finished');
    }
}
