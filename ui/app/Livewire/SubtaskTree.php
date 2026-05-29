<?php

namespace App\Livewire;

use App\Models\Subtask;
use App\Services\MarkdownDocumentRenderer;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class SubtaskTree extends Component
{
    public int $taskId;

    public ?int $selectedId = null;

    public function mount(int $taskId): void
    {
        $this->taskId = $taskId;
    }

    public function open(int $id): void
    {
        $this->selectedId = $id;
    }

    /**
     * Load subtasks and compute depth for each via parent_subtask_id chain.
     *
     * @return Collection<int, Subtask>
     */
    protected function loadSubtasks(): Collection
    {
        $subtasks = Subtask::where('task_id', $this->taskId)
            ->orderBy('position')
            ->get();

        // Build a lookup from subtask_id string -> depth for efficient resolution
        $depthBySubtaskId = [];

        foreach ($subtasks as $subtask) {
            $depthBySubtaskId[$subtask->subtask_id] = $this->resolveDepth($subtask, $subtasks);
        }

        foreach ($subtasks as $subtask) {
            $subtask->depth = $depthBySubtaskId[$subtask->subtask_id] ?? 0;
        }

        return $subtasks;
    }

    /**
     * Walk the parent_subtask_id chain to determine depth.
     */
    protected function resolveDepth(Subtask $subtask, Collection $all, int $limit = 20): int
    {
        $depth = 0;
        $currentParentId = $subtask->parent_subtask_id;
        $visited = [];

        while ($currentParentId !== null && $depth < $limit) {
            if (isset($visited[$currentParentId])) {
                // Cycle guard
                break;
            }
            $visited[$currentParentId] = true;

            $parent = $all->firstWhere('subtask_id', $currentParentId);
            if ($parent === null) {
                break;
            }

            $depth++;
            $currentParentId = $parent->parent_subtask_id;
        }

        return $depth;
    }

    /**
     * Build a map of subtask_id -> status for dependency pill rendering.
     *
     * @param  Collection<int, Subtask>  $subtasks
     * @return array<string, string>
     */
    protected function buildDepStatus(Collection $subtasks): array
    {
        $map = [];
        foreach ($subtasks as $subtask) {
            $map[$subtask->subtask_id] = $subtask->status;
        }

        return $map;
    }

    public function render(): View
    {
        $subtasks = $this->loadSubtasks();
        $depStatus = $this->buildDepStatus($subtasks);
        if ($this->selectedId === null && $subtasks->isNotEmpty()) {
            $this->selectedId = $subtasks->first()->id;
        }

        $selected = $this->selectedId !== null
            ? $subtasks->firstWhere('id', $this->selectedId)
            : null;

        $selectedHtml = $selected?->body
            ? app(MarkdownDocumentRenderer::class)->toHtml($selected->body)
            : null;

        return view('livewire.subtask-tree', compact('subtasks', 'depStatus', 'selected', 'selectedHtml'));
    }
}
