@php
    /** @var \App\Models\PlayerTokenUsage $usage */
    $number = static fn (mixed $value): string => number_format(is_numeric($value) ? (int) $value : 0);
    $usd = static function (mixed $value, int $decimals = 9): string {
        $formatted = number_format(is_numeric($value) ? (float) $value : 0.0, $decimals, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return '$'.($trimmed === '' ? '0' : $trimmed);
    };
    $rate = static function (mixed $value): string {
        $formatted = number_format(is_numeric($value) ? (float) $value : 0.0, 6, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return '$'.($trimmed === '' ? '0' : $trimmed);
    };

    $requestType = match ($usage->kind) {
        \App\Models\PlayerTokenUsage::KIND_BEGIN => 'Opening',
        \App\Models\PlayerTokenUsage::KIND_NEXT_SESSION => 'Next session',
        default => 'Player turn',
    };

    $inputTokens = (int) $usage->input_tokens;
    $cacheReadTokens = (int) $usage->cache_read_input_tokens;
    $cacheWriteTokens = (int) $usage->cache_write_input_tokens;
    $cachedTokens = (int) $usage->cached_input_tokens;
    $outputTokens = (int) $usage->output_tokens;
    $totalTokens = (int) $usage->total_tokens;
    $compositionTotal = max(1, $inputTokens + $cachedTokens + $outputTokens);
    $inputPercent = ($inputTokens / $compositionTotal) * 100;
    $cachedPercent = ($cachedTokens / $compositionTotal) * 100;
    $outputPercent = ($outputTokens / $compositionTotal) * 100;

    $isLegacyGameplay = $usage->playable_moment_id === null && blank($usage->moment_key);
    $chapters = $isLegacyGameplay
        ? collect()
        : $usage->chapters->sortBy('position')->values();
    $chapterPositions = $chapters->pluck('position', 'id');
    $events = $isLegacyGameplay
        ? collect()
        : $usage->events
            ->sortBy(static fn ($event): string => str_pad(
                (string) ($chapterPositions->get($event->chapter_id, 999999)),
                6,
                '0',
                STR_PAD_LEFT,
            ).':'.str_pad((string) ($event->position ?? 0), 6, '0', STR_PAD_LEFT))
            ->values();
    $eventPreviewLimit = 6;
    $eventPreview = $events->take($eventPreviewLimit);
    $remainingEvents = $events->slice($eventPreviewLimit)->values();

    $systemPrompt = is_string($usage->system_prompt) ? $usage->system_prompt : null;
    $generatedOutput = is_string($usage->generated_output) ? $usage->generated_output : null;
    $systemPromptLength = $systemPrompt === null ? 0 : mb_strlen($systemPrompt);
    $generatedOutputLength = $generatedOutput === null ? 0 : mb_strlen($generatedOutput);

    $identifiers = [
        ['label' => 'Game ID', 'value' => $usage->game_id],
        ['label' => 'Prompt reference', 'value' => $usage->prompt_reference_id],
        ['label' => 'Source prompt', 'value' => $usage->source_prompt_reference_id],
    ];

    $costRows = [
        [
            'label' => 'Input',
            'tokens' => $inputTokens,
            'rate' => $usage->input_price_per_million_usd,
            'cost' => $usage->input_cost_usd,
            'dot' => 'bg-primary-500',
        ],
        [
            'label' => 'Cache read',
            'tokens' => $cacheReadTokens,
            'rate' => $usage->cache_read_price_per_million_usd,
            'cost' => $usage->cache_read_cost_usd,
            'dot' => 'bg-info-500',
        ],
        [
            'label' => 'Cache write',
            'tokens' => $cacheWriteTokens,
            'rate' => $usage->cache_write_price_per_million_usd,
            'cost' => $usage->cache_write_cost_usd,
            'dot' => 'bg-warning-500',
        ],
        [
            'label' => 'Output',
            'tokens' => $outputTokens,
            'rate' => $usage->output_price_per_million_usd,
            'cost' => $usage->output_cost_usd,
            'dot' => 'bg-success-500',
        ],
    ];

    $pricingSource = is_string($usage->pricing_source)
        && filter_var($usage->pricing_source, FILTER_VALIDATE_URL)
            ? $usage->pricing_source
            : null;
    $rawUsage = json_encode(
        $usage->raw_usage,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) ?: '{}';
@endphp

<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-500 via-violet-400 to-info-400"></div>

        <div class="p-5 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">
                            {{ $requestType }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                            {{ $usage->model }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium uppercase tracking-wide text-gray-600 ring-1 ring-inset ring-gray-600/10 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                            {{ $usage->provider }}
                        </span>
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                            'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20' => $usage->usage_reported,
                            'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/20' => ! $usage->usage_reported,
                        ])>
                            <span @class([
                                'size-1.5 rounded-full',
                                'bg-success-500' => $usage->usage_reported,
                                'bg-danger-500' => ! $usage->usage_reported,
                            ])></span>
                            {{ $usage->usage_reported ? 'Usage reported' : 'Usage missing' }}
                        </span>
                    </div>

                    <h2 class="mt-3 text-lg font-semibold text-gray-950 dark:text-white">
                        Request overview
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Player-runtime usage captured
                        {{ $usage->occurred_at->format('M j, Y') }} at
                        {{ $usage->occurred_at->format('H:i:s') }}.
                    </p>
                </div>

                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-clock" class="size-4" />
                    <span>Cycle {{ $usage->story_cycle_number }} · Session {{ $usage->session_number }}</span>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-primary-200 bg-primary-50/80 p-4 dark:border-primary-400/20 dark:bg-primary-400/10">
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Total cost</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-semibold tracking-tight text-primary-950 tabular-nums dark:text-primary-100">
                        {{ $usd($usage->total_cost_usd) }}
                    </p>
                    <p class="mt-1 text-xs text-primary-700/70 dark:text-primary-300/70">Stored pricing snapshot</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total tokens</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $number($totalTokens) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Input + cached + output</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Input</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $number($inputTokens) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Standard input tokens</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cached input</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $number($cachedTokens) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $number($cacheReadTokens) }} read · {{ $number($cacheWriteTokens) }} write</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Output</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $number($outputTokens) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $number($usage->reasoning_tokens) }} reasoning tokens</p>
                </div>
            </div>

            <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-white/10 dark:bg-black/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Token composition</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Hover-free, proportional view</p>
                </div>
                <div
                    class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"
                    role="img"
                    aria-label="Token composition: {{ $number($inputTokens) }} input, {{ $number($cachedTokens) }} cached input, and {{ $number($outputTokens) }} output tokens"
                >
                    <span class="bg-primary-500" style="width: {{ $inputPercent }}%"></span>
                    <span class="bg-info-500" style="width: {{ $cachedPercent }}%"></span>
                    <span class="bg-success-500" style="width: {{ $outputPercent }}%"></span>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600 dark:text-gray-300">
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-primary-500"></span>Input {{ number_format($inputPercent, 1) }}%</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-info-500"></span>Cached {{ number_format($cachedPercent, 1) }}%</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-success-500"></span>Output {{ number_format($outputPercent, 1) }}%</span>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-12">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6 xl:col-span-7">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-book-open" class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gameplay context</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $usage->story->title }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Where this request occurred in the player's journey.</p>
                </div>
            </div>

            <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-user" class="size-4" /> Player
                    </dt>
                    <dd class="mt-2 truncate text-sm font-medium text-gray-900 dark:text-white" title="{{ $usage->user->email }}">{{ $usage->user->email }}</dd>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="size-4" /> Story cycle
                    </dt>
                    <dd class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Cycle {{ $usage->story_cycle_number }}</dd>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-queue-list" class="size-4" /> Session
                    </dt>
                    <dd class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Session {{ $usage->session_number }}</dd>
                </div>
            </dl>

            <div class="mt-3 flex items-center gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-map-pin" class="size-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Playable moment</p>
                    <p class="mt-0.5 truncate text-sm font-medium text-gray-900 dark:text-white">
                        {{ filled($usage->moment_key) ? $usage->moment_key : 'Legacy gameplay · no moment key' }}
                    </p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6 xl:col-span-5">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-o-finger-print" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Request identity</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">References</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Copy an identifier without selecting wrapped text.</p>
                </div>
            </div>

            <dl class="mt-6 divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/10">
                @foreach ($identifiers as $identifier)
                    <div class="p-4">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $identifier['label'] }}</dt>
                        @if (filled($identifier['value']))
                            <dd class="mt-1.5 flex items-center gap-2">
                                <code class="min-w-0 flex-1 break-all text-xs font-medium text-gray-800 dark:text-gray-200">{{ $identifier['value'] }}</code>
                                <button
                                    type="button"
                                    class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-primary-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:hover:bg-white/5 dark:hover:text-primary-300"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText(@js($identifier['value'])); copied = true; setTimeout(() => copied = false, 1600)"
                                    aria-label="Copy {{ $identifier['label'] }}"
                                >
                                    <x-filament::icon icon="heroicon-o-clipboard-document" class="size-4" x-show="! copied" />
                                    <x-filament::icon icon="heroicon-o-check" class="size-4 text-success-500" x-show="copied" x-cloak />
                                </button>
                            </dd>
                        @else
                            <dd class="mt-1.5 text-sm text-gray-400 dark:text-gray-500">Not linked</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-info-50 text-info-600 dark:bg-info-400/10 dark:text-info-300">
                    <x-filament::icon icon="heroicon-o-map" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Story attribution</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Chapters and source events</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $isLegacyGameplay
                            ? 'Legacy gameplay has no precise playable-moment cursor.'
                            : 'Only the authored material linked to this playable moment.' }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($isLegacyGameplay)
                    <span class="rounded-full bg-warning-50 px-2.5 py-1 text-xs font-semibold text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/20">Session-level only</span>
                @else
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $number($chapters->count()) }} chapters</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $number($events->count()) }} events</span>
                @endif
            </div>
        </div>

        @if ($isLegacyGameplay)
            <div class="mt-6 flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50/70 p-4 dark:border-warning-400/20 dark:bg-warning-400/10">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-warning-600 shadow-sm dark:bg-white/5 dark:text-warning-300">
                    <x-filament::icon icon="heroicon-o-information-circle" class="size-5" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-warning-900 dark:text-warning-100">Exact event attribution is unavailable</h3>
                    <p class="mt-1 text-sm leading-6 text-warning-800/80 dark:text-warning-200/80">
                        This request is intentionally included in game, player, and session totals only. Assigning it to every event would overstate event and chapter consumption.
                    </p>
                </div>
            </div>
        @else
            <div class="mt-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Chapters</h3>
                @if ($chapters->isEmpty())
                    <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">No chapter attribution recorded.</p>
                @else
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($chapters as $chapter)
                            <span class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-white/10 dark:bg-white/[0.035] dark:text-gray-200">
                                <span class="flex size-5 items-center justify-center rounded-md bg-white text-[10px] font-semibold text-gray-500 shadow-sm dark:bg-white/5 dark:text-gray-300">{{ $chapter->position }}</span>
                                {{ $chapter->title }}
                                @if ($chapter->pivot?->attribution_role === 'anchor')
                                    <span class="rounded bg-primary-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">Anchor</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-6 border-t border-gray-200 pt-6 dark:border-white/10">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Event preview</h3>
                @if ($events->isEmpty())
                    <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">No event attribution recorded.</p>
                @else
                    <ol class="mt-3 grid gap-2 lg:grid-cols-2">
                        @foreach ($eventPreview as $event)
                            <li class="flex items-start gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-white/10">
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md bg-gray-100 text-[10px] font-semibold text-gray-500 dark:bg-white/5 dark:text-gray-300">{{ $loop->iteration }}</span>
                                <span class="min-w-0 flex-1 text-sm text-gray-700 dark:text-gray-200">{{ $event->title }}</span>
                                @if ($event->pivot?->attribution_role === 'anchor')
                                    <span class="mt-0.5 size-2 shrink-0 rounded-full bg-primary-500" title="Anchor event"></span>
                                @endif
                            </li>
                        @endforeach
                    </ol>

                    @if ($remainingEvents->isNotEmpty())
                        <details class="group mt-3 rounded-xl border border-gray-200 dark:border-white/10">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-white/[0.035] [&::-webkit-details-marker]:hidden">
                                <span>Show remaining {{ $number($remainingEvents->count()) }} events</span>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 text-gray-400 transition-transform group-open:rotate-180" />
                            </summary>
                            <ol class="grid gap-2 border-t border-gray-200 p-3 dark:border-white/10 lg:grid-cols-2">
                                @foreach ($remainingEvents as $event)
                                    <li class="flex items-start gap-3 rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-white/[0.035]">
                                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md bg-white text-[10px] font-semibold text-gray-500 shadow-sm dark:bg-white/5 dark:text-gray-300">{{ $eventPreviewLimit + $loop->iteration }}</span>
                                        <span class="min-w-0 flex-1 text-sm text-gray-700 dark:text-gray-200">{{ $event->title }}</span>
                                        @if ($event->pivot?->attribution_role === 'anchor')
                                            <span class="mt-0.5 size-2 shrink-0 rounded-full bg-primary-500" title="Anchor event"></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                    @endif
                @endif
            </div>
        @endif
    </section>

    <details class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:hover:bg-white/[0.035] sm:p-6 [&::-webkit-details-marker]:hidden">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300">
                    <x-filament::icon icon="heroicon-o-command-line" class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Generation snapshot</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">System prompt and output</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Exact gameplay content stored with this usage record.</p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <div class="hidden gap-2 lg:flex">
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $number($systemPromptLength) }} prompt chars</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $number($generatedOutputLength) }} output chars</span>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-down" class="size-5 text-gray-400 transition-transform group-open:rotate-180" />
            </div>
        </summary>

        <div class="grid gap-4 border-t border-gray-200 p-5 dark:border-white/10 sm:p-6 xl:grid-cols-2">
            <article class="min-w-0 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <header class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.035]">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">System prompt</h3>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                        'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-300' => filled($systemPrompt),
                        'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => blank($systemPrompt),
                    ])>{{ filled($systemPrompt) ? 'Captured' : 'Unavailable' }}</span>
                </header>
                @if (filled($systemPrompt))
                    <pre class="max-h-[32rem] overflow-auto whitespace-pre-wrap break-words p-4 font-mono text-xs leading-6 text-gray-700 dark:text-gray-200">{{ $systemPrompt }}</pre>
                @else
                    <p class="p-4 text-sm leading-6 text-gray-500 dark:text-gray-400">This historical record was created before system-prompt snapshots were enabled.</p>
                @endif
            </article>

            <article class="min-w-0 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <header class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.035]">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Generated output</h3>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                        'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-300' => filled($generatedOutput),
                        'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => blank($generatedOutput),
                    ])>{{ filled($generatedOutput) ? 'Captured' : 'Unavailable' }}</span>
                </header>
                @if (filled($generatedOutput))
                    <pre class="max-h-[32rem] overflow-auto whitespace-pre-wrap break-words p-4 font-mono text-xs leading-6 text-gray-700 dark:text-gray-200">{{ $generatedOutput }}</pre>
                @else
                    <p class="p-4 text-sm leading-6 text-gray-500 dark:text-gray-400">No generated output snapshot is available for this record.</p>
                @endif
            </article>
        </div>
    </details>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-300">
                    <x-filament::icon icon="heroicon-o-currency-dollar" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cost calculation</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Stored price breakdown</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Calculated with the model pricing active when this request occurred.</p>
                </div>
            </div>
            <div class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                {{ $usage->pricing_version }}
            </div>
        </div>

        <div class="mt-6 grid gap-3 lg:grid-cols-2">
            @foreach ($costRows as $row)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2.5">
                            <span class="size-2.5 rounded-full {{ $row['dot'] }}"></span>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $row['label'] }}</p>
                        </div>
                        <p class="whitespace-nowrap text-sm font-semibold text-gray-950 tabular-nums dark:text-white">{{ $usd($row['cost'], 12) }}</p>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $number($row['tokens']) }} tokens</span>
                        <span>{{ $rate($row['rate']) }} / MTok</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex flex-col gap-2 rounded-xl bg-gray-950 px-4 py-4 text-white sm:flex-row sm:items-center sm:justify-between dark:bg-black/30">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Total recorded cost</p>
                <p class="mt-1 text-xs text-gray-400">{{ $number($totalTokens) }} total tokens · {{ $usage->currency }}</p>
            </div>
            <p class="whitespace-nowrap text-2xl font-semibold tracking-tight tabular-nums">{{ $usd($usage->total_cost_usd, 12) }}</p>
        </div>

        <details class="group mt-4 rounded-xl border border-gray-200 dark:border-white/10">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-white/[0.035] [&::-webkit-details-marker]:hidden">
                <span class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-code-bracket-square" class="size-4 text-gray-400" />
                    Pricing metadata and provider payload
                </span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 text-gray-400 transition-transform group-open:rotate-180" />
            </summary>

            <div class="border-t border-gray-200 p-4 dark:border-white/10">
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Pricing version</dt>
                        <dd class="mt-1 break-all text-sm text-gray-900 dark:text-white">{{ $usage->pricing_version }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Effective from</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $usage->pricing_effective_from?->format('M j, Y H:i') ?? 'Not specified' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Official pricing source</dt>
                        <dd class="mt-1 text-sm">
                            @if ($pricingSource)
                                <a href="{{ $pricingSource }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    Open source
                                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-3.5" />
                                </a>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">Not recorded</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Provider usage payload</h3>
                        <span class="text-xs text-gray-400 dark:text-gray-500">Read-only</span>
                    </div>
                    <pre class="mt-2 max-h-80 overflow-auto rounded-xl bg-gray-950 p-4 text-xs leading-5 text-gray-200 dark:bg-black/40"><code>{{ $rawUsage }}</code></pre>
                </div>
            </div>
        </details>
    </section>
</div>
