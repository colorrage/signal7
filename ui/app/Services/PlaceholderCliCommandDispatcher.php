<?php

namespace App\Services;

use App\Contracts\CliCommandDispatcher;
use App\Models\Task;

/**
 * Placeholder implementation of CliCommandDispatcher.
 *
 * Records each call for test inspection and throws a RuntimeException so
 * callers see a user-visible toast error rather than silent success.
 * When RunCliCommand (Horizon job) lands, the container binding in
 * AppServiceProvider flips to the real dispatcher and no code here changes.
 */
class PlaceholderCliCommandDispatcher implements CliCommandDispatcher
{
    /**
     * Recorded dispatch calls for test inspection.
     *
     * @var array<int, array{task_id: int|null, command: string, args: array}>
     */
    public array $calls = [];

    /**
     * {@inheritdoc}
     *
     * Always throws RuntimeException — the real Horizon job is not yet landed.
     */
    public function dispatch(Task $task, string $command, array $args = []): string
    {
        $this->calls[] = [
            'task_id' => $task->id ?? null,
            'command' => $command,
            'args' => $args,
        ];

        throw new \RuntimeException('CLI dispatch not yet available: '.$command);
    }

    public function dispatchTaskless(string $command, array $args = []): string
    {
        $this->calls[] = [
            'task_id' => null,
            'command' => $command,
            'args' => $args,
        ];

        throw new \RuntimeException('CLI dispatch not yet available: '.$command);
    }
}
