@php
    use League\CommonMark\CommonMarkConverter;
    $converter = new CommonMarkConverter();

    $dependsStr = is_array($record->depends) && count($record->depends)
        ? implode(', ', $record->depends)
        : '—';

    $bodyHtml = $record->body
        ? $converter->convert($record->body)->getContent()
        : null;

    $promptHtml = $record->prompt
        ? $converter->convert($record->prompt)->getContent()
        : null;
@endphp

<div class="space-y-4 p-1">

    {{-- Header: status badge + revision counter --}}
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
            {{ $record->status ?? '—' }}
        </span>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            Cached revision: {{ $record->revision ?? 0 }}
        </span>
    </div>

    {{-- Metadata table --}}
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400 w-36">Asset ID</th>
                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100 font-mono">{{ $record->asset_id ?? '—' }}</td>
                </tr>
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Title</th>
                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $record->title ?? '—' }}</td>
                </tr>
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $record->asset_type ?? '—' }}</td>
                </tr>
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Channel</th>
                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $record->channel ?? '—' }}</td>
                </tr>
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Language</th>
                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $record->language ?? '—' }}</td>
                </tr>
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Depends</th>
                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $dependsStr }}</td>
                </tr>
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Publish At</th>
                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                        {{ $record->publish_at ? $record->publish_at->format('Y-m-d H:i') : '—' }}
                    </td>
                </tr>
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">External Gate</th>
                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $record->external_gate ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Rendered markdown body --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Body</h3>
        @if ($bodyHtml)
            <div class="prose dark:prose-invert max-w-none rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800">
                {!! $bodyHtml !!}
            </div>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500 italic">No body content available.</p>
        @endif
    </div>

    {{-- View Prompt disclosure --}}
    <div x-data="{ open: false }">
        <button
            type="button"
            x-on:click="open = ! open"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 focus:outline-none"
        >
            <svg
                x-bind:class="open ? 'rotate-90' : ''"
                class="h-4 w-4 transition-transform duration-150"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            View Prompt
        </button>

        <div x-show="open" x-collapse class="mt-2">
            @if ($promptHtml)
                <div class="prose dark:prose-invert max-w-none rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800">
                    {!! $promptHtml !!}
                </div>
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500 italic">No prompt available.</p>
            @endif
        </div>
    </div>

</div>
