@php
    /** @var \App\Support\GateState\ComplianceState $compliance */
@endphp

@if (isset($compliance) && $compliance->status !== 'clear')
    @php
        $isBlocked        = $compliance->status === 'blocked';
        $isQuestionsOpen  = $compliance->status === 'questions-open';

        $bgClass     = $isBlocked ? 'bg-red-50 border-red-300' : 'bg-yellow-50 border-yellow-300';
        $textClass   = $isBlocked ? 'text-red-800' : 'text-yellow-800';
        $labelClass  = $isBlocked ? 'text-red-700 font-semibold' : 'text-yellow-700 font-semibold';
        $btnClass    = $isBlocked
            ? 'bg-red-600 hover:bg-red-700 text-white'
            : 'bg-yellow-600 hover:bg-yellow-700 text-white';
    @endphp

    <div class="rounded-lg border {{ $bgClass }} p-4 mb-4 space-y-3" role="alert">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <p class="{{ $labelClass }} text-sm">
                    @if ($isBlocked)
                        Compliance Blocked — publish is disabled until this is resolved.
                    @else
                        Compliance Questions Open — review required before publishing.
                    @endif
                </p>

                @if ($isQuestionsOpen)
                    @if (!empty($compliance->openQuestions))
                        <ul class="{{ $textClass }} text-sm mt-2 list-disc list-inside space-y-1">
                            @foreach ($compliance->openQuestions as $question)
                                <li>{{ $question }}</li>
                            @endforeach
                        </ul>
                    @else
                        {{-- Edge case: status is questions-open but no questions were parsed --}}
                        <p class="{{ $textClass }} text-sm mt-2 italic">
                            compliance.md flagged questions but none were parsed —
                            <a href="#" class="underline">open file</a>
                        </p>
                    @endif
                @endif
            </div>

            {{-- Resolve Compliance button — calls resolveCompliance() on the parent GatePanel component --}}
            <button
                wire:click="resolveCompliance()"
                class="shrink-0 rounded px-3 py-1.5 text-sm font-medium {{ $btnClass }} focus:outline-none focus:ring-2 focus:ring-offset-1"
            >
                Resolve Compliance
            </button>
        </div>
    </div>
@endif
