<div>
    {{-- Compliance banner always rendered (hides itself when status is clear) --}}
    @include('livewire.gate-panel.compliance-banner', ['compliance' => $this->gateState->compliance])

    @if ($this->gateState->awaiting !== null)
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 space-y-4">

            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                Gate Panel
                <span class="ml-2 inline-flex items-center rounded bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">
                    awaiting: {{ $this->gateState->awaiting }}
                </span>
            </h3>

            {{-- ================================================================
                 USER-APPROVAL branch
                 ================================================================ --}}
            @if ($this->gateState->awaiting === 'user-approval')

                {{-- Approver rows --}}
                @if (!empty($this->gateState->approvers))
                    <div class="space-y-2">
                        @foreach ($this->gateState->approvers as $approver)
                            @php
                                $statusColor = match($approver->status) {
                                    'approved'  => 'bg-green-100 text-green-800',
                                    'rejected'  => 'bg-red-100 text-red-800',
                                    'escalated' => 'bg-orange-100 text-orange-800',
                                    default     => 'bg-gray-100 text-gray-700',
                                };
                                $resolved = in_array($approver->status, ['approved', 'rejected'], true);
                                $taskUpdatedAt = $task->updated_at
                                    ? \DateTimeImmutable::createFromMutable($task->updated_at->toDateTime())
                                    : null;
                                $expiresAt = $approver->expiresAt($taskUpdatedAt);
                            @endphp
                            <div class="flex flex-wrap items-center gap-3 rounded border border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                                {{-- Name --}}
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{ $approver->name }}
                                </span>

                                {{-- Status badge --}}
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold {{ $statusColor }}">
                                    {{ $approver->status }}
                                </span>

                                {{-- Timeout countdown --}}
                                @if ($expiresAt !== null)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        expires {{ $expiresAt->format('Y-m-d H:i') }}
                                    </span>
                                @endif

                                {{-- Per-row action buttons --}}
                                @if (!$resolved)
                                    <div class="ml-auto flex gap-2">
                                        <button
                                            wire:click="approve('{{ $approver->name }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="approve('{{ $approver->name }}')"
                                            class="rounded bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50"
                                        >
                                            {{ isset($inflight[$approver->name]) ? 'Dispatching…' : 'Approve' }}
                                        </button>
                                        <button
                                            wire:click="reject('{{ $approver->name }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="reject('{{ $approver->name }}')"
                                            class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                        >
                                            {{ isset($inflight[$approver->name]) ? 'Dispatching…' : 'Reject' }}
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Global Approve / Reject buttons --}}
                <div class="flex flex-wrap items-start gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                    <button
                        wire:click="approve()"
                        wire:loading.attr="disabled"
                        wire:target="approve()"
                        class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ isset($inflight['global']) ? 'Dispatching…' : 'Approve' }}
                    </button>

                    @if (!$showRejectInput)
                        <button
                            wire:click="$set('showRejectInput', true)"
                            class="rounded bg-red-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                        >
                            Reject
                        </button>
                    @else
                        <div class="flex flex-1 gap-2">
                            <input
                                type="text"
                                wire:model.live="rejectReason"
                                placeholder="Rejection reason…"
                                class="flex-1 rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-red-500 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                            />
                            <button
                                wire:click="reject()"
                                wire:loading.attr="disabled"
                                wire:target="reject()"
                                class="rounded bg-red-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                {{ isset($inflight['global']) ? 'Dispatching…' : 'Confirm Reject' }}
                            </button>
                            <button
                                wire:click="$set('showRejectInput', false)"
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Cancel
                            </button>
                        </div>
                    @endif
                </div>

            {{-- ================================================================
                 USER-INPUT branch
                 ================================================================ --}}
            @elseif ($this->gateState->awaiting === 'user-input')

                <div class="flex flex-wrap items-start gap-3">
                    <input
                        type="text"
                        wire:model.live="userInput"
                        placeholder="Enter your response…"
                        class="flex-1 rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    />
                    <button
                        wire:click="submit()"
                        wire:loading.attr="disabled"
                        wire:target="submit()"
                        class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ isset($inflight['submit']) ? 'Submitting…' : 'Submit' }}
                    </button>
                </div>

            @endif

        </div>
    @endif
</div>
