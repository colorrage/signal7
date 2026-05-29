<div class="space-y-6">
    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,28rem)]">
        <div class="space-y-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="command-key">Command</label>
                <select
                    id="command-key"
                    wire:model.live="selectedCommand"
                    class="mt-2 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                >
                    <option value="">Select a command</option>
                    @foreach ($this->groupedCommands as $system => $commands)
                        <optgroup label="{{ strtoupper($system) }}">
                            @foreach ($commands as $key => $definition)
                                <option value="{{ $key }}">{{ $definition['label'] ?? $key }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            @if ($selectedCommand)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                            Task context
                            <input
                                type="text"
                                wire:model.live="taskContext"
                                placeholder="S1, T12, or database id"
                                class="mt-2 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                            />
                        </label>

                        @foreach ($this->argumentSchema as $argument)
                            @continue(($argument['type'] ?? null) === 'hidden')
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $argument['label'] ?? $argument['name'] }}
                                @if (($argument['type'] ?? 'text') === 'textarea')
                                    <textarea
                                        wire:model.live="arguments.{{ $argument['name'] }}"
                                        rows="3"
                                        class="mt-2 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                    ></textarea>
                                @else
                                    <input
                                        type="text"
                                        wire:model.live="arguments.{{ $argument['name'] }}"
                                        class="mt-2 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                    />
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Preview</div>
                    <pre class="mt-2 overflow-x-auto rounded-md bg-gray-950 p-3 text-xs text-gray-100">{{ $this->preview }}</pre>
                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            wire:click="confirm"
                            class="rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500"
                        >
                            Confirm
                        </button>
                        @if ($confirming)
                            <button
                                type="button"
                                wire:click="run"
                                class="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500"
                            >
                                Run
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900" wire:poll.2s="refreshLog">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Terminal</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($commandLogId)
                            Log #{{ $commandLogId }} · {{ $status ?? 'queued' }}
                        @else
                            No command running
                        @endif
                    </div>
                </div>
            </div>
            <pre class="mt-3 min-h-80 overflow-auto rounded-md bg-gray-950 p-3 text-xs leading-5 text-gray-100">{{ $output }}</pre>
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Recent Commands</div>
        <div class="mt-3 divide-y divide-gray-200 dark:divide-gray-800">
            @forelse ($recentLogs as $log)
                <details class="py-3">
                    <summary class="cursor-pointer text-sm text-gray-700 dark:text-gray-200">
                        #{{ $log->id }} · {{ $log->command }} · {{ $log->status }}
                        @if ($log->task)
                            · {{ $log->task->task_id }}
                        @endif
                        @if ($log->duration_seconds !== null)
                            · {{ $log->duration_seconds }}s
                        @endif
                    </summary>
                    <pre class="mt-2 max-h-72 overflow-auto rounded-md bg-gray-950 p-3 text-xs text-gray-100">{{ $log->output }}</pre>
                </details>
            @empty
                <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No commands recorded yet.</p>
            @endforelse
        </div>
    </section>
</div>
