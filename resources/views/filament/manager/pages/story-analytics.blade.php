<x-filament-panels::page>
    @php
        $stories     = $this->getStoryMetrics();
        $progression = $this->getStoryProgression();
        $funnels     = $this->getSessionFunnels();
        $dropOffs    = $this->dropOffSessions();
        $baselineLabel = \App\Support\Analytics::baselineLabel();
        $abandonedLabel = \App\Support\Analytics::abandonedLabel();
    @endphp

    <div class="flex flex-col gap-7">

        {{-- Baseline + Metric Definitions --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <p class="text-[13px] text-gray-600 dark:text-gray-400">
                    <strong class="text-gray-950 dark:text-white">Data baseline: {{ $baselineLabel }}.</strong>
                    All metrics exclude data before this date.
                </p>
            </div>
            <div class="px-6 py-5">
                <p class="mb-4 text-[13px] font-semibold text-gray-700 dark:text-gray-200">Metric glossary</p>
                <div class="grid grid-cols-1 gap-x-10 gap-y-3 md:grid-cols-2">
                    @php
                        $glossary = [
                            ['Starts',              'Total games created for a story since the baseline (excl. previews).'],
                            ['Completion %',        'Unique games completed ÷ starts. Capped at 100%; replays do not inflate this.'],
                            ['Comp. Events',        'Total rows in game_completions (all story cycles; can exceed starts).'],
                            ['Replay %',            'Unique replayers ÷ unique completed games.'],
                            ['Incomplete',          'Started and not yet completed. User may still be actively reading.'],
                            ['Abandoned',           'Incomplete with no gameplay activity for ' . $abandonedLabel . '.'],
                            ['Content Progression', 'Started → Reached S2 → … → Completed (distinct games per gameplay session step).'],
                            ['Avg / Median Session','Mean and median of (completed_at − started_at) per gameplay session.'],
                            ['Avg Completion',      'Mean time from Session 1 start to story completion, per story cycle.'],
                            ['Drop-off Session',    'Gameplay session with the highest absolute user loss (reached minus completed).'],
                        ];
                    @endphp
                    @foreach ($glossary as $item)
                        <div class="flex items-baseline gap-2">
                            <span class="shrink-0 whitespace-nowrap text-[13px] font-semibold text-gray-900 dark:text-white">{{ $item[0] }}</span>
                            <span class="text-[13px] text-gray-600 dark:text-gray-400">{{ $item[1] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($stories->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white px-6 py-16 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                No gameplay data yet. Metrics will appear once users start stories on or after {{ $baselineLabel }}.
            </div>
        @else

            {{-- Story Summary Table --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <p class="text-[15px] font-semibold text-gray-950 dark:text-white">Story Summary</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-[13px]">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60">
                                @foreach (['Story', 'Starts', 'Completed', 'Completion %', 'Incomplete', 'Incomplete %', 'Abandoned', 'Abandoned %', 'Comp. Events', 'Replay Events', 'Unique Replayers', 'Replay %', 'Avg Session', 'Avg Completion', 'Drop-off Session'] as $heading)
                                    <th class="whitespace-nowrap px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wide text-gray-500 first:pl-5 first:text-left dark:text-gray-400">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stories as $story)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="min-w-[200px] max-w-[280px] px-5 py-3.5 font-semibold text-gray-900 dark:text-white">
                                        <div class="truncate">{{ $story->title }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($story->starts) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($story->unique_completed) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums">
                                        @if ($story->completion_rate !== null)
                                            <span class="font-semibold {{ $story->completion_rate >= 60 ? 'text-emerald-600 dark:text-emerald-400' : ($story->completion_rate >= 30 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ $story->completion_rate }}%</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($story->incomplete) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                        {{ $story->incomplete_rate !== null ? $story->incomplete_rate . '%' : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($story->abandoned) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums">
                                        @if ($story->abandoned_rate !== null)
                                            <span class="font-semibold {{ $story->abandoned_rate >= 60 ? 'text-red-600 dark:text-red-400' : ($story->abandoned_rate >= 30 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') }}">{{ $story->abandoned_rate }}%</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($story->completion_events) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($story->replay_events) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($story->unique_replayers) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums">
                                        @if ($story->replay_rate !== null)
                                            <span class="font-semibold text-primary-600 dark:text-primary-400">{{ $story->replay_rate }}%</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                        @if ($story->avg_minutes !== null)
                                            @php $mins = (float) $story->avg_minutes; $h = floor($mins / 60); $m = round($mins % 60); @endphp
                                            {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                        @if ($story->avg_completion_minutes !== null)
                                            @php $mins = (float) $story->avg_completion_minutes; $h = floor($mins / 60); $m = round($mins % 60); @endphp
                                            {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                        @if ($story->drop_off_session !== null)
                                            <span class="inline-block rounded-md bg-red-50 px-2.5 py-0.5 text-[11px] font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">Session {{ $story->drop_off_session }}</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Summary footer --}}
                @php
                    $totalStarts           = $stories->sum('starts');
                    $totalCompleted        = $stories->sum('unique_completed');
                    $totalCompletionEvents = $stories->sum('completion_events');
                    $totalAbandoned        = $stories->sum('abandoned');
                    $totalReplayEvents     = $stories->sum('replay_events');
                    $totalReplayers        = $stories->sum('unique_replayers');
                    $overallCompletion     = $totalStarts > 0 ? round(($totalCompleted / $totalStarts) * 100, 1) : null;
                @endphp
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 border-t border-gray-100 bg-gray-50 px-5 py-3 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400">
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $stories->count() }} stories</span>
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <span>{{ number_format($totalStarts) }} starts</span>
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <span>{{ number_format($totalCompleted) }} completed</span>
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <span>{{ number_format($totalCompletionEvents) }} completion events</span>
                    @if ($overallCompletion !== null)
                        <span class="text-gray-300 dark:text-gray-600">·</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $overallCompletion }}% overall completion</span>
                    @endif
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <span>{{ number_format($totalAbandoned) }} abandoned</span>
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <span>{{ number_format($totalReplayEvents) }} replays by {{ number_format($totalReplayers) }} unique replayers</span>
                </div>
            </div>

            {{-- Content Progression --}}
            @if ($progression->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <p class="mb-0.5 text-[15px] font-semibold text-gray-950 dark:text-white">Content Progression</p>
                        <p class="text-[13px] text-gray-500 dark:text-gray-400">How many distinct games reach each gameplay session. Shows where stories lose users.</p>
                    </div>
                    <div class="flex flex-col gap-3 px-6 py-4">
                        @foreach ($progression as $prog)
                            @php $starts = (int) $prog->starts; @endphp
                            <div class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
                                <div class="border-b border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-gray-800 dark:bg-gray-800/60">
                                    <span class="text-[13px] font-semibold text-gray-900 dark:text-white">{{ $prog->title }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 px-4 py-3 text-[13px]">
                                    <span class="text-gray-600 dark:text-gray-400">Started</span>
                                    <span class="mr-2 font-bold text-gray-900 dark:text-white">{{ number_format($starts) }}</span>
                                    @foreach ($prog->reached as $sessionNum => $reached)
                                        @php $pct = $starts > 0 ? round($reached / $starts * 100, 1) : null; @endphp
                                        <span class="text-gray-600 dark:text-gray-400">Reached S{{ $sessionNum }}</span>
                                        <span class="mr-1 font-bold text-gray-700 dark:text-gray-200">{{ number_format($reached) }}</span>
                                        @if ($pct !== null)
                                            <span class="mr-2 text-xs text-gray-500 dark:text-gray-400">({{ $pct }}%)</span>
                                        @endif
                                    @endforeach
                                    @php $compPct = $starts > 0 ? round($prog->completions / $starts * 100, 1) : null; @endphp
                                    <span class="text-gray-600 dark:text-gray-400">Completed</span>
                                    <span class="mr-1 font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($prog->completions) }}</span>
                                    @if ($compPct !== null)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">({{ $compPct }}%)</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Session Funnel --}}
            @if ($funnels->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <p class="mb-0.5 text-[15px] font-semibold text-gray-950 dark:text-white">Session Funnel</p>
                        <p class="text-[13px] text-gray-500 dark:text-gray-400">Aggregated across all story cycles. Each row is one gameplay session (game, story cycle, session_number).</p>
                    </div>
                    <div class="flex flex-col gap-4 px-6 py-4">
                        @foreach ($stories as $story)
                            @php
                                $sessionRows = $funnels->get($story->id);
                                $dropOff     = $dropOffs[$story->id] ?? null;
                            @endphp
                            @if ($sessionRows && $sessionRows->isNotEmpty())
                                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/60">
                                        <div>
                                            <span class="text-[13px] font-semibold text-gray-900 dark:text-white">{{ $story->title }}</span>
                                            @if ($story->completion_rate !== null)
                                                <span class="ml-2.5 text-xs text-gray-500 dark:text-gray-400">{{ $story->completion_rate }}% story completion</span>
                                            @endif
                                        </div>
                                        @if ($dropOff !== null)
                                            <span class="rounded-md bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">Biggest drop: Session {{ $dropOff }}</span>
                                        @endif
                                    </div>
                                    <div class="overflow-x-auto">
                                    <table class="min-w-full border-collapse text-[13px]">
                                        <thead>
                                            <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
                                                @foreach (['Session', 'Reached', 'Completed', 'Dropped', 'Completion %', 'Avg / Median', 'Progress'] as $heading)
                                                    <th class="whitespace-nowrap px-4 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-500 first:text-left dark:text-gray-400">{{ $heading }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($sessionRows as $session)
                                                @php
                                                    $isDropOff = $dropOff !== null && (int) $session->session_number === $dropOff;
                                                    $pct       = $session->completion_pct;
                                                    $barWidth  = $pct !== null ? max(2, (int) $pct) : 0;
                                                    $fmtDur    = function (?float $mins): string {
                                                        if ($mins === null) return '—';
                                                        $h = floor($mins / 60);
                                                        $m = round($mins % 60);
                                                        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                                                    };
                                                @endphp
                                                <tr class="border-t border-gray-100 dark:border-gray-800 {{ $isDropOff ? 'bg-red-50/60 dark:bg-red-950/20' : '' }}">
                                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-700 dark:text-gray-200">
                                                        Session {{ $session->session_number }}
                                                        @if ($isDropOff)
                                                            <span class="ml-1.5 text-[11px] font-bold text-red-600 dark:text-red-400">drop-off</span>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format($session->reached) }}</td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format($session->completed_cnt) }}</td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                                        @if ((int) $session->dropped > 0)
                                                            <span class="font-semibold {{ $isDropOff ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300' }}">−{{ number_format($session->dropped) }}</span>
                                                        @else
                                                            <span class="text-gray-400 dark:text-gray-500">0</span>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                                        @if ($pct !== null)
                                                            <span class="font-bold {{ $pct >= 70 ? 'text-emerald-600 dark:text-emerald-400' : ($pct >= 40 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ $pct }}%</span>
                                                        @else
                                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                                        @if ($session->avg_minutes !== null || $session->median_minutes !== null)
                                                            {{ $fmtDur($session->avg_minutes !== null ? (float) $session->avg_minutes : null) }}
                                                            <span class="mx-0.5 text-gray-400 dark:text-gray-500">/</span>
                                                            {{ $fmtDur($session->median_minutes !== null ? (float) $session->median_minutes : null) }}
                                                        @else
                                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        @if ($pct !== null)
                                                            <div class="h-1.5 w-[100px] overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                                                <div class="h-full rounded-full {{ $pct >= 70 ? 'bg-emerald-500' : ($pct >= 40 ? 'bg-amber-500' : 'bg-red-500') }}" style="width:{{ $barWidth }}%;"></div>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

        @endif
    </div>
</x-filament-panels::page>
