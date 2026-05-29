@props(['task'])
@php
    $pipelineState = [
        'task_id'  => $task->task_id,
        'system'   => $task->system,
        'scope'    => $task->scope,
        'phase'    => $task->phase,
        'awaiting' => $task->awaiting,
        'assets'   => $task->assets->map(fn ($a) => [
            'asset_id'   => $a->asset_id,
            'asset_type' => $a->asset_type,
            'status'     => $a->status,
        ])->values(),
    ];
@endphp
<div x-data x-on:pipeline-node-clicked.window="$dispatch('pipeline-node-clicked', $event.detail)">
    <div
        id="pipeline-root"
        data-state="@js($pipelineState)"
        class="h-[480px] w-full rounded-lg border border-gray-200 dark:border-gray-700"
    ></div>
    @vite('resources/js/pipeline/app.tsx')
</div>
