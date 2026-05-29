<?php

namespace App\Livewire;

use App\Contracts\CliCommandDispatcher;
use App\Models\Asset;
use App\Models\Task;
use App\Support\GateState\GateState;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PublishPanel extends Component
{
    public Task $task;

    /** @var array<int, string> */
    public array $selectedAssets = [];

    /** @var array<string, bool> */
    public array $inflight = [];

    public function mount(Task $task): void
    {
        $this->task = $task;
        $this->task->loadMissing(['assets', 'publishLogEntries']);
    }

    #[Computed]
    public function gateState(): GateState
    {
        $this->task->loadMissing('assets');

        return $this->task->gate_state;
    }

    /**
     * @return Collection<int, Asset>
     */
    #[Computed]
    public function assets(): Collection
    {
        return $this->task->assets()->orderBy('asset_id')->get();
    }

    #[Computed]
    public function blockedAssetCount(): int
    {
        return $this->assets->filter(fn (Asset $asset): bool => $this->assetIsBlocked($asset))->count();
    }

    #[Computed]
    public function publishBlocked(): bool
    {
        return $this->gateState->compliance->isPublishBlocked();
    }

    public function publishAll(): void
    {
        if ($this->publishBlocked) {
            $this->notifyBlocked();

            return;
        }

        $this->dispatchPublish([], 'publish-all', 'Publish all dispatched.');
    }

    public function publishSelected(): void
    {
        if ($this->publishBlocked) {
            $this->notifyBlocked();

            return;
        }

        $assetIds = $this->normalizedSelectedAssets();
        if ($assetIds === []) {
            Notification::make()
                ->warning()
                ->title('No assets selected')
                ->body('Select at least one asset before publishing selected assets.')
                ->send();

            return;
        }

        $this->dispatchPublish(['assets' => implode(',', $assetIds)], 'publish-selected', 'Publish selected dispatched.');
    }

    /**
     * @return array<int, array{key: string, label: string, state: string, ok: bool|null, detail: string}>
     */
    public function readinessFor(Asset $asset): array
    {
        $externalGate = $asset->external_gate;
        $externalStatus = is_array($externalGate) ? ($externalGate['status'] ?? null) : null;
        $externalOk = $externalStatus === null || in_array($externalStatus, ['satisfied', 'waived'], true);

        $expired = $asset->expires_at !== null && $asset->expires_at->isPast();

        return [
            [
                'key' => 'compliance',
                'label' => 'Compliance',
                'state' => $this->publishBlocked ? 'Blocked' : 'Clear',
                'ok' => ! $this->publishBlocked,
                'detail' => $this->publishBlocked ? 'Compliance blocks publishing.' : 'Compliance does not block publishing.',
            ],
            [
                'key' => 'external_gate',
                'label' => 'External gate',
                'state' => $externalStatus ?? 'None',
                'ok' => $externalOk,
                'detail' => $externalStatus === null ? 'No external gate required.' : 'External gate status: '.$externalStatus,
            ],
            [
                'key' => 'expiry',
                'label' => 'Expiry',
                'state' => $asset->expires_at?->format('Y-m-d H:i') ?? 'None',
                'ok' => ! $expired,
                'detail' => $expired ? 'Asset has expired.' : 'Asset is not expired.',
            ],
            [
                'key' => 'rate_limit',
                'label' => 'Rate limit',
                'state' => 'N/A',
                'ok' => null,
                'detail' => 'Rate limit checking is not yet available.',
            ],
        ];
    }

    public function assetIsBlocked(Asset $asset): bool
    {
        foreach ($this->readinessFor($asset) as $check) {
            if ($check['ok'] === false) {
                return true;
            }
        }

        return false;
    }

    public function idempotencyKeyFor(Asset $asset): string
    {
        return implode(':', array_filter([
            $this->task->system,
            $this->task->task_id,
            $asset->asset_id,
            $asset->asset_type,
        ], fn ($part): bool => $part !== null && $part !== ''));
    }

    #[On('cli-command-finished')]
    public function onCommandFinished(array $payload): void
    {
        $id = $payload['id'] ?? null;
        if ($id !== null) {
            unset($this->inflight[$id]);
        } else {
            $this->inflight = [];
        }

        $this->task->refresh();
        $this->task->loadMissing(['assets', 'publishLogEntries']);
    }

    public function render(): View
    {
        return view('livewire.publish-panel', [
            'publishLogs' => $this->task->publishLogEntries()->latest('published_at')->latest('id')->get(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function normalizedSelectedAssets(): array
    {
        $validAssetIds = $this->assets->pluck('asset_id')->map(fn ($id): string => (string) $id)->all();

        return collect($this->selectedAssets)
            ->map(fn ($assetId): string => (string) $assetId)
            ->intersect($validAssetIds)
            ->values()
            ->all();
    }

    private function dispatchPublish(array $args, string $inflightKey, string $successMessage): void
    {
        $this->inflight[$inflightKey] = true;

        try {
            /** @var CliCommandDispatcher $dispatcher */
            $dispatcher = app(CliCommandDispatcher::class);
            $dispatchId = $dispatcher->dispatch($this->task, 'signal.publish', $args);

            Notification::make()
                ->success()
                ->title('Publish dispatched')
                ->body($successMessage.($dispatchId ? " (id: {$dispatchId})" : ''))
                ->send();
        } catch (\Throwable $e) {
            unset($this->inflight[$inflightKey]);

            Notification::make()
                ->danger()
                ->title('Publish failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function notifyBlocked(): void
    {
        Notification::make()
            ->danger()
            ->title('Publish blocked')
            ->body('Compliance must be resolved before publishing.')
            ->send();
    }
}
