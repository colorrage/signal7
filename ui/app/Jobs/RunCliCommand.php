<?php

namespace App\Jobs;

use App\Events\CommandOutputChunk;
use App\Models\CommandLog;
use App\Services\CommandRegistry;
use App\Services\DiskSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\Process\Process;

class RunCliCommand implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public int $commandLogId)
    {
        $this->onQueue(config('commands.queue', 'cli-commands'));
    }

    public function handle(CommandRegistry $registry, DiskSyncService $diskSync): void
    {
        $log = CommandLog::with('task')->findOrFail($this->commandLogId);
        $task = $log->task;
        $args = $log->args ?? [];
        $this->timeout = $registry->timeout($log->command);

        $log->forceFill([
            'status' => 'running',
            'started_at' => now(),
        ])->save();

        event(new CommandOutputChunk($log->id, '', 'status', 'running'));

        try {
            $process = new Process(
                $registry->tokens($log->command, $args, $task),
                config('commands.working_directory')
            );
            $process->setTimeout($this->timeout);
            $process->run(function (string $type, string $buffer) use ($log): void {
                $log->appendOutput($buffer);

                event(new CommandOutputChunk(
                    $log->id,
                    $buffer,
                    $type === Process::ERR ? 'stderr' : 'stdout',
                ));
            });

            $log->refresh();
            $log->forceFill([
                'status' => $process->isSuccessful() ? 'done' : 'failed',
                'finished_at' => now(),
            ])->save();

            if ($task !== null) {
                $diskSync->syncTask($task->system, $task->task_id);
            }

            event(new CommandOutputChunk($log->id, '', 'status', $log->status));
        } catch (\Throwable $e) {
            $log->refresh();
            $log->appendOutput("\n".$e->getMessage());
            $log->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
            ])->save();

            event(new CommandOutputChunk($log->id, "\n".$e->getMessage(), 'stderr', 'failed'));

            throw $e;
        }
    }
}
