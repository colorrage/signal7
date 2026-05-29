<?php

namespace App\Livewire;

use App\Contracts\CliCommandDispatcher;
use App\Models\CommandLog;
use App\Models\Task;
use App\Services\CommandRegistry;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CommandRunner extends Component
{
    public ?string $selectedCommand = null;

    /** @var array<string, mixed> */
    public array $arguments = [];

    public ?string $taskContext = null;

    public bool $confirming = false;

    public ?int $commandLogId = null;

    public ?string $status = null;

    public string $output = '';

    public function mount(): void
    {
        $this->selectedCommand = request()->query('command');
        $this->taskContext = request()->query('task');

        $args = request()->query('args', []);
        if (is_string($args)) {
            $decoded = json_decode($args, true);
            $args = is_array($decoded) ? $decoded : [];
        }
        $this->arguments = is_array($args) ? $args : [];

        foreach (request()->query() as $key => $value) {
            if (str_starts_with((string) $key, 'arg_')) {
                $this->arguments[substr((string) $key, 4)] = $value;
            }
        }

        if ($this->selectedCommand !== null) {
            $this->confirming = true;
        }
    }

    public function updatedSelectedCommand(): void
    {
        $this->arguments = [];
        $this->confirming = false;
        $this->commandLogId = null;
        $this->status = null;
        $this->output = '';
    }

    #[Computed]
    public function groupedCommands(): array
    {
        return app(CommandRegistry::class)->grouped();
    }

    #[Computed]
    public function argumentSchema(): array
    {
        if ($this->selectedCommand === null) {
            return [];
        }

        return app(CommandRegistry::class)->argumentSchema($this->selectedCommand);
    }

    #[Computed]
    public function preview(): string
    {
        if ($this->selectedCommand === null) {
            return '';
        }

        try {
            return app(CommandRegistry::class)->preview(
                $this->selectedCommand,
                $this->arguments,
                $this->resolveTask(),
            );
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function confirm(): void
    {
        $this->confirming = true;
    }

    public function run(): void
    {
        if ($this->selectedCommand === null) {
            Notification::make()->danger()->title('Select a command')->send();

            return;
        }

        try {
            $registry = app(CommandRegistry::class);
            $dispatcher = app(CliCommandDispatcher::class);
            $task = $this->resolveTask();

            if ($registry->requiresTask($this->selectedCommand) || $task !== null) {
                if ($task === null) {
                    throw new \RuntimeException('This command requires a task context.');
                }

                $dispatchId = $dispatcher->dispatch($task, $this->selectedCommand, $this->arguments);
            } else {
                $dispatchId = $dispatcher->dispatchTaskless($this->selectedCommand, $this->arguments);
            }

            $this->commandLogId = (int) $dispatchId;
            $this->refreshLog();

            Notification::make()
                ->success()
                ->title('Command queued')
                ->body('Command log #'.$dispatchId)
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Command failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function refreshLog(): void
    {
        if ($this->commandLogId === null) {
            return;
        }

        $log = CommandLog::find($this->commandLogId);
        if ($log === null) {
            return;
        }

        $this->status = $log->status;
        $this->output = $log->output ?? '';
    }

    public function resolveTask(): ?Task
    {
        if ($this->taskContext === null || trim($this->taskContext) === '') {
            return null;
        }

        if (ctype_digit($this->taskContext)) {
            return Task::find((int) $this->taskContext);
        }

        return Task::query()
            ->where('task_id', $this->taskContext)
            ->first();
    }

    public function recentLogs()
    {
        return CommandLog::query()
            ->with('task')
            ->latest()
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.command-runner', [
            'recentLogs' => $this->recentLogs(),
        ]);
    }
}
