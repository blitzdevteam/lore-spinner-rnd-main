<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Voice pricing catalog</x-slot>
        <x-slot name="description">Public pay-as-you-go reference rates used for new player narration estimates. Every recorded request keeps its own price snapshot.</x-slot>

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($pricingGroups as $provider => $rows)
                <article class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                    <header class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50/80 px-4 py-3 dark:border-white/10 dark:bg-white/[0.035]">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-300">
                                <x-filament::icon icon="heroicon-o-speaker-wave" class="size-4" />
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $rows[0]['provider_label'] }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ count($rows) }} priced model {{ Str::plural('family', count($rows)) }}</p>
                            </div>
                        </div>

                        @if (collect($rows)->contains('is_active', true))
                            <span class="rounded-full bg-success-50 px-2 py-1 text-xs font-semibold text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20">Active provider</span>
                        @endif
                    </header>

                    <div class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($rows as $row)
                            <div class="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $row['model_label'] }}</p>
                                        @if ($row['is_active'])
                                            <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[0.6875rem] font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">Configured</span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 break-all font-mono text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $row['model'] }}</p>
                                </div>

                                <div class="sm:text-right">
                                    <p class="whitespace-nowrap text-sm font-semibold text-gray-950 tabular-nums dark:text-white">${{ $row['rate_per_thousand'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">per 1K {{ $row['billing_unit'] }}</p>
                                </div>

                                <div class="flex items-center justify-between gap-3 sm:block sm:min-w-24 sm:text-right">
                                    <div>
                                        <p class="whitespace-nowrap text-sm font-medium text-gray-700 tabular-nums dark:text-gray-200">${{ $row['rate_per_million'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">per 1M</p>
                                    </div>
                                    @if (filter_var($row['source'], FILTER_VALIDATE_URL))
                                        <a href="{{ $row['source'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-300">
                                            Source
                                            <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-3" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4 flex items-start gap-2 rounded-xl bg-warning-50 px-4 py-3 text-xs leading-5 text-warning-800 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-200 dark:ring-warning-400/20">
            <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-4 shrink-0" />
            <p>
                These are public PAYG estimates, not invoice totals. Both providers bill TTS by character. The ElevenLabs response reports the characters used for billing, including any custom-voice multiplier; Deepgram reports input characters. The ledger preserves that quantity and the exact configured price version for auditability.
            </p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
