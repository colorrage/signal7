<?php

namespace App\Services;

use App\Models\Task;
use InvalidArgumentException;

class CommandRegistry
{
    /**
     * @param  array<string, array<string, mixed>>|null  $commands
     */
    public function __construct(private ?array $commands = null)
    {
        $this->commands ??= config('commands.commands', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->commands;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->commands);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown command key [{$key}].");
        }

        return $this->commands[$key];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->commands as $key => $definition) {
            $system = (string) ($definition['system'] ?? 'other');
            $grouped[$system][$key] = $definition + ['key' => $key];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function argumentSchema(string $key): array
    {
        return $this->get($key)['arguments'] ?? [];
    }

    public function requiresTask(string $key): bool
    {
        return (bool) ($this->get($key)['requires_task'] ?? false);
    }

    public function timeout(string $key): int
    {
        return (int) ($this->get($key)['timeout'] ?? 300);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<int, string>
     */
    public function tokens(string $key, array $args = [], ?Task $task = null): array
    {
        $definition = $this->get($key);

        if (($definition['requires_task'] ?? false) && $task === null) {
            throw new InvalidArgumentException("Command [{$key}] requires a task context.");
        }

        $tokens = array_values(array_map('strval', $definition['tokens'] ?? []));

        foreach ($this->argumentSchema($key) as $argument) {
            $name = (string) ($argument['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $value = $args[$name] ?? $argument['default'] ?? null;
            if ($value === null && ($argument['default_from'] ?? null) === 'task.task_id') {
                $value = $task?->task_id;
            }

            if (($argument['required'] ?? false) && ($value === null || $value === '')) {
                throw new InvalidArgumentException("Command [{$key}] requires argument [{$name}].");
            }

            if ($value === null || $value === '') {
                continue;
            }

            $placement = $argument['placement'] ?? 'positional';
            if ($placement === 'option') {
                $option = (string) ($argument['option'] ?? '');
                if ($option === '') {
                    throw new InvalidArgumentException("Command [{$key}] argument [{$name}] is missing an option token.");
                }

                $tokens[] = $option;
            }

            $tokens[] = (string) $value;
        }

        return $tokens;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function preview(string $key, array $args = [], ?Task $task = null): string
    {
        return implode(' ', array_map($this->quoteToken(...), $this->tokens($key, $args, $task)));
    }

    private function quoteToken(string $token): string
    {
        if ($token === '') {
            return "''";
        }

        if (preg_match('/^[A-Za-z0-9_\\.\\/:=@+-]+$/', $token) === 1) {
            return $token;
        }

        return "'".str_replace("'", "'\\''", $token)."'";
    }
}
