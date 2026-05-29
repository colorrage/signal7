@if ($documents === [])
    <div class="space-y-4">
        <section class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            No task documents found on disk for this task.
        </section>
    </div>
@else
    <style>
        .s7-md-reader {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(14rem, 18rem) minmax(0, 1fr);
            min-height: 38rem;
        }

        .s7-md-sidebar,
        .s7-md-document {
            background: rgb(255, 255, 255);
            border: 1px solid rgb(229, 231, 235);
            border-radius: .5rem;
        }

        .dark .s7-md-sidebar,
        .dark .s7-md-document {
            background: rgb(17, 24, 39);
            border-color: rgb(31, 41, 55);
        }

        .s7-md-sidebar {
            align-self: start;
            max-height: 70vh;
            overflow: auto;
            padding: .5rem;
        }

        .s7-md-sidebar-title {
            color: rgb(107, 114, 128);
            font-size: .75rem;
            font-weight: 700;
            padding: .5rem;
            text-transform: uppercase;
        }

        .s7-md-file-button {
            border: 1px solid transparent;
            border-radius: .375rem;
            color: rgb(55, 65, 81);
            display: block;
            padding: .625rem .75rem;
            text-align: left;
            width: 100%;
        }

        .dark .s7-md-file-button {
            color: rgb(229, 231, 235);
        }

        .s7-md-file-button:hover {
            background: rgb(249, 250, 251);
        }

        .dark .s7-md-file-button:hover {
            background: rgb(31, 41, 55);
        }

        .s7-md-file-button.is-active {
            background: rgb(255, 251, 235);
            border-color: rgb(245, 158, 11);
            color: rgb(180, 83, 9);
        }

        .dark .s7-md-file-button.is-active {
            background: rgb(69, 26, 3);
            border-color: rgb(251, 191, 36);
            color: rgb(253, 230, 138);
        }

        .s7-md-file-label {
            display: block;
            font-size: .875rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .s7-md-file-name {
            display: block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: .75rem;
            line-height: 1rem;
            opacity: .75;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .s7-md-document {
            min-width: 0;
        }

        .s7-md-document-header {
            align-items: flex-start;
            border-bottom: 1px solid rgb(229, 231, 235);
            display: flex;
            gap: .75rem;
            justify-content: space-between;
            padding: 1rem 1.5rem;
        }

        .dark .s7-md-document-header {
            border-color: rgb(31, 41, 55);
        }

        .s7-md-document-title {
            color: rgb(17, 24, 39);
            font-size: 1.125rem;
            font-weight: 700;
            line-height: 1.75rem;
            margin: 0;
        }

        .dark .s7-md-document-title {
            color: rgb(243, 244, 246);
        }

        .s7-md-document-file {
            color: rgb(107, 114, 128);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: .75rem;
            line-height: 1rem;
            margin-top: .25rem;
        }

        .s7-md-badge {
            background: rgb(243, 244, 246);
            border-radius: .375rem;
            color: rgb(55, 65, 81);
            flex-shrink: 0;
            font-size: .75rem;
            font-weight: 600;
            padding: .25rem .5rem;
        }

        .dark .s7-md-badge {
            background: rgb(31, 41, 55);
            color: rgb(229, 231, 235);
        }

        .s7-md-body {
            color: rgb(31, 41, 55);
            font-size: .9375rem;
            line-height: 1.75;
            max-height: 70vh;
            overflow: auto;
            padding: 1.5rem;
        }

        .dark .s7-md-body {
            color: rgb(229, 231, 235);
        }

        .s7-md-body h1,
        .s7-md-body h2,
        .s7-md-body h3 {
            color: rgb(17, 24, 39);
            font-weight: 800;
            line-height: 1.25;
            margin: 1.5rem 0 .75rem;
        }

        .dark .s7-md-body h1,
        .dark .s7-md-body h2,
        .dark .s7-md-body h3 {
            color: rgb(249, 250, 251);
        }

        .s7-md-body h1 {
            font-size: 1.875rem;
            margin-top: 0;
        }

        .s7-md-body h2 {
            border-bottom: 1px solid rgb(229, 231, 235);
            font-size: 1.375rem;
            padding-bottom: .35rem;
        }

        .dark .s7-md-body h2 {
            border-color: rgb(55, 65, 81);
        }

        .s7-md-body h3 {
            font-size: 1.125rem;
        }

        .s7-md-body p,
        .s7-md-body ul,
        .s7-md-body ol,
        .s7-md-body table,
        .s7-md-body pre,
        .s7-md-body blockquote {
            margin: .85rem 0;
        }

        .s7-md-body ul,
        .s7-md-body ol {
            padding-left: 1.5rem;
        }

        .s7-md-body ul {
            list-style: disc;
        }

        .s7-md-body ol {
            list-style: decimal;
        }

        .s7-md-body table {
            border-collapse: collapse;
            display: block;
            overflow-x: auto;
            width: 100%;
        }

        .s7-md-body th,
        .s7-md-body td {
            border: 1px solid rgb(209, 213, 219);
            padding: .5rem .65rem;
            vertical-align: top;
        }

        .dark .s7-md-body th,
        .dark .s7-md-body td {
            border-color: rgb(75, 85, 99);
        }

        .s7-md-body th {
            background: rgb(249, 250, 251);
            font-weight: 700;
        }

        .dark .s7-md-body th {
            background: rgb(31, 41, 55);
        }

        .s7-md-body code {
            background: rgb(243, 244, 246);
            border-radius: .25rem;
            font-size: .875em;
            padding: .125rem .25rem;
        }

        .dark .s7-md-body code {
            background: rgb(31, 41, 55);
        }

        .s7-md-body pre {
            background: rgb(17, 24, 39);
            border-radius: .5rem;
            color: rgb(243, 244, 246);
            overflow-x: auto;
            padding: 1rem;
        }

        .s7-md-body pre code {
            background: transparent;
            color: inherit;
            padding: 0;
        }

        .s7-md-body blockquote {
            border-left: 4px solid rgb(245, 158, 11);
            color: rgb(75, 85, 99);
            padding-left: 1rem;
        }

        .dark .s7-md-body blockquote {
            color: rgb(209, 213, 219);
        }

        @media (max-width: 1024px) {
            .s7-md-reader {
                grid-template-columns: 1fr;
            }

            .s7-md-sidebar {
                max-height: none;
            }
        }
    </style>

    <div
        x-data="{ selected: @js($documents[0]['key']) }"
        class="s7-md-reader"
    >
        <aside class="s7-md-sidebar">
            <div class="s7-md-sidebar-title">
                Markdown Files
            </div>

            <nav class="space-y-1" aria-label="Task documents">
                @foreach ($documents as $document)
                    <button
                        type="button"
                        x-on:click="selected = @js($document['key'])"
                        x-bind:class="selected === @js($document['key']) ? 'is-active' : ''"
                        class="s7-md-file-button"
                    >
                        <span class="s7-md-file-label">{{ $document['label'] }}</span>
                        <span class="s7-md-file-name">{{ $document['filename'] }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        <section class="s7-md-document">
            @foreach ($documents as $document)
                <article x-cloak x-show="selected === @js($document['key'])">
                    <header class="s7-md-document-header">
                        <div>
                            <h2 class="s7-md-document-title">{{ $document['label'] }}</h2>
                            <div class="s7-md-document-file">{{ $document['filename'] }}</div>
                        </div>
                        <span class="s7-md-badge">{{ $document['group'] }}</span>
                    </header>

                    <div class="s7-md-body">
                        {!! $document['html'] !!}
                    </div>
                </article>
            @endforeach
        </section>
    </div>
@endif
