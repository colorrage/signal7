<?php

namespace App\Contracts;

use App\Models\Task;

interface CliCommandDispatcher
{
    /**
     * Dispatch a CLI command for the given task.
     *
     * @param  Task  $task  The task context for the command.
     * @param  string  $command  The command string to dispatch.
     * @param  array  $args  Additional arguments for the command.
     * @return string Dispatch id (used for toast correlation).
     */
    public function dispatch(Task $task, string $command, array $args = []): string;

    /**
     * Dispatch a CLI command without a task context.
     *
     * Used for registry-approved commands such as list, backlog, and recipe
     * actions where there is no cached Task row to attach.
     *
     * @param  string  $command  The command string or registry key to dispatch.
     * @param  array  $args  Additional arguments for the command.
     * @return string Dispatch id (used for toast correlation).
     */
    public function dispatchTaskless(string $command, array $args = []): string;
}
