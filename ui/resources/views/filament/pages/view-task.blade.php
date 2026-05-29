<x-filament-panels::page>
    @php
        $taskDocuments = app(\App\Services\TaskDocumentService::class)->documentsFor($record);
        $showPublishTabs = $record->system === 'signal7' && in_array($record->phase, ['publish', 'done'], true);
        $showAuditTab = $record->system === 'signal7' && $record->assets()->exists();
        $showDocumentsTab = true;
        $showSubtasksTab = $record->system === 'hyper7';
        $showTaskTabs = $showPublishTabs || $showAuditTab || $showDocumentsTab || $showSubtasksTab;
        $activeTaskTab = $this->activeTaskTab ?? 'overview';

        if (
            ($activeTaskTab === 'publish' && ! $showPublishTabs)
            || ($activeTaskTab === 'audit' && ! $showAuditTab)
            || ($activeTaskTab === 'subtasks' && ! $showSubtasksTab)
        ) {
            $activeTaskTab = 'overview';
        }
    @endphp

    @if ($showTaskTabs)
        <style>
            .s7-task-tabs {
                align-items: center;
                border-bottom: 1px solid rgba(148, 163, 184, .35);
                display: flex;
                gap: 1.25rem;
                margin-bottom: 1.5rem;
            }

            .s7-task-tab {
                border: 0;
                border-bottom: 2px solid transparent;
                background: transparent;
                color: rgb(148, 163, 184);
                cursor: pointer;
                font: inherit;
                font-size: .875rem;
                font-weight: 600;
                padding: .75rem .125rem;
            }

            .s7-task-tab.is-active {
                border-bottom-color: rgb(245, 158, 11);
                color: rgb(245, 158, 11);
            }
        </style>

        <div class="s7-task-tabs">
            <nav class="s7-task-tabs" style="border-bottom: 0; margin-bottom: 0;" aria-label="Task sections">
                <button
                    type="button"
                    wire:click="$set('activeTaskTab', 'overview')"
                    @class(['s7-task-tab', 'is-active' => $activeTaskTab === 'overview'])
                >
                    Overview
                </button>
                @if ($showPublishTabs)
                    <button
                        type="button"
                        wire:click="$set('activeTaskTab', 'publish')"
                        @class(['s7-task-tab', 'is-active' => $activeTaskTab === 'publish'])
                    >
                        Publish
                    </button>
                @endif
                @if ($showDocumentsTab)
                    <button
                        type="button"
                        wire:click="$set('activeTaskTab', 'documents')"
                        @class(['s7-task-tab', 'is-active' => $activeTaskTab === 'documents'])
                    >
                        Documents
                    </button>
                @endif
                @if ($showSubtasksTab)
                    <button
                        type="button"
                        wire:click="$set('activeTaskTab', 'subtasks')"
                        @class(['s7-task-tab', 'is-active' => $activeTaskTab === 'subtasks'])
                    >
                        Subtasks
                    </button>
                @endif
                @if ($showAuditTab)
                    <button
                        type="button"
                        wire:click="$set('activeTaskTab', 'audit')"
                        @class(['s7-task-tab', 'is-active' => $activeTaskTab === 'audit'])
                    >
                        Revision History
                    </button>
                @endif
            </nav>
        </div>
    @endif

    @if ($activeTaskTab === 'publish')
        <livewire:publish-panel :task="$record" :key="'publish-panel-'.$record->id" />
    @elseif ($activeTaskTab === 'documents')
        @include('filament.partials.task-documents-panel', ['documents' => $taskDocuments])
    @elseif ($activeTaskTab === 'subtasks')
        <livewire:subtask-tree :task-id="$record->id" :key="'subtask-tree-'.$record->id" />
    @elseif ($activeTaskTab === 'audit')
        <div class="space-y-6">
            @foreach ($record->assets()->orderBy('asset_id')->get() as $asset)
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <span class="rounded bg-primary-50 px-2 py-1 font-mono text-xs font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-200">
                            {{ $asset->asset_id }}
                        </span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $asset->title }}</span>
                        <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $asset->status }}</span>
                    </div>

                    <livewire:asset-revision-audit-panel :asset-id="$asset->id" :key="'task-audit-'.$asset->id" />
                </section>
            @endforeach
        </div>
    @else
        {{ $this->infolist }}

        <div class="mt-6">
            @include('filament.partials.pipeline-canvas', ['task' => $record])
        </div>
    @endif
</x-filament-panels::page>
