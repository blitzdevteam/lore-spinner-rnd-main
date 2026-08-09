<x-filament-panels::page>

    @php
        $acquisition = $this->getAcquisitionMetrics();
        $funnel      = $this->getEngagementFunnel();
        $topStories  = $this->getTopStories();
        $keyInsights = $this->getKeyInsights();
        $baselineLabel = \App\Support\Analytics::baselineLabel();
        $abandonedLabel = \App\Support\Analytics::abandonedLabel();

        $defs = [
            ['Unique Visits',       'A distinct visitor cookie session that loaded a tracked public page. This is NOT a gameplay session. Direct /register arrivals are not counted here.'],
            ['Signups',             'New registered accounts created in the period, from any entry point including direct registration.'],
            ['Story Starts',        'Non-preview game instances created since the baseline. One row per user per story. Includes games from users who registered before the baseline.'],
            ['Session 1 Completed', 'Games where the player finished Session 1 and advanced to Session 2 (completed_at set on session_number = 1 in game_session_completions).'],
            ['Story Completions',   'Distinct games that reached the story end state and generated a game_completions record. One game counted once regardless of how many times it was completed.'],
            ['Replayed Games',      'Distinct non-preview games reset after the player had already completed the story at least once.'],
            ['Abandoned',           'Incomplete games with no gameplay activity (prompts, session advances, or resets) for ' . $abandonedLabel . '.'],
            ['Return Users',        'Registered users active in the period who also had activity on a different calendar day before the period start.'],
        ];
    @endphp

    {{-- ── Baseline notice ──────────────────────────────────────────── --}}
    <div style="display:flex; align-items:flex-start; gap:12px; padding:14px 20px; border-radius:10px; background:#f0fdf4; border:1px solid #bbf7d0;">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0; margin-top:1px;">
            <circle cx="10" cy="10" r="9" stroke="#16a34a" stroke-width="1.5"/>
            <path d="M10 9v5M10 7h.01" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <div>
            <p style="margin:0 0 2px; font-size:13px; font-weight:600; color:#15803d;">Baseline: {{ $baselineLabel }} — All metrics start here</p>
            <p style="margin:0; font-size:12px; color:#16a34a;">Data before this date is excluded. See Metric Glossary below for precise definitions.</p>
        </div>
    </div>

    {{-- ── KPI Stat Cards ──────────────────────────────────────────── --}}
    @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget::class)

    {{-- ── Acquisition (Visits + Signups — independent counts) ────── --}}
    @if (!empty($acquisition))
    <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
        <div style="padding:14px 24px 10px; border-bottom:1px solid #f3f4f6; background:#fafafa; display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Acquisition</p>
                <p style="margin:0; font-size:12px; color:#9ca3af;">Platform-wide counts — these are independent metrics, not a subset funnel</p>
            </div>
            <span style="font-size:11px; color:#9ca3af; background:#f3f4f6; padding:3px 8px; border-radius:6px; white-space:nowrap; margin-top:2px;">since baseline</span>
        </div>
        <div style="padding:20px 24px; display:flex; flex-direction:column; gap:14px;">
            @foreach ($acquisition as $row)
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:80px; flex-shrink:0; text-align:right;">
                    <span style="font-size:13px; font-weight:600; color:#374151;">{{ $row['label'] }}</span>
                </div>
                <div style="flex:1; position:relative; height:28px; background:#f3f4f6; border-radius:6px; overflow:hidden;">
                    <div style="position:absolute; left:0; top:0; bottom:0; border-radius:6px; background:{{ $row['color'] }}; width:{{ $row['bar_width'] }}%; opacity:0.80;"></div>
                    <div style="position:absolute; left:10px; top:0; bottom:0; display:flex; align-items:center;">
                        <span style="font-size:13px; font-weight:700; color:#fff; text-shadow:0 1px 2px rgba(0,0,0,0.25);">{{ number_format($row['value']) }}</span>
                    </div>
                </div>
                <div style="width:300px; flex-shrink:0;">
                    <span style="font-size:11px; color:#9ca3af; font-style:italic;">{{ $row['note'] }}</span>
                </div>
            </div>
            @endforeach
            <p style="margin:6px 0 0; font-size:11px; color:#d1d5db; border-top:1px solid #f3f4f6; padding-top:10px;">
                ⓘ Visits and Signups are counted from different sources. A user can sign up without creating a Visit (e.g. direct /register link). No cross-conversion % is shown because the populations do not nest.
            </p>
        </div>
    </div>
    @endif

    {{-- ── Engagement Funnel (nested — Starts is the anchor) ─────── --}}
    @if (!empty($funnel))
    @php $starts = collect($funnel)->firstWhere('label', 'Story Starts'); @endphp
    <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
        <div style="padding:14px 24px 10px; border-bottom:1px solid #f3f4f6; background:#fafafa; display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Engagement Funnel</p>
                <p style="margin:0; font-size:12px; color:#9ca3af;">
                    Strictly nested steps — each row is a provable subset of Story Starts
                    @if ($starts)
                        <span style="font-weight:600; color:#3b82f6;">({{ number_format($starts['value']) }} starts = 100%)</span>
                    @endif
                </p>
            </div>
            <span style="font-size:11px; color:#9ca3af; background:#f3f4f6; padding:3px 8px; border-radius:6px; white-space:nowrap; margin-top:2px;">% of starts</span>
        </div>
        <div style="padding:20px 24px; display:flex; flex-direction:column; gap:12px;">
            @foreach ($funnel as $i => $step)
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:145px; flex-shrink:0; text-align:right;">
                    <span style="font-size:13px; font-weight:{{ $i === 0 ? '700' : '600' }}; color:{{ $i === 0 ? '#111827' : '#374151' }};">{{ $step['label'] }}</span>
                </div>
                <div style="flex:1; position:relative; height:28px; background:#f3f4f6; border-radius:6px; overflow:hidden;">
                    @if ($step['bar_width'] > 0)
                    <div style="position:absolute; left:0; top:0; bottom:0; border-radius:6px; background:{{ $step['color'] }}; width:{{ $step['bar_width'] }}%; opacity:{{ $i === 0 ? '1' : '0.80' }};"></div>
                    @endif
                    <div style="position:absolute; left:10px; top:0; bottom:0; display:flex; align-items:center;">
                        <span style="font-size:{{ $i === 0 ? '13px' : '12px' }}; font-weight:700; color:{{ $step['bar_width'] > 10 ? '#fff' : '#374151' }}; text-shadow:{{ $step['bar_width'] > 10 ? '0 1px 2px rgba(0,0,0,0.25)' : 'none' }};">
                            {{ number_format($step['value']) }}
                        </span>
                    </div>
                </div>
                <div style="width:160px; flex-shrink:0; display:flex; align-items:center; gap:6px;">
                    @if ($step['pct_starts'] !== null)
                    <span style="font-size:11px; font-weight:600; color:#6b7280; background:#f3f4f6; padding:2px 7px; border-radius:12px; white-space:nowrap;">
                        {{ $step['pct_starts'] }}% of starts
                    </span>
                    @endif
                    @if ($step['pct_prev'] !== null && $i > 1)
                    {{-- Only show step-to-step % for non-anchor rows below Starts --}}
                    @php
                        $pct   = $step['pct_prev'];
                        $bg    = $pct >= 60 ? '#f0fdf4' : ($pct >= 30 ? '#fffbeb' : '#fef2f2');
                        $color = $pct >= 60 ? '#15803d' : ($pct >= 30 ? '#b45309' : '#dc2626');
                    @endphp
                    <span style="font-size:11px; font-weight:600; color:{{ $color }}; background:{{ $bg }}; padding:2px 7px; border-radius:12px; white-space:nowrap;">
                        {{ $pct }}% of prev
                    </span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Key Insights + Top Stories ───────────────────────────────── --}}
    @if (!empty($keyInsights) || !empty($topStories))
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">

        @if (!empty($keyInsights))
        <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
            <div style="padding:14px 24px 10px; border-bottom:1px solid #f3f4f6; background:#fafafa;">
                <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Key Insights</p>
                <p style="margin:0; font-size:12px; color:#9ca3af;">Computed from live data since baseline</p>
            </div>
            <div style="padding:16px 24px; display:flex; flex-direction:column; gap:12px;">
                @foreach ($keyInsights as $insight)
                <div style="display:flex; align-items:flex-start; gap:12px; padding:12px 14px; border-radius:8px; background:#f9fafb; border-left:3px solid {{ $insight['color'] }};">
                    <span style="font-size:18px; line-height:1; flex-shrink:0;">{{ $insight['icon'] }}</span>
                    <p style="margin:0; font-size:13px; color:#374151; line-height:1.5;">
                        <span style="font-weight:700; color:{{ $insight['color'] }};">{{ $insight['highlight'] }}</span>
                        {{ $insight['text'] }}
                    </p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if (!empty($topStories))
        <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
            <div style="padding:14px 24px 10px; border-bottom:1px solid #f3f4f6; background:#fafafa;">
                <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Top Stories</p>
                <p style="margin:0; font-size:12px; color:#9ca3af;">Ranked by game starts since baseline</p>
            </div>
            <div style="padding:16px 24px; display:flex; flex-direction:column; gap:12px;">
                @foreach ($topStories as $rank => $story)
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="width:22px; height:22px; border-radius:50%; background:{{ $rank === 0 ? '#6366f1' : ($rank === 1 ? '#8b5cf6' : '#d1d5db') }}; color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0;">{{ $rank + 1 }}</span>
                    <div style="flex:1; min-width:0;">
                        <p style="margin:0 0 4px; font-size:13px; font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $story['title'] }}</p>
                        <div style="height:6px; background:#f3f4f6; border-radius:3px; overflow:hidden;">
                            <div style="height:100%; background:{{ $rank === 0 ? '#6366f1' : ($rank === 1 ? '#8b5cf6' : '#a5b4fc') }}; border-radius:3px; width:{{ $story['pct'] }}%;"></div>
                        </div>
                    </div>
                    <span style="flex-shrink:0; font-size:13px; font-weight:700; color:#374151; width:65px; text-align:right;">{{ $story['starts'] }} start{{ $story['starts'] !== 1 ? 's' : '' }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
    @endif

    {{-- ── Retention ────────────────────────────────────────────────── --}}
    @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget::class)

    {{-- ── Metric Glossary ─────────────────────────────────────────── --}}
    <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
        <div style="padding:14px 24px 10px; border-bottom:1px solid #f3f4f6; background:#fafafa;">
            <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Metric Glossary</p>
            <p style="margin:0; font-size:13px; color:#9ca3af;">Precise definitions for every number on this page — read before interpreting funnel percentages.</p>
        </div>
        <div style="padding:20px 24px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0;">
                @foreach ($defs as $i => $def)
                <div style="padding:12px 16px; display:flex; gap:10px; align-items:baseline; {{ $i >= 2 ? 'border-top:1px solid #f3f4f6;' : '' }} {{ $i % 2 === 1 ? 'border-left:1px solid #f3f4f6;' : '' }}">
                    <span style="font-weight:700; font-size:13px; color:#111827; white-space:nowrap; flex-shrink:0;">{{ $def[0] }}</span>
                    <span style="font-size:12px; color:#6b7280; line-height:1.5;">{{ $def[1] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</x-filament-panels::page>
