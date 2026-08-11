@php
    /** @var \App\Models\PlayerVoiceUsage $usage */
    $number = static fn (mixed $value): string => number_format(is_numeric($value) ? (int) $value : 0);
    $usd = static function (mixed $value, int $decimals = 9): string {
        if (! is_numeric($value)) {
            return 'Unpriced';
        }

        $formatted = number_format((float) $value, $decimals, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return '$'.($trimmed === '' ? '0' : $trimmed);
    };
    $bytes = static function (mixed $value): string {
        $value = is_numeric($value) ? max(0, (int) $value) : 0;

        return match (true) {
            $value >= 1_048_576 => number_format($value / 1_048_576, 2).' MB',
            $value >= 1_024 => number_format($value / 1_024, 1).' KB',
            default => number_format($value).' B',
        };
    };

    $providerName = match (mb_strtolower($usage->provider)) {
        'elevenlabs' => 'ElevenLabs',
        'deepgram' => 'Deepgram',
        default => str($usage->provider)->headline()->toString(),
    };
    $unitName = str($usage->billing_unit)->headline()->toString();
    $usageSource = data_get($usage->provider_metadata, 'usage_source');
    $providerReported = $usageSource === 'provider_header';
    $pricingSource = is_string($usage->pricing_source)
        && filter_var($usage->pricing_source, FILTER_VALIDATE_URL)
            ? $usage->pricing_source
            : null;
    $providerMetadata = json_encode(
        $usage->provider_metadata,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) ?: '{}';
    $identifiers = [
        ['label' => 'Game ID', 'value' => $usage->game_id],
        ['label' => 'Prompt reference', 'value' => $usage->prompt_reference_id],
        ['label' => 'Provider request', 'value' => $usage->provider_request_id],
        ['label' => 'Provider trace', 'value' => $usage->provider_trace_id],
    ];
@endphp

<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-500 via-violet-400 to-info-400"></div>

        <div class="p-5 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">
                            {{ $providerName }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                            {{ $usage->model }}
                        </span>
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                            'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20' => $providerReported,
                            'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/20' => ! $providerReported,
                        ])>
                            <span @class([
                                'size-1.5 rounded-full',
                                'bg-success-500' => $providerReported,
                                'bg-warning-500' => ! $providerReported,
                            ])></span>
                            {{ $providerReported ? 'Provider-reported units' : 'Character fallback' }}
                        </span>
                    </div>

                    <h2 class="mt-3 text-lg font-semibold text-gray-950 dark:text-white">Narration generation</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Successful player-side voice request captured {{ $usage->occurred_at->format('M j, Y') }} at {{ $usage->occurred_at->format('H:i:s') }}.
                    </p>
                </div>

                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-clock" class="size-4" />
                    <span>Cycle {{ $usage->story_cycle_number }} · Session {{ $usage->session_number }}</span>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-primary-200 bg-primary-50/80 p-4 dark:border-primary-400/20 dark:bg-primary-400/10">
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Estimated cost</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-semibold tracking-tight text-primary-950 tabular-nums dark:text-primary-100">{{ $usd($usage->estimated_cost_usd) }}</p>
                    <p class="mt-1 text-xs text-primary-700/70 dark:text-primary-300/70">Public PAYG snapshot</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Billable units</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $number($usage->billable_units) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $unitName }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Input text</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $number($usage->input_characters) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Characters sent</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Provider latency</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $number($usage->latency_ms) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Milliseconds</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Audio output</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $bytes($usage->audio_bytes) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $usage->output_format }}</p>
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
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gameplay context</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $usage->story->title }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Where this narration audio was generated.</p>
                </div>
            </div>

            <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Player</dt>
                    <dd class="mt-2 truncate text-sm font-medium text-gray-900 dark:text-white" title="{{ $usage->user->email }}">{{ $usage->user->email }}</dd>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Story cycle</dt>
                    <dd class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Cycle {{ $usage->story_cycle_number }}</dd>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Session</dt>
                    <dd class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Session {{ $usage->session_number }}</dd>
                </div>
            </dl>

            <div class="mt-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Voice</p>
                <p class="mt-1.5 break-all font-mono text-xs font-medium text-gray-800 dark:text-gray-200">{{ filled($usage->voice_id) ? $usage->voice_id : 'Provider default' }}</p>
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
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Internal and provider identifiers for support and reconciliation.</p>
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
                            <dd class="mt-1.5 text-sm text-gray-400 dark:text-gray-500">Not reported</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-300">
                    <x-filament::icon icon="heroicon-o-currency-dollar" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pricing snapshot</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Stored cost estimate</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Historical rates remain unchanged when provider pricing changes later.</p>
                </div>
            </div>
            <span @class([
                'w-fit rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20' => $usage->pricing_available,
                'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/20' => ! $usage->pricing_available,
            ])>{{ $usage->pricing_available ? 'Pricing available' : 'Pricing missing' }}</span>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Billable quantity</p>
                <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $number($usage->billable_units) }} {{ $usage->billing_unit }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Rate / 1K units</p>
                <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $usd($usage->rate_per_thousand_usd, 6) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Estimated cost</p>
                <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $usd($usage->estimated_cost_usd, 12) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pricing version</p>
                <p class="mt-2 break-all text-sm font-semibold text-gray-950 dark:text-white">{{ $usage->pricing_version ?? 'Not configured' }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
            <span>Effective {{ $usage->pricing_effective_from?->format('M j, Y') ?? 'from the start of tracking' }}</span>
            @if ($pricingSource !== null)
                <a href="{{ $pricingSource }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-500 dark:text-primary-300">
                    Official pricing source
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-3.5" />
                </a>
            @endif
        </div>
    </section>

    <details class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:hover:bg-white/[0.035] sm:p-6 [&::-webkit-details-marker]:hidden">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300">
                    <x-filament::icon icon="heroicon-o-code-bracket-square" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Audit payload</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Narration text and provider metadata</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Collapsed by default to keep the billing view focused.</p>
                </div>
            </div>
            <x-filament::icon icon="heroicon-m-chevron-down" class="size-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180" />
        </summary>

        <div class="grid gap-4 border-t border-gray-200 p-5 dark:border-white/10 sm:p-6 xl:grid-cols-2">
            <article class="min-w-0 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <header class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.035]">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Text sent to voice provider</h3>
                </header>
                <pre class="max-h-[32rem] overflow-auto whitespace-pre-wrap break-words p-4 font-mono text-xs leading-6 text-gray-700 dark:text-gray-200">{{ $usage->text_snapshot }}</pre>
            </article>
            <article class="min-w-0 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <header class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.035]">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Provider metadata</h3>
                </header>
                <pre class="max-h-[32rem] overflow-auto whitespace-pre-wrap break-words p-4 font-mono text-xs leading-6 text-gray-700 dark:text-gray-200">{{ $providerMetadata }}</pre>
            </article>
        </div>
    </details>
</div>
