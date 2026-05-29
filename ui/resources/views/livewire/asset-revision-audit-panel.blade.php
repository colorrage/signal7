<div class="s7-audit-panel space-y-5">
    <style>
        .s7-audit-panel table {
            width: 100%;
        }

        .s7-audit-panel th,
        .s7-audit-panel td {
            vertical-align: top;
        }

        .s7-audit-pre {
            max-height: 24rem;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>

    @if (! $available)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
            {{ $message ?? 'Revision audit is unavailable.' }}
        </div>
    @endif

    @if (! empty($warnings))
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
            <div class="font-semibold">Audit warnings</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Revision History</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ count($revisions) }} revisions discovered from disk</p>
            </div>
        </div>

        @if (empty($revisions))
            <p class="text-sm italic text-gray-500 dark:text-gray-400">{{ $message ?? 'No revisions found.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Revision</th>
                            <th class="px-3 py-2">Worker</th>
                            <th class="px-3 py-2">Model</th>
                            <th class="px-3 py-2">Prompt Hash</th>
                            <th class="px-3 py-2">Content Hash</th>
                            <th class="px-3 py-2">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($revisions as $revision)
                            <tr>
                                <td class="px-3 py-2 font-mono">r{{ $revision['revision'] }}</td>
                                <td class="px-3 py-2">{{ $revision['worker'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $revision['model'] ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $revision['prompt_hash'] ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $revision['content_hash'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $revision['timestamp'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Compare Revisions</h3>

        @if (count($revisions) > 1)
            <div class="mb-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-200">
                    <span class="mb-1 block text-xs font-medium uppercase text-gray-500">From</span>
                    <select wire:model.live="fromRevision" class="w-full rounded-md border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-950">
                        @foreach ($revisions as $revision)
                            <option value="{{ $revision['revision'] }}">r{{ $revision['revision'] }} · {{ $revision['worker'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-200">
                    <span class="mb-1 block text-xs font-medium uppercase text-gray-500">To</span>
                    <select wire:model.live="toRevision" class="w-full rounded-md border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-950">
                        @foreach ($revisions as $revision)
                            <option value="{{ $revision['revision'] }}">r{{ $revision['revision'] }} · {{ $revision['worker'] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        @endif

        @if (! $diff['available'])
            <p class="text-sm italic text-gray-500 dark:text-gray-400">{{ $diff['message'] ?? 'Diff unavailable.' }}</p>
        @else
            <div class="max-h-96 overflow-auto rounded-md border border-gray-200 dark:border-gray-700">
                <table class="text-xs">
                    <tbody>
                        @foreach ($diff['rows'] as $row)
                            @php
                                $color = match ($row['type']) {
                                    'added' => 'bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-100',
                                    'deleted' => 'bg-red-50 text-red-900 dark:bg-red-950 dark:text-red-100',
                                    default => 'bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-200',
                                };
                            @endphp
                            <tr class="{{ $color }}">
                                <td class="w-1/2 border-r border-gray-200 px-3 py-1 font-mono dark:border-gray-700">{{ $row['left'] }}</td>
                                <td class="w-1/2 px-3 py-1 font-mono">{{ $row['right'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Prompt Audit</h3>
        @if ($prompt)
            <div class="mb-2 flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-mono">r{{ $prompt['revision'] }}</span>
                <span>{{ $prompt['worker'] }}</span>
                <span class="font-mono">{{ $prompt['prompt_hash'] ?? 'no prompt hash' }}</span>
            </div>
            <pre class="s7-audit-pre rounded-md bg-gray-950 p-3 text-xs leading-5 text-gray-100">{{ $prompt['prompt'] }}</pre>
        @else
            <p class="text-sm italic text-gray-500 dark:text-gray-400">No prompt content available.</p>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Generation Log</h3>
        @if (empty($generationLog['rows']))
            <p class="text-sm italic text-gray-500 dark:text-gray-400">{{ $generationLog['message'] ?? 'No generation log entries available.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Model</th>
                            <th class="px-3 py-2">Worker</th>
                            <th class="px-3 py-2">Version</th>
                            <th class="px-3 py-2">Prompt Hash</th>
                            <th class="px-3 py-2">Content Hash</th>
                            <th class="px-3 py-2">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($generationLog['rows'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['model'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['worker'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['worker_version'] ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['prompt_hash'] ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['content_hash'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['timestamp'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
