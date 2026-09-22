<x-filament-panels::page>

    @php
        $analyticsFilters = $this->getAnalyticsFilters();
        $filterSummary = $this->getFilterSummary();
        $dateRangeOptions = $this->getDateRangeOptions();
        $filterKey = md5((string) json_encode($analyticsFilters));
        $acquisition = $this->getAcquisitionMetrics();
        $funnel      = $this->getEngagementFunnel();
        $topStories  = $this->getTopStories();
        $keyInsights = $this->getKeyInsights();
        $baselineLabel = \App\Support\Analytics::baselineLabel();
        $abandonedLabel = \App\Support\Analytics::abandonedLabel();

        $defs = [
            ['Unique Visits',       'A distinct visitor cookie session that loaded a tracked public page. This is NOT a gameplay session. Direct /register arrivals are not counted here.'],
            ['Signups',             'New registered accounts created in the period, from any entry point including direct registration.'],
            ['Story Starts',        'Non-preview game instances created in the selected period. One row per user per story. Includes games from users who registered before the baseline.'],
            ['Session 1 Completed', 'Games where the player finished Session 1 and advanced to Session 2 (completed_at set on session_number = 1 in game_session_completions).'],
            ['Story Completions',   'Distinct games that reached the story end state and generated a game_completions record. One game counted once regardless of how many times it was completed.'],
            ['Replayed Games',      'Distinct non-preview games reset after the player had already completed the story at least once.'],
            ['Abandoned',           'Incomplete games with no gameplay activity (prompts, session advances, or resets) for ' . $abandonedLabel . '.'],
            ['Return Users',        'Registered users active in the period who also had activity on a different calendar day before the period start.'],
        ];
    @endphp

    {{-- ── Baseline notice ──────────────────────────────────────────── --}}
    <div class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-3.5 dark:border-emerald-800/60 dark:bg-emerald-950/30">
        <svg class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
            <path d="M10 9v5M10 7h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <div>
            <p class="mb-0.5 text-[13px] font-semibold text-emerald-800 dark:text-emerald-200">Baseline: {{ $baselineLabel }} — All metrics start here</p>
            <p class="text-xs text-emerald-700 dark:text-emerald-300">Data before this date is excluded. See Metric Glossary below for precise definitions.</p>
        </div>
    </div>

    {{-- ── Shared analytics filters ─────────────────────────────────── --}}
    <div class="relative z-20 overflow-visible rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" wire:loading.class="opacity-60" wire:target="filters,setDateRange,resetFilters">
        <div class="flex flex-wrap items-start justify-between gap-3 rounded-t-xl border-b border-gray-100 bg-gray-50 px-6 pb-3 pt-4 dark:border-gray-800 dark:bg-gray-800/60">
            <div>
                <p class="mb-0.5 text-[15px] font-semibold text-gray-950 dark:text-white">Filters</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Every gameplay metric below uses the selected date, player, and story scope.</p>
            </div>
            <button type="button" wire:click="resetFilters" class="text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                Reset filters
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 px-6 py-5 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="analytics-date-range" class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300">Date range</label>
                <x-filament::input.wrapper class="w-full" prefix-icon="heroicon-m-calendar-days">
                    <x-filament::input.select id="analytics-date-range" wire:change="setDateRange($event.target.value)">
                        @foreach ($dateRangeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($analyticsFilters['range'] === $value)>{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $filterSummary['date'] }}</p>
            </div>

            <div>
                <label for="analytics-date-from" class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300">From</label>
                <x-filament::input.wrapper
                    :disabled="$analyticsFilters['range'] !== \App\Support\AnalyticsFilters::RANGE_CUSTOM"
                    class="w-full"
                    prefix-icon="heroicon-m-calendar"
                >
                    <x-filament::input
                        id="analytics-date-from"
                        type="date"
                        wire:model.live="filters.from"
                        value="{{ $analyticsFilters['from'] ?? '' }}"
                        min="{{ $filterSummary['min'] }}"
                        max="{{ $filterSummary['max'] }}"
                        :disabled="$analyticsFilters['range'] !== \App\Support\AnalyticsFilters::RANGE_CUSTOM"
                    />
                </x-filament::input.wrapper>
                <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">Dates before {{ $baselineLabel }} are excluded.</p>
            </div>

            <div>
                <label for="analytics-date-to" class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300">To</label>
                <x-filament::input.wrapper
                    :disabled="$analyticsFilters['range'] !== \App\Support\AnalyticsFilters::RANGE_CUSTOM"
                    class="w-full"
                    prefix-icon="heroicon-m-calendar"
                >
                    <x-filament::input
                        id="analytics-date-to"
                        type="date"
                        wire:model.live="filters.to"
                        value="{{ $analyticsFilters['to'] ?? '' }}"
                        min="{{ $filterSummary['min'] }}"
                        max="{{ $filterSummary['max'] }}"
                        :disabled="$analyticsFilters['range'] !== \App\Support\AnalyticsFilters::RANGE_CUSTOM"
                    />
                </x-filament::input.wrapper>
                <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">The range ends at today.</p>
            </div>

            <div>
                <label for="analytics-user" class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300">Player</label>
                <div id="analytics-user">
                    {{ $this->playerFilter }}
                </div>
                <p class="mt-1.5 truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $filterSummary['user'] }}</p>
            </div>

            <div class="md:col-span-2 xl:col-span-4">
                <label for="analytics-story" class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300">Story</label>
                <div id="analytics-story">
                    {{ $this->storyFilter }}
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $filterSummary['story'] }} · story filtering applies to gameplay metrics and matching public story-page visits.</p>
            </div>
        </div>
    </div>

    {{-- ── KPI Stat Cards ──────────────────────────────────────────── --}}
    @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget::class, ['pageFilters' => $analyticsFilters], key('analytics-kpi-'.$filterKey))

    {{-- ── Acquisition (Visits + Signups — independent counts) ────── --}}
    @if (!empty($acquisition))
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start justify-between border-b border-gray-100 bg-gray-50 px-6 pb-2.5 pt-3.5 dark:border-gray-800 dark:bg-gray-800/60">
            <div>
                <p class="mb-0.5 text-[15px] font-semibold text-gray-950 dark:text-white">Acquisition</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Platform-wide counts — these are independent metrics, not a subset funnel</p>
            </div>
            <span class="mt-0.5 whitespace-nowrap rounded-md bg-gray-100 px-2 py-0.5 text-[11px] text-gray-500 dark:bg-gray-700 dark:text-gray-300">{{ $filterSummary['date'] }}</span>
        </div>
        <div class="flex flex-col gap-3.5 px-6 py-5">
            @foreach ($acquisition as $row)
            <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3.5 gap-y-1.5 sm:grid-cols-[5rem_minmax(0,1fr)_18rem]">
                <div class="text-right sm:w-20">
                    <span class="text-[13px] font-semibold text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                </div>
                <div class="relative h-7 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800">
                    <div class="absolute inset-y-0 left-0 rounded-md opacity-80" style="background:{{ $row['color'] }}; width:{{ $row['bar_width'] }}%;"></div>
                    <div class="absolute inset-y-0 left-2.5 flex items-center">
                        <span class="text-[13px] font-bold text-white drop-shadow-sm">{{ number_format($row['value']) }}</span>
                    </div>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <span class="text-[11px] italic text-gray-500 dark:text-gray-400">{{ $row['note'] }}</span>
                </div>
            </div>
            @endforeach
            <p class="mt-1.5 border-t border-gray-100 pt-2.5 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <span class="font-semibold text-gray-700 dark:text-gray-300">Note:</span> Visits and Signups are counted from different sources. A user can sign up without creating a Visit (e.g. direct /register link). No cross-conversion % is shown because the populations do not nest.
            </p>
        </div>
    </div>
    @endif

    {{-- ── Engagement Funnel (nested — Starts is the anchor) ─────── --}}
    @if (!empty($funnel))
    @php $starts = collect($funnel)->firstWhere('label', 'Story Starts'); @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start justify-between border-b border-gray-100 bg-gray-50 px-6 pb-2.5 pt-3.5 dark:border-gray-800 dark:bg-gray-800/60">
            <div>
                <p class="mb-0.5 text-[15px] font-semibold text-gray-950 dark:text-white">Engagement Funnel</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Strictly nested steps — each row is a provable subset of Story Starts
                    @if ($starts)
                        <span class="font-semibold text-primary-600 dark:text-primary-400">({{ number_format($starts['value']) }} starts = 100%)</span>
                    @endif
                </p>
            </div>
            <span class="mt-0.5 whitespace-nowrap rounded-md bg-gray-100 px-2 py-0.5 text-[11px] text-gray-500 dark:bg-gray-700 dark:text-gray-300">% of starts</span>
        </div>
        <div class="flex flex-col gap-3 px-6 py-5">
            @foreach ($funnel as $i => $step)
            <div class="grid grid-cols-[8rem_minmax(0,1fr)] items-center gap-x-3.5 gap-y-1.5 sm:grid-cols-[9.0625rem_minmax(0,1fr)_10rem]">
                <div class="text-right">
                    <span class="text-[13px] font-{{ $i === 0 ? 'bold' : 'semibold' }} {{ $i === 0 ? 'text-gray-950 dark:text-white' : 'text-gray-700 dark:text-gray-200' }}">{{ $step['label'] }}</span>
                </div>
                <div class="relative h-7 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800">
                    @if ($step['bar_width'] > 0)
                    <div class="absolute inset-y-0 left-0 rounded-md {{ $i === 0 ? '' : 'opacity-80' }}" style="background:{{ $step['color'] }}; width:{{ $step['bar_width'] }}%;"></div>
                    @endif
                    <div class="absolute inset-y-0 left-2.5 flex items-center">
                        <span class="text-xs font-bold {{ $step['bar_width'] > 10 ? 'text-white drop-shadow-sm' : 'text-gray-700 dark:text-gray-200' }}">
                            {{ number_format($step['value']) }}
                        </span>
                    </div>
                </div>
                <div class="col-span-2 flex items-center gap-1.5 sm:col-span-1">
                    @if ($step['pct_starts'] !== null)
                    <span class="whitespace-nowrap rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {{ $step['pct_starts'] }}% of starts
                    </span>
                    @endif
                    @if ($step['pct_prev'] !== null && $i > 1)
                    {{-- Only show step-to-step % for non-anchor rows below Starts --}}
                    @php
                        $pct   = $step['pct_prev'];
                        $pctClasses = $pct >= 60
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : ($pct >= 30
                                ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300');
                    @endphp
                    <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $pctClasses }}">
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
    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-2">

        @if (!empty($keyInsights))
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 bg-gray-50 px-6 pb-2.5 pt-3.5 dark:border-gray-800 dark:bg-gray-800/60">
                <p class="mb-0.5 text-[15px] font-semibold text-gray-950 dark:text-white">Key Insights</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Computed from live data for {{ $filterSummary['date'] }}</p>
            </div>
            <div class="flex flex-col gap-3 px-6 py-4">
                @foreach ($keyInsights as $insight)
                @php
                    $insightClasses = match ($insight['tone']) {
                        'success' => 'border-emerald-500 bg-emerald-50/70 dark:border-emerald-400 dark:bg-emerald-950/25',
                        'warning' => 'border-amber-500 bg-amber-50/70 dark:border-amber-400 dark:bg-amber-950/25',
                        'danger' => 'border-red-500 bg-red-50/70 dark:border-red-400 dark:bg-red-950/25',
                        'info' => 'border-blue-500 bg-blue-50/70 dark:border-blue-400 dark:bg-blue-950/25',
                        default => 'border-primary-500 bg-primary-50/70 dark:border-primary-400 dark:bg-primary-950/25',
                    };
                    $insightTextClasses = match ($insight['tone']) {
                        'success' => 'text-emerald-700 dark:text-emerald-300',
                        'warning' => 'text-amber-700 dark:text-amber-300',
                        'danger' => 'text-red-700 dark:text-red-300',
                        'info' => 'text-blue-700 dark:text-blue-300',
                        default => 'text-primary-700 dark:text-primary-300',
                    };
                @endphp
                <div class="flex items-start gap-3 rounded-lg border-l-4 px-3.5 py-3 {{ $insightClasses }}">
                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-current {{ $insightTextClasses }}" aria-hidden="true"></span>
                    <p class="text-[13px] leading-5 text-gray-700 dark:text-gray-200">
                        <span class="font-bold {{ $insightTextClasses }}">{{ $insight['highlight'] }}</span>
                        {{ $insight['text'] }}
                    </p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if (!empty($topStories))
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 bg-gray-50 px-6 pb-2.5 pt-3.5 dark:border-gray-800 dark:bg-gray-800/60">
                <p class="mb-0.5 text-[15px] font-semibold text-gray-950 dark:text-white">Top Stories</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Ranked by game starts for {{ $filterSummary['date'] }}</p>
            </div>
            <div class="flex flex-col gap-3 px-6 py-4">
                @foreach ($topStories as $rank => $story)
                @php
                    $rankBadgeClasses = match ($rank) {
                        0 => 'bg-primary-600 dark:bg-primary-400',
                        1 => 'bg-primary-500 dark:bg-primary-500',
                        default => 'bg-gray-300 dark:bg-gray-600',
                    };
                    $rankBarColor = match ($rank) {
                        0 => '#6366f1',
                        1 => '#8b5cf6',
                        default => '#a5b4fc',
                    };
                @endphp
                <div class="flex items-center gap-3">
                    <span class="flex size-[22px] shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white {{ $rankBadgeClasses }}">{{ $rank + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="mb-1 truncate text-[13px] font-semibold text-gray-950 dark:text-white">{{ $story['title'] }}</p>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-full rounded-full" style="background:{{ $rankBarColor }}; width:{{ $story['pct'] }}%;"></div>
                        </div>
                    </div>
                    <span class="w-[65px] shrink-0 text-right text-[13px] font-bold text-gray-700 dark:text-gray-200">{{ $story['starts'] }} start{{ $story['starts'] !== 1 ? 's' : '' }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
    @endif

    {{-- ── Retention ────────────────────────────────────────────────── --}}
    @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget::class, ['pageFilters' => $analyticsFilters], key('analytics-retention-'.$filterKey))

    {{-- ── Metric Glossary ─────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 bg-gray-50 px-6 pb-2.5 pt-3.5 dark:border-gray-800 dark:bg-gray-800/60">
            <p class="mb-0.5 text-[15px] font-semibold text-gray-950 dark:text-white">Metric Glossary</p>
            <p class="text-[13px] text-gray-500 dark:text-gray-400">Precise definitions for every number on this page — read before interpreting funnel percentages.</p>
        </div>
        <div class="px-6 py-5">
            <div class="grid grid-cols-1 md:grid-cols-2">
                @foreach ($defs as $i => $def)
                <div class="flex items-baseline gap-2 border-gray-100 px-4 py-3 dark:border-gray-800 {{ $i >= 2 ? 'border-t' : '' }} {{ $i % 2 === 1 ? 'md:border-l' : '' }}">
                    <span class="shrink-0 whitespace-nowrap text-[13px] font-bold text-gray-900 dark:text-white">{{ $def[0] }}</span>
                    <span class="text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $def[1] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</x-filament-panels::page>
