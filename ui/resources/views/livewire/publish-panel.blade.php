<div class="s7-publish-panel">
    <style>
        .s7-publish-panel {
            display: grid;
            gap: 1.25rem;
        }

        .s7-publish-card {
            background: rgb(24, 24, 27);
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .22);
            padding: 1rem;
        }

        .s7-publish-header {
            align-items: flex-start;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }

        .s7-publish-title {
            color: rgb(244, 244, 245);
            font-size: .875rem;
            font-weight: 700;
            letter-spacing: .04em;
            margin: 0;
            text-transform: uppercase;
        }

        .s7-publish-muted {
            color: rgb(161, 161, 170);
            font-size: .875rem;
            margin: .25rem 0 0;
        }

        .s7-publish-badge {
            border-radius: 6px;
            display: inline-flex;
            font-size: .8125rem;
            font-weight: 700;
            line-height: 1;
            padding: .45rem .65rem;
            white-space: nowrap;
        }

        .s7-publish-badge.ready,
        .s7-publish-check.ok {
            background: rgba(22, 163, 74, .18);
            color: rgb(134, 239, 172);
        }

        .s7-publish-badge.blocked,
        .s7-publish-check.fail {
            background: rgba(220, 38, 38, .18);
            color: rgb(252, 165, 165);
        }

        .s7-publish-check.na {
            background: rgba(148, 163, 184, .16);
            color: rgb(203, 213, 225);
        }

        .s7-publish-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: 1rem;
        }

        .s7-publish-button {
            border: 0;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: .875rem;
            font-weight: 700;
            padding: .55rem .85rem;
        }

        .s7-publish-button.green {
            background: rgb(22, 163, 74);
        }

        .s7-publish-button.blue {
            background: rgb(37, 99, 235);
        }

        .s7-publish-button:disabled {
            cursor: not-allowed;
            opacity: .55;
        }

        .s7-publish-table-wrap {
            margin-top: 1rem;
            overflow-x: auto;
        }

        .s7-publish-table {
            border-collapse: collapse;
            min-width: 760px;
            width: 100%;
        }

        .s7-publish-table th {
            color: rgb(161, 161, 170);
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
            padding: .65rem .75rem;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .s7-publish-table td {
            border-top: 1px solid rgba(148, 163, 184, .16);
            color: rgb(228, 228, 231);
            padding: .8rem .75rem;
            vertical-align: top;
        }

        .s7-publish-asset-title {
            color: rgb(244, 244, 245);
            font-weight: 700;
        }

        .s7-publish-asset-meta {
            color: rgb(161, 161, 170);
            font-size: .8125rem;
            margin-top: .2rem;
        }

        .s7-publish-checks {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            max-width: 540px;
        }

        .s7-publish-check {
            border-radius: 5px;
            font-size: .75rem;
            font-weight: 700;
            padding: .25rem .45rem;
        }

        .s7-publish-code {
            background: rgba(63, 63, 70, .65);
            border-radius: 5px;
            color: rgb(244, 244, 245);
            display: block;
            font-size: .75rem;
            max-width: 280px;
            overflow: hidden;
            padding: .35rem .45rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .s7-publish-empty {
            color: rgb(161, 161, 170);
            padding: 1.5rem;
            text-align: center;
        }

        @media (max-width: 760px) {
            .s7-publish-header {
                display: grid;
            }

            .s7-publish-table {
                min-width: 680px;
            }
        }
    </style>

    <div class="s7-publish-card">
        <div class="s7-publish-header">
            <div>
                <h3 class="s7-publish-title">Publish Readiness</h3>
                <p class="s7-publish-muted">
                    Gate checks and idempotency previews for {{ $task->task_id }}.
                </p>
            </div>

            @if ($this->blockedAssetCount === 0)
                <span class="s7-publish-badge ready">Ready to publish</span>
            @else
                <span class="s7-publish-badge blocked">
                    {{ $this->blockedAssetCount }} {{ \Illuminate\Support\Str::plural('asset', $this->blockedAssetCount) }} blocked
                </span>
            @endif
        </div>

        <div class="s7-publish-actions">
            <button
                type="button"
                wire:click="publishAll"
                wire:loading.attr="disabled"
                wire:target="publishAll"
                @disabled($this->publishBlocked)
                class="s7-publish-button green"
            >
                {{ isset($inflight['publish-all']) ? 'Dispatching...' : 'Publish All' }}
            </button>
            <button
                type="button"
                wire:click="publishSelected"
                wire:loading.attr="disabled"
                wire:target="publishSelected"
                @disabled($this->publishBlocked || count($selectedAssets) === 0)
                class="s7-publish-button blue"
            >
                {{ isset($inflight['publish-selected']) ? 'Dispatching...' : 'Publish Selected' }}
            </button>
        </div>

        <div class="s7-publish-table-wrap">
            <table class="s7-publish-table">
                <thead>
                    <tr>
                        <th style="width: 2.5rem;"></th>
                        <th>Asset</th>
                        <th>Checks</th>
                        <th>Idempotency Key</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->assets as $asset)
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedAssets"
                                    value="{{ $asset->asset_id }}"
                                />
                            </td>
                            <td>
                                <div class="s7-publish-asset-title">{{ $asset->asset_id }}</div>
                                <div class="s7-publish-asset-meta">{{ $asset->title }}</div>
                                <div class="s7-publish-asset-meta">{{ $asset->channel ?? 'unknown channel' }}</div>
                            </td>
                            <td>
                                <div class="s7-publish-checks">
                                    @foreach ($this->readinessFor($asset) as $check)
                                        @php
                                            $classes = match ($check['ok']) {
                                                true => 'ok',
                                                false => 'fail',
                                                default => 'na',
                                            };
                                            $mark = match ($check['ok']) {
                                                true => '✓',
                                                false => '✗',
                                                default => 'N/A',
                                            };
                                        @endphp
                                        <span class="s7-publish-check {{ $classes }}" title="{{ $check['detail'] }}">
                                            {{ $mark }} {{ $check['label'] }}: {{ $check['state'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <code
                                    class="s7-publish-code"
                                    title="Computed as system:task_id:asset_id:asset_type"
                                >{{ $this->idempotencyKeyFor($asset) }}</code>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="s7-publish-empty">
                                No assets found for this task.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="s7-publish-card">
        <h3 class="s7-publish-title">Publish Log</h3>

        <div class="s7-publish-table-wrap">
            <table class="s7-publish-table">
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Channel</th>
                        <th>Idempotency Key</th>
                        <th>Status</th>
                        <th>Published</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($publishLogs as $entry)
                        @php
                            $statusColor = match ($entry->status) {
                                'published' => 'ok',
                                'skipped' => 'na',
                                'blocked' => 'na',
                                'failed' => 'fail',
                                default => 'na',
                            };
                        @endphp
                        <tr>
                            <td>{{ $entry->asset_id }}</td>
                            <td>{{ $entry->channel ?? 'unknown' }}</td>
                            <td>
                                <code class="s7-publish-code">{{ $entry->idempotency_key }}</code>
                            </td>
                            <td>
                                <span class="s7-publish-check {{ $statusColor }}">{{ $entry->status }}</span>
                            </td>
                            <td>{{ $entry->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="s7-publish-empty">
                                No publish log entries yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
