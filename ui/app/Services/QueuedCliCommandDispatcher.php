<?php

namespace App\Services;

use App\Contracts\CliCommandDispatcher;
use App\Jobs\RunCliCommand;
use App\Models\CommandLog;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class QueuedCliCommandDispatcher implements CliCommandDispatcher
{
    public function __construct(private CommandRegistry $registry) {}

    public function dispatch(Task $task, string $command, array $args = []): string
    {
        if (! $this->registry->has($command)) {
            throw new RuntimeException("Command [{$command}] is not registered.");
        }

        $this->registry->tokens($command, $args, $task);

        if ($this->hasRunningCommand($task)) {
            throw new RuntimeException('A command is already running for this task. Queue or cancel?');
        }

        $lock = Cache::lock('command-'.$task->id, 30);
        if (! $lock->get()) {
            throw new RuntimeException('A command is already being dispatched for this task.');
        }

        try {
            return $this->createLogAndDispatch($command, $args, $task);
        } finally {
            $lock->release();
        }
    }

    public function dispatchTaskless(string $command, array $args = []): string
    {
        if (! $this->registry->has($command)) {
            throw new RuntimeException("Command [{$command}] is not registered.");
        }

        $this->registry->tokens($command, $args);

        return $this->createLogAndDispatch($command, $args);
    }

    private function hasRunningCommand(Task $task): bool
    {
        return $task->commandLogs()
            ->where('status', 'running')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createLogAndDispatch(string $command, array $args, ?Task $task = null): string
    {
        $log = CommandLog::create([
            'task_id' => $task?->id,
            'command' => $command,
            'args' => $args,
            'status' => 'queued',
            'output' => '',
        ]);

        RunCliCommand::dispatch($log->id);

        return (string) $log->id;
    }
}
