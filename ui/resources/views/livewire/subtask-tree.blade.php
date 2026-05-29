<div>
    @if ($subtasks->isEmpty())
        <p class="text-sm text-gray-500 italic">No subtasks yet.</p>
    @else
        <style>
            .s7-subtask-reader {
                display: grid;
                gap: 1rem;
                grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr);
                min-height: 38rem;
            }

            .s7-subtask-sidebar,
            .s7-subtask-document {
                background: rgb(255, 255, 255);
                border: 1px solid rgb(229, 231, 235);
                border-radius: .5rem;
            }

            .dark .s7-subtask-sidebar,
            .dark .s7-subtask-document {
                background: rgb(17, 24, 39);
                border-color: rgb(31, 41, 55);
            }

            .s7-subtask-sidebar {
                align-self: start;
                max-height: 70vh;
                overflow: auto;
                padding: .5rem;
            }

            .s7-subtask-sidebar-title {
                color: rgb(107, 114, 128);
                font-size: .75rem;
                font-weight: 700;
                padding: .5rem;
                text-transform: uppercase;
            }

            .s7-subtask-button {
                border: 1px solid transparent;
                border-radius: .375rem;
                color: rgb(55, 65, 81);
                display: block;
                padding: .625rem .75rem;
                text-align: left;
                width: 100%;
            }

            .dark .s7-subtask-button {
                color: rgb(229, 231, 235);
            }

            .s7-subtask-button:hover {
                background: rgb(249, 250, 251);
            }

            .dark .s7-subtask-button:hover {
                background: rgb(31, 41, 55);
            }

            .s7-subtask-button.is-active {
                background: rgb(255, 251, 235);
                border-color: rgb(245, 158, 11);
                color: rgb(180, 83, 9);
            }

            .dark .s7-subtask-button.is-active {
                background: rgb(69, 26, 3);
                border-color: rgb(251, 191, 36);
                color: rgb(253, 230, 138);
            }

            .s7-subtask-button-title {
                display: block;
                font-size: .875rem;
                font-weight: 700;
                line-height: 1.25rem;
            }

            .s7-subtask-button-meta {
                align-items: center;
                display: flex;
                flex-wrap: wrap;
                gap: .375rem;
                margin-top: .375rem;
            }

            .s7-subtask-chip {
                border-radius: .25rem;
                display: inline-flex;
                font-size: .75rem;
                font-weight: 700;
                line-height: 1rem;
                padding: .125rem .375rem;
            }

            .s7-subtask-chip-id {
                background: rgb(239, 246, 255);
                color: rgb(29, 78, 216);
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            }

            .s7-subtask-chip-done {
                background: rgb(220, 252, 231);
                color: rgb(22, 101, 52);
            }

            .s7-subtask-chip-progress {
                background: rgb(254, 249, 195);
                color: rgb(133, 77, 14);
            }

            .s7-subtask-chip-default {
                background: rgb(243, 244, 246);
                color: rgb(55, 65, 81);
            }

            .s7-subtask-document {
                min-width: 0;
            }

            .s7-subtask-header {
                border-bottom: 1px solid rgb(229, 231, 235);
                padding: 1rem 1.5rem;
            }

            .dark .s7-subtask-header {
                border-color: rgb(31, 41, 55);
            }

            .s7-subtask-title {
                color: rgb(17, 24, 39);
                font-size: 1.125rem;
                font-weight: 700;
                line-height: 1.75rem;
                margin: .5rem 0 0;
            }

            .dark .s7-subtask-title {
                color: rgb(243, 244, 246);
            }

            .s7-subtask-body {
                color: rgb(31, 41, 55);
                font-size: .9375rem;
                line-height: 1.75;
                max-height: 70vh;
                overflow: auto;
                padding: 1.5rem;
            }

            .dark .s7-subtask-body {
                color: rgb(229, 231, 235);
            }

            .s7-subtask-body h1,
            .s7-subtask-body h2,
            .s7-subtask-body h3 {
                color: rgb(17, 24, 39);
                font-weight: 800;
                line-height: 1.25;
                margin: 1.5rem 0 .75rem;
            }

            .dark .s7-subtask-body h1,
            .dark .s7-subtask-body h2,
            .dark .s7-subtask-body h3 {
                color: rgb(249, 250, 251);
            }

            .s7-subtask-body h1 {
                font-size: 1.875rem;
                margin-top: 0;
            }

            .s7-subtask-body h2 {
                border-bottom: 1px solid rgb(229, 231, 235);
                font-size: 1.375rem;
                padding-bottom: .35rem;
            }

            .dark .s7-subtask-body h2 {
                border-color: rgb(55, 65, 81);
            }

            .s7-subtask-body h3 {
                font-size: 1.125rem;
            }

            .s7-subtask-body p,
            .s7-subtask-body ul,
            .s7-subtask-body ol,
            .s7-subtask-body table,
            .s7-subtask-body pre,
            .s7-subtask-body blockquote {
                margin: .85rem 0;
            }

            .s7-subtask-body ul,
            .s7-subtask-body ol {
                padding-left: 1.5rem;
            }

            .s7-subtask-body ul {
                list-style: disc;
            }

            .s7-subtask-body ol {
                list-style: decimal;
            }

            .s7-subtask-body table {
                border-collapse: collapse;
                display: block;
                overflow-x: auto;
                width: 100%;
            }

            .s7-subtask-body th,
            .s7-subtask-body td {
                border: 1px solid rgb(209, 213, 219);
                padding: .5rem .65rem;
                vertical-align: top;
            }

            .dark .s7-subtask-body th,
            .dark .s7-subtask-body td {
                border-color: rgb(75, 85, 99);
            }

            .s7-subtask-body th {
                background: rgb(249, 250, 251);
                font-weight: 700;
            }

            .dark .s7-subtask-body th {
                background: rgb(31, 41, 55);
            }

            .s7-subtask-body code {
                background: rgb(243, 244, 246);
                border-radius: .25rem;
                font-size: .875em;
                padding: .125rem .25rem;
            }

            .dark .s7-subtask-body code {
                background: rgb(31, 41, 55);
            }

            .s7-subtask-body pre {
                background: rgb(17, 24, 39);
                border-radius: .5rem;
                color: rgb(243, 244, 246);
                overflow-x: auto;
                padding: 1rem;
            }

            .s7-subtask-body pre code {
                background: transparent;
                color: inherit;
                padding: 0;
            }

            .s7-subtask-section {
                border-top: 1px solid rgb(229, 231, 235);
                margin-top: 1.25rem;
                padding-top: 1rem;
            }

            .dark .s7-subtask-section {
                border-color: rgb(55, 65, 81);
            }

            .s7-subtask-section-title {
                color: rgb(107, 114, 128);
                font-size: .75rem;
                font-weight: 700;
                margin-bottom: .5rem;
                text-transform: uppercase;
            }

            @media (max-width: 1024px) {
                .s7-subtask-reader {
                    grid-template-columns: 1fr;
                }

                .s7-subtask-sidebar {
                    max-height: none;
                }
            }
        </style>

        <div class="s7-subtask-reader">
            <aside class="s7-subtask-sidebar">
                <div class="s7-subtask-sidebar-title">Subtasks</div>

                <nav aria-label="Subtasks">
                    @foreach ($subtasks as $subtask)
                        @php
                            $statusClass = match($subtask->status) {
                                'done' => 's7-subtask-chip-done',
                                'in-progress' => 's7-subtask-chip-progress',
                                default => 's7-subtask-chip-default',
                            };
                            $paddingLeft = $subtask->depth * .75;
                        @endphp

                        <button
                            type="button"
                            wire:click="open({{ $subtask->id }})"
                            @class(['s7-subtask-button', 'is-active' => $selected?->id === $subtask->id])
                            style="padding-left: {{ .75 + $paddingLeft }}rem"
                        >
                            <span class="s7-subtask-button-title">{{ $subtask->title }}</span>
                            <span class="s7-subtask-button-meta">
                                <span class="s7-subtask-chip s7-subtask-chip-id">{{ $subtask->subtask_id }}</span>
                                <span class="s7-subtask-chip {{ $statusClass }}">{{ $subtask->status }}</span>
                            </span>
                        </button>
                    @endforeach
                </nav>
            </aside>

            <section class="s7-subtask-document">
                @if ($selected)
                    @php
                        $selectedStatusClass = match($selected->status) {
                            'done' => 's7-subtask-chip-done',
                            'in-progress' => 's7-subtask-chip-progress',
                            default => 's7-subtask-chip-default',
                        };
                    @endphp

                    <header class="s7-subtask-header">
                        <div class="s7-subtask-button-meta">
                            <span class="s7-subtask-chip s7-subtask-chip-id">{{ $selected->subtask_id }}</span>
                            <span class="s7-subtask-chip {{ $selectedStatusClass }}">{{ $selected->status }}</span>
                            @if ($selected->role)
                                <span class="s7-subtask-chip s7-subtask-chip-default">{{ $selected->role }}</span>
                            @endif
                        </div>
                        <h2 class="s7-subtask-title">{{ $selected->title }}</h2>
                    </header>

                    <div class="s7-subtask-body">
                        @if ($selectedHtml)
                            {!! $selectedHtml !!}
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">No markdown body available for this subtask.</p>
                        @endif

                        @if (! empty($selected->writes))
                            <section class="s7-subtask-section">
                                <h3 class="s7-subtask-section-title">Writes</h3>
                                <ul>
                                    @foreach ($selected->writes as $path)
                                        <li><code>{{ $path }}</code></li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif

                        @if (! empty($selected->depends))
                            <section class="s7-subtask-section">
                                <h3 class="s7-subtask-section-title">Depends On</h3>
                                <div class="s7-subtask-button-meta">
                                    @foreach ($selected->depends as $dep)
                                        @php
                                            $depStat = $depStatus[$dep] ?? 'unknown';
                                            $depClass = match($depStat) {
                                                'done' => 's7-subtask-chip-done',
                                                'in-progress' => 's7-subtask-chip-progress',
                                                default => 's7-subtask-chip-default',
                                            };
                                        @endphp
                                        <span class="s7-subtask-chip {{ $depClass }}">{{ $dep }} · {{ $depStat }}</span>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    @endif
</div>
