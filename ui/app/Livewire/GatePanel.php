<?php

namespace App\Livewire;

use App\Contracts\CliCommandDispatcher;
use App\Models\Task;
use App\Support\GateState\GateState;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class GatePanel extends Component
{
    /** The task whose gate surface this panel manages. */
    public Task $task;

    /** Text input for the `user-input` awaiting branch. */
    public string $userInput = '';

    /** Rejection reason for the Reject action. */
    public string $rejectReason = '';

    /** Show/hide the inline reject reason input per approver or globally. */
    public bool $showRejectInput = false;

    /**
     * Per-action inflight flag keyed by action id (e.g. 'global', 'Owner').
     * Set on dispatch, cleared by `cli-command-finished` listener.
     *
     * @var array<string, bool>
     */
    public array $inflight = [];

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    /**
     * Computed gate state — delegates to the Task accessor.
     * Re-evaluated on each Livewire render cycle.
     */
    #[Computed]
    public function gateState(): GateState
    {
        // Load assets relation so the accessor can build asset summaries.
        $this->task->loadMissing('assets');

        return $this->task->gate_state;
    }

    /**
     * The canonical command string used to resolve compliance issues.
     * Centralised here so the template path can be changed in one place.
     */
    public function resolveComplianceCommand(): string
    {
        return $this->task->system === 'hyper7' ? 'hyper.run' : 'signal.run';
    }

    // -------------------------------------------------------------------------
    // Action methods
    // -------------------------------------------------------------------------

    /**
     * Approve the gate — optionally scoped to a named approver.
     */
    public function approve(?string $approverName = null): void
    {
        $key = $approverName ?? 'global';
        $this->inflight[$key] = true;

        $command = $this->task->system === 'hyper7' ? 'hyper.approve' : 'signal.approve';
        $args = $approverName
            ? ['response' => 'continue', 'approver' => $approverName]
            : ['response' => 'continue'];

        $this->dispatch_($command, $args, $key, 'Gate approved — command dispatched.');
    }

    /**
     * Reject the gate — optionally scoped to a named approver.
     * Validates that a rejection reason has been provided.
     */
    public function reject(?string $approverName = null): void
    {
        if (trim($this->rejectReason) === '') {
            Notification::make()
                ->danger()
                ->title('Rejection reason required')
                ->body('Please enter a reason before rejecting.')
                ->send();

            return;
        }

        $key = $approverName ?? 'global';
        $this->inflight[$key] = true;
        $command = $this->task->system === 'hyper7' ? 'hyper.run' : 'signal.run';
        $args = ['input' => 'reject: '.$this->rejectReason];
        if ($approverName) {
            $args['approver'] = $approverName;
        }

        $this->dispatch_($command, $args, $key, 'Rejection submitted — command dispatched.');
        $this->rejectReason = '';
        $this->showRejectInput = false;
    }

    /**
     * Submit a user-input response.
     */
    public function submit(): void
    {
        if (trim($this->userInput) === '') {
            Notification::make()
                ->danger()
                ->title('Input required')
                ->body('Please enter a response before submitting.')
                ->send();

            return;
        }

        $this->inflight['submit'] = true;
        $command = $this->task->system === 'hyper7' ? 'hyper.run' : 'signal.run';
        $this->dispatch_($command, ['input' => $this->userInput], 'submit', 'Response submitted — command dispatched.');
        $this->userInput = '';
    }

    /**
     * Dispatch a "resolve compliance" command.
     */
    public function resolveCompliance(): void
    {
        $this->inflight['compliance'] = true;
        $this->dispatch_(
            $this->resolveComplianceCommand(),
            ['input' => 'resolve compliance'],
            'compliance',
            'Compliance resolution dispatched.'
        );
    }

    /**
     * Listener for the `cli-command-finished` Livewire event.
     * Clears the inflight flag and fires a completion notification.
     *
     * @param  array{id?: string, success?: bool, message?: string}  $payload
     */
    #[On('cli-command-finished')]
    public function onCommandFinished(array $payload): void
    {
        $id = $payload['id'] ?? null;
        if ($id !== null) {
            unset($this->inflight[$id]);
        } else {
            $this->inflight = [];
        }

        $success = $payload['success'] ?? true;
        $message = $payload['message'] ?? 'Command finished.';

        if ($success) {
            Notification::make()->success()->title('Done')->body($message)->send();
        } else {
            Notification::make()->danger()->title('Command failed')->body($message)->send();
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the dispatcher from the container, call dispatch(), send a toast,
     * and handle failures gracefully.
     */
    private function dispatch_(string $command, array $args, string $inflightKey, string $successMessage): void
    {
        try {
            /** @var CliCommandDispatcher $dispatcher */
            $dispatcher = app(CliCommandDispatcher::class);
            $dispatchId = $dispatcher->dispatch($this->task, $command, $args);

            Notification::make()
                ->success()
                ->title('Dispatched')
                ->body($successMessage.($dispatchId ? " (id: {$dispatchId})" : ''))
                ->send();
        } catch (\Throwable $e) {
            unset($this->inflight[$inflightKey]);

            Notification::make()
                ->danger()
                ->title('Dispatch failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function render(): View
    {
        return view('livewire.gate-panel');
    }
}
