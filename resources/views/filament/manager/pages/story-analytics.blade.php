<x-filament-panels::page>
    @php
        $stories     = $this->getStoryMetrics();
        $progression = $this->getStoryProgression();
        $funnels     = $this->getSessionFunnels();
        $dropOffs    = $this->dropOffSessions();
        $baselineLabel = \App\Support\Analytics::baselineLabel();
        $abandonedLabel = \App\Support\Analytics::abandonedLabel();
    @endphp

    <div style="display:flex; flex-direction:column; gap:28px;">

        {{-- Baseline + Metric Definitions --}}
        <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
            <div style="padding:16px 24px; border-bottom:1px solid #f3f4f6;">
                <p style="font-size:13px; color:#6b7280; margin:0;">
                    <strong style="color:#111827;">Data baseline: {{ $baselineLabel }}.</strong>
                    All metrics exclude data before this date.
                </p>
            </div>
            <div style="padding:20px 24px;">
                <p style="font-weight:600; font-size:13px; color:#374151; margin:0 0 16px;">Metric glossary</p>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px 40px;">
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
                        <div style="display:flex; gap:8px; align-items:baseline;">
                            <span style="font-weight:600; font-size:13px; color:#111827; white-space:nowrap; flex-shrink:0;">{{ $item[0] }}</span>
                            <span style="font-size:13px; color:#6b7280;">{{ $item[1] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($stories->isEmpty())
            <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:64px 24px; text-align:center; color:#9ca3af; font-size:14px;">
                No gameplay data yet. Metrics will appear once users start stories on or after {{ $baselineLabel }}.
            </div>
        @else

            {{-- Story Summary Table --}}
            <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
                <div style="padding:16px 24px; border-bottom:1px solid #f3f4f6;">
                    <p style="font-weight:600; font-size:15px; color:#111827; margin:0;">Story Summary</p>
                </div>
                <div style="overflow-x:auto;">
                    <table style="min-width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                <th style="padding:12px 20px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Story</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Starts</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Completed</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Completion %</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Incomplete</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Incomplete %</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Abandoned</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Abandoned %</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Comp. Events</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Replay Events</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Unique Replayers</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Replay %</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Avg Session</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Avg Completion</th>
                                <th style="padding:12px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Drop-off Session</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stories as $story)
                                <tr style="border-top:1px solid #f3f4f6;">
                                    <td style="padding:14px 20px; font-weight:600; color:#111827; min-width:200px; max-width:280px;">
                                        <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $story->title }}</div>
                                    </td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->starts) }}</td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->unique_completed) }}</td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                        @if ($story->completion_rate !== null)
                                            <span style="font-weight:600; color:{{ $story->completion_rate >= 60 ? '#16a34a' : ($story->completion_rate >= 30 ? '#ca8a04' : '#dc2626') }};">{{ $story->completion_rate }}%</span>
                                        @else
                                            <span style="color:#d1d5db;">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->incomplete) }}</td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#6b7280;">
                                        {{ $story->incomplete_rate !== null ? $story->incomplete_rate . '%' : '—' }}
                                    </td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->abandoned) }}</td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                        @if ($story->abandoned_rate !== null)
                                            <span style="font-weight:600; color:{{ $story->abandoned_rate >= 60 ? '#dc2626' : ($story->abandoned_rate >= 30 ? '#ca8a04' : '#16a34a') }};">{{ $story->abandoned_rate }}%</span>
                                        @else
                                            <span style="color:#d1d5db;">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#6b7280;">{{ number_format($story->completion_events) }}</td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->replay_events) }}</td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->unique_replayers) }}</td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                        @if ($story->replay_rate !== null)
                                            <span style="font-weight:600; color:#9333ea;">{{ $story->replay_rate }}%</span>
                                        @else
                                            <span style="color:#d1d5db;">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#6b7280;">
                                        @if ($story->avg_minutes !== null)
                                            @php $mins = (float) $story->avg_minutes; $h = floor($mins / 60); $m = round($mins % 60); @endphp
                                            {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                                        @else
                                            <span style="color:#d1d5db;">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:14px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#6b7280;">
                                        @if ($story->avg_completion_minutes !== null)
                                            @php $mins = (float) $story->avg_completion_minutes; $h = floor($mins / 60); $m = round($mins % 60); @endphp
                                            {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                                        @else
                                            <span style="color:#d1d5db;">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:14px 16px; text-align:right;">
                                        @if ($story->drop_off_session !== null)
                                            <span style="display:inline-block; border-radius:6px; padding:2px 10px; font-size:11px; font-weight:700; background:#fee2e2; color:#b91c1c;">Session {{ $story->drop_off_session }}</span>
                                        @else
                                            <span style="color:#d1d5db;">—</span>
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
                <div style="padding:12px 20px; border-top:1px solid #f3f4f6; background:#f9fafb; font-size:12px; color:#6b7280; display:flex; flex-wrap:wrap; gap:6px 0; align-items:center;">
                    <span style="font-weight:600; color:#374151;">{{ $stories->count() }} stories</span>
                    <span style="margin:0 8px; color:#d1d5db;">·</span>
                    <span>{{ number_format($totalStarts) }} starts</span>
                    <span style="margin:0 8px; color:#d1d5db;">·</span>
                    <span>{{ number_format($totalCompleted) }} completed</span>
                    <span style="margin:0 8px; color:#d1d5db;">·</span>
                    <span>{{ number_format($totalCompletionEvents) }} completion events</span>
                    @if ($overallCompletion !== null)
                        <span style="margin:0 8px; color:#d1d5db;">·</span>
                        <span style="font-weight:600; color:#374151;">{{ $overallCompletion }}% overall completion</span>
                    @endif
                    <span style="margin:0 8px; color:#d1d5db;">·</span>
                    <span>{{ number_format($totalAbandoned) }} abandoned</span>
                    <span style="margin:0 8px; color:#d1d5db;">·</span>
                    <span>{{ number_format($totalReplayEvents) }} replays by {{ number_format($totalReplayers) }} unique replayers</span>
                </div>
            </div>

            {{-- Content Progression --}}
            @if ($progression->isNotEmpty())
                <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
                    <div style="padding:16px 24px; border-bottom:1px solid #f3f4f6;">
                        <p style="font-weight:600; font-size:15px; color:#111827; margin:0 0 2px;">Content Progression</p>
                        <p style="font-size:13px; color:#9ca3af; margin:0;">How many distinct games reach each gameplay session. Shows where stories lose users.</p>
                    </div>
                    <div style="padding:16px 24px; display:flex; flex-direction:column; gap:12px;">
                        @foreach ($progression as $prog)
                            @php $starts = (int) $prog->starts; @endphp
                            <div style="border:1px solid #f3f4f6; border-radius:8px; overflow:hidden;">
                                <div style="padding:10px 16px; background:#f9fafb; border-bottom:1px solid #f3f4f6;">
                                    <span style="font-weight:600; font-size:13px; color:#111827;">{{ $prog->title }}</span>
                                </div>
                                <div style="padding:12px 16px; display:flex; flex-wrap:wrap; gap:6px 0; align-items:center; font-size:13px;">
                                    <span style="color:#6b7280;">Started</span>
                                    <span style="font-weight:700; color:#111827; margin:0 16px 0 8px;">{{ number_format($starts) }}</span>
                                    @foreach ($prog->reached as $sessionNum => $reached)
                                        @php $pct = $starts > 0 ? round($reached / $starts * 100, 1) : null; @endphp
                                        <span style="color:#6b7280;">Reached S{{ $sessionNum }}</span>
                                        <span style="font-weight:700; color:#374151; margin:0 4px 0 8px;">{{ number_format($reached) }}</span>
                                        @if ($pct !== null)
                                            <span style="color:#9ca3af; font-size:12px; margin-right:16px;">({{ $pct }}%)</span>
                                        @endif
                                    @endforeach
                                    @php $compPct = $starts > 0 ? round($prog->completions / $starts * 100, 1) : null; @endphp
                                    <span style="color:#6b7280;">Completed</span>
                                    <span style="font-weight:700; color:#16a34a; margin:0 4px 0 8px;">{{ number_format($prog->completions) }}</span>
                                    @if ($compPct !== null)
                                        <span style="color:#9ca3af; font-size:12px;">({{ $compPct }}%)</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Session Funnel --}}
            @if ($funnels->isNotEmpty())
                <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
                    <div style="padding:16px 24px; border-bottom:1px solid #f3f4f6;">
                        <p style="font-weight:600; font-size:15px; color:#111827; margin:0 0 2px;">Session Funnel</p>
                        <p style="font-size:13px; color:#9ca3af; margin:0;">Aggregated across all story cycles. Each row is one gameplay session (game, story cycle, session_number).</p>
                    </div>
                    <div style="padding:16px 24px; display:flex; flex-direction:column; gap:16px;">
                        @foreach ($stories as $story)
                            @php
                                $sessionRows = $funnels->get($story->id);
                                $dropOff     = $dropOffs[$story->id] ?? null;
                            @endphp
                            @if ($sessionRows && $sessionRows->isNotEmpty())
                                <div style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                                    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                        <div>
                                            <span style="font-weight:600; font-size:13px; color:#111827;">{{ $story->title }}</span>
                                            @if ($story->completion_rate !== null)
                                                <span style="margin-left:10px; font-size:12px; color:#9ca3af;">{{ $story->completion_rate }}% story completion</span>
                                            @endif
                                        </div>
                                        @if ($dropOff !== null)
                                            <span style="font-size:12px; font-weight:600; color:#dc2626; background:#fee2e2; padding:2px 10px; border-radius:6px;">Biggest drop: Session {{ $dropOff }}</span>
                                        @endif
                                    </div>
                                    <table style="min-width:100%; border-collapse:collapse; font-size:13px;">
                                        <thead>
                                            <tr style="background:#f9fafb; border-bottom:1px solid #f3f4f6;">
                                                <th style="padding:10px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap; width:140px;">Session</th>
                                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Reached</th>
                                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Completed</th>
                                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Dropped</th>
                                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Completion %</th>
                                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Avg / Median</th>
                                                <th style="padding:10px 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; white-space:nowrap;">Progress</th>
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
                                                <tr style="border-top:1px solid #f3f4f6; {{ $isDropOff ? 'background:#fff5f5;' : '' }}">
                                                    <td style="padding:12px 16px; font-weight:500; color:#374151;">
                                                        Session {{ $session->session_number }}
                                                        @if ($isDropOff)
                                                            <span style="margin-left:6px; font-size:11px; font-weight:700; color:#ef4444;">drop-off</span>
                                                        @endif
                                                    </td>
                                                    <td style="padding:12px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#4b5563;">{{ number_format($session->reached) }}</td>
                                                    <td style="padding:12px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#4b5563;">{{ number_format($session->completed_cnt) }}</td>
                                                    <td style="padding:12px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                                        @if ((int) $session->dropped > 0)
                                                            <span style="font-weight:600; color:{{ $isDropOff ? '#dc2626' : '#6b7280' }};">−{{ number_format($session->dropped) }}</span>
                                                        @else
                                                            <span style="color:#d1d5db;">0</span>
                                                        @endif
                                                    </td>
                                                    <td style="padding:12px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                                        @if ($pct !== null)
                                                            <span style="font-weight:700; color:{{ $pct >= 70 ? '#16a34a' : ($pct >= 40 ? '#ca8a04' : '#dc2626') }};">{{ $pct }}%</span>
                                                        @else
                                                            <span style="color:#d1d5db;">—</span>
                                                        @endif
                                                    </td>
                                                    <td style="padding:12px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#6b7280;">
                                                        @if ($session->avg_minutes !== null || $session->median_minutes !== null)
                                                            {{ $fmtDur($session->avg_minutes !== null ? (float) $session->avg_minutes : null) }}
                                                            <span style="color:#d1d5db; margin:0 3px;">/</span>
                                                            {{ $fmtDur($session->median_minutes !== null ? (float) $session->median_minutes : null) }}
                                                        @else
                                                            <span style="color:#d1d5db;">—</span>
                                                        @endif
                                                    </td>
                                                    <td style="padding:12px 16px;">
                                                        @if ($pct !== null)
                                                            <div style="width:100px; height:6px; background:#f3f4f6; border-radius:9999px; overflow:hidden;">
                                                                <div style="height:100%; border-radius:9999px; width:{{ $barWidth }}%; background:{{ $pct >= 70 ? '#22c55e' : ($pct >= 40 ? '#eab308' : '#ef4444') }};"></div>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

        @endif
    </div>
</x-filament-panels::page>
