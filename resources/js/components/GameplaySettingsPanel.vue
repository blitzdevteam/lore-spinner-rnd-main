<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { GameplayUsageSummary, SubscriptionUsageSummary } from '@/types';
import { glassTintVars } from '@/utils/color';
import { router } from '@inertiajs/vue3';
import { LucideExternalLink, LucideMinus, LucidePlus, LucideRefreshCw, LucideRotateCcw, LucideZap } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        gameId?: string;
        usage?: GameplayUsageSummary;
        subscriptionUsage?: SubscriptionUsageSummary | null;
        variant?: 'sidebar' | 'sheet';
    }>(),
    { variant: 'sidebar' },
);

const { settings, fontColorPresets, backgroundPresets, reset } = useGameplaySettings();
const tts = useTextToSpeech();

const usage = computed<GameplayUsageSummary>(
    () =>
        props.usage ?? {
            game: {
                turns: 0,
                voice_duration_ms: 0,
            },
            total: {
                turns: 0,
                voice_duration_ms: 0,
            },
        },
);

const subscriptionUsage = computed<SubscriptionUsageSummary | null>(() => props.subscriptionUsage ?? null);

const glassPreviewStyle = computed(() => glassTintVars(settings.backgroundColor));

const toggleAutoplay = () => {
    tts.primeAudio();
    settings.autoplay = !settings.autoplay;
};

const clampFontSize = (val: number) => Math.min(28, Math.max(12, val));

const formatUsageCount = (value: number) => Math.max(0, value).toLocaleString();

const formatVoiceDuration = (milliseconds: number) => {
    const totalSeconds = Math.max(0, Math.round(milliseconds / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return seconds === 0 ? `${minutes}m` : `${minutes}m ${seconds}s`;
};

const formatRenewalDate = (value: string) => {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? 'Resets at the end of your billing period'
        : `Resets ${date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`;
};

const isResetting = ref(false);

const handleResetGame = () => {
    if (!props.gameId || isResetting.value) return;
    const confirmed = window.confirm(
        'Start a fresh story? This will erase all your current progress, including every prompt and choice you have made.',
    );
    if (!confirmed) return;

    isResetting.value = true;
    tts.resetForNewStory();
    router.post(
        `/user/games/${props.gameId}/reset`,
        {},
        {
            preserveScroll: false,
            preserveState: false,
            onFinish: () => {
                isResetting.value = false;
            },
        },
    );
};

const volumePercent = () => {
    if (tts.isMuted.value || tts.volume.value === 0) return 0;
    return Math.round(tts.volume.value * 100);
};

const onVolumeInput = (event: Event) => {
    tts.setVolume(Number((event.target as HTMLInputElement).value) / 100);
};
</script>

<template>
    <!-- Desktop sidebar: classic layout -->
    <div v-if="variant === 'sidebar'" class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-100">Settings</h3>
            <BaseButton severity="transparent" :icon-only="true" class="size-8!" @click="reset">
                <LucideRotateCcw :size="16" />
            </BaseButton>
        </div>

        <div class="flex flex-col gap-2.5">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Font Size</label>
            <div class="flex items-center gap-3">
                <BaseButton
                    severity="muted"
                    :icon-only="true"
                    class="size-9!"
                    :disabled="settings.fontSize <= 12"
                    @click="settings.fontSize = clampFontSize(settings.fontSize - 1)"
                >
                    <LucideMinus :size="14" />
                </BaseButton>
                <div class="flex-1">
                    <input
                        type="range"
                        :min="12"
                        :max="28"
                        :value="settings.fontSize"
                        class="gameplay-range w-full"
                        @input="settings.fontSize = clampFontSize(Number(($event.target as HTMLInputElement).value))"
                    />
                </div>
                <BaseButton
                    severity="muted"
                    :icon-only="true"
                    class="size-9!"
                    :disabled="settings.fontSize >= 28"
                    @click="settings.fontSize = clampFontSize(settings.fontSize + 1)"
                >
                    <LucidePlus :size="14" />
                </BaseButton>
                <span class="w-9 text-center text-sm text-gray-300">{{ settings.fontSize }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-2.5">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Font Color</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="preset in fontColorPresets"
                    :key="preset.value"
                    type="button"
                    :class="[
                        'flex h-9 items-center gap-2 rounded-lg border px-3 transition-all',
                        settings.fontColor === preset.value
                            ? 'border-primary-400 bg-primary-400/10'
                            : 'border-gray-700 hover:border-gray-500',
                    ]"
                    @click="settings.fontColor = preset.value"
                >
                    <span class="size-3.5 rounded-full border border-gray-600" :style="{ backgroundColor: preset.value }" />
                    <span class="text-xs text-gray-300">{{ preset.label }}</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="color"
                    :value="settings.fontColor"
                    class="size-8 cursor-pointer rounded border-none bg-transparent"
                    @input="settings.fontColor = ($event.target as HTMLInputElement).value"
                />
                <span class="text-xs text-gray-500">Custom color</span>
            </div>
        </div>

        <div class="flex flex-col gap-2.5">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Panel tint</label>
            <p class="text-xs text-gray-500">Tints narration and choice glass panels.</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="preset in backgroundPresets"
                    :key="preset.value"
                    type="button"
                    :class="[
                        'flex h-9 items-center gap-2 rounded-lg border px-3 transition-all',
                        settings.backgroundColor === preset.value
                            ? 'border-primary-400 bg-primary-400/10'
                            : 'border-gray-700 hover:border-gray-500',
                    ]"
                    @click="settings.backgroundColor = preset.value"
                >
                    <span
                        v-if="preset.value"
                        class="size-3.5 rounded-full border border-gray-600"
                        :style="{ backgroundColor: preset.value }"
                    />
                    <span v-else class="size-3.5 rounded-full border border-dashed border-gray-500" />
                    <span class="text-xs text-gray-300">{{ preset.label }}</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="color"
                    :value="settings.backgroundColor || '#1a1a1a'"
                    class="size-8 cursor-pointer rounded border-none bg-transparent"
                    @input="settings.backgroundColor = ($event.target as HTMLInputElement).value"
                />
                <span class="text-xs text-gray-500">Custom color</span>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Preview</label>
            <div class="settings-glass-preview rounded-xl border border-white/10 p-4" :style="glassPreviewStyle">
                <p
                    class="font-light leading-relaxed"
                    :style="{ fontSize: settings.fontSize + 'px', color: settings.fontColor }"
                >
                    The ancient door creaked open, revealing a corridor bathed in moonlight...
                </p>
            </div>
        </div>

        <div v-if="gameId" class="flex flex-col gap-3 border-t border-gray-700/40 pt-6">
            <div v-if="subscriptionUsage" class="rounded-xl border border-primary-400/20 bg-primary-400/5 p-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-primary-200">Plan allowance</label>
                        <p class="mt-1 text-sm font-medium text-gray-100">{{ subscriptionUsage.plan.name }}</p>
                    </div>
                    <span class="text-right text-xs leading-relaxed text-gray-400">{{ formatRenewalDate(subscriptionUsage.period_ends_at) }}</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2.5">
                    <div class="rounded-lg border border-white/8 bg-black/10 p-2.5">
                        <span class="block text-xs text-gray-400">Actions left</span>
                        <strong class="mt-1 block text-base font-semibold tabular-nums text-primary-100">
                            {{ formatUsageCount(subscriptionUsage.actions.remaining) }} / {{ formatUsageCount(subscriptionUsage.actions.limit) }}
                        </strong>
                    </div>
                    <div class="rounded-lg border border-white/8 bg-black/10 p-2.5">
                        <span class="block text-xs text-gray-400">Voice left</span>
                        <strong class="mt-1 block text-base font-semibold tabular-nums text-primary-100">
                            {{ formatVoiceDuration(subscriptionUsage.voice.remaining_milliseconds) }}
                        </strong>
                        <span class="mt-0.5 block text-[0.6875rem] text-gray-500">of {{ subscriptionUsage.voice.limit_minutes }}m</span>
                    </div>
                </div>
            </div>
            <div>
                <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Usage</label>
                <p class="mt-1 text-xs text-gray-500">This game</p>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                <div class="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                    <span class="block text-xs text-gray-400">Turns played</span>
                    <strong class="mt-1 block text-lg font-semibold tabular-nums text-gray-100">
                        {{ formatUsageCount(usage.game.turns) }}
                    </strong>
                    <span class="mt-1 block text-xs text-gray-500">Completed moves</span>
                </div>
                <div class="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                    <span class="block text-xs text-gray-400">Voice generated</span>
                    <strong class="mt-1 block text-lg font-semibold tabular-nums text-gray-100">
                        {{ formatVoiceDuration(usage.game.voice_duration_ms) }}
                    </strong>
                    <span class="mt-1 block text-xs text-gray-500">Narration time</span>
                </div>
            </div>
            <div class="border-t border-gray-700/40 pt-4">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Total (all games)</p>
                <div class="mt-2 grid grid-cols-2 gap-2.5">
                    <div class="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                        <span class="block text-xs text-gray-400">Turns played</span>
                        <strong class="mt-1 block text-lg font-semibold tabular-nums text-gray-100">
                            {{ formatUsageCount(usage.total.turns) }}
                        </strong>
                        <span class="mt-1 block text-xs text-gray-500">Across your games</span>
                    </div>
                    <div class="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                        <span class="block text-xs text-gray-400">Voice generated</span>
                        <strong class="mt-1 block text-lg font-semibold tabular-nums text-gray-100">
                            {{ formatVoiceDuration(usage.total.voice_duration_ms) }}
                        </strong>
                        <span class="mt-1 block text-xs text-gray-500">Across your games</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="gameId" class="flex flex-col gap-2.5 border-t border-gray-700/40 pt-6">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Story</label>
            <BaseButton severity="gray-muted" class="w-full" :disabled="isResetting" @click="handleResetGame">
                <LucideRefreshCw :size="16" :class="{ 'animate-spin': isResetting }" />
                <span>{{ isResetting ? 'Resetting...' : 'Start a fresh story' }}</span>
            </BaseButton>
            <p class="text-xs leading-relaxed text-gray-500">
                Erases all progress for this story and begins a new playthrough from the start.
            </p>
        </div>
    </div>

    <!-- Mobile bottom sheet: sectioned layout -->
    <div v-else class="flex flex-col gap-8 pb-2">
        <section class="gp-settings-section">
            <div class="gp-settings-section__header">
                <h3 class="gp-section-title">Typography</h3>
                <BaseButton severity="transparent" :icon-only="true" class="size-8!" title="Reset settings" @click="reset">
                    <LucideRotateCcw :size="16" />
                </BaseButton>
            </div>

            <div class="gp-settings-group">
                <label class="gp-field-label">Font Size</label>
                <div class="flex items-center gap-3">
                    <BaseButton
                        severity="muted"
                        :icon-only="true"
                        class="size-9!"
                        :disabled="settings.fontSize <= 12"
                        @click="settings.fontSize = clampFontSize(settings.fontSize - 1)"
                    >
                        <LucideMinus :size="14" />
                    </BaseButton>
                    <input
                        type="range"
                        :min="12"
                        :max="28"
                        :value="settings.fontSize"
                        class="gp-range w-full"
                        @input="settings.fontSize = clampFontSize(Number(($event.target as HTMLInputElement).value))"
                    />
                    <BaseButton
                        severity="muted"
                        :icon-only="true"
                        class="size-9!"
                        :disabled="settings.fontSize >= 28"
                        @click="settings.fontSize = clampFontSize(settings.fontSize + 1)"
                    >
                        <LucidePlus :size="14" />
                    </BaseButton>
                    <span class="w-9 text-center text-sm tabular-nums text-gray-300">{{ settings.fontSize }}</span>
                </div>
            </div>

            <div class="gp-settings-group">
                <label class="gp-field-label">Font Color</label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in fontColorPresets"
                        :key="preset.value"
                        type="button"
                        :class="[
                            'flex h-9 items-center gap-2 rounded-lg border px-3 transition-all',
                            settings.fontColor === preset.value
                                ? 'border-primary-400 bg-primary-400/10'
                                : 'border-gray-700/80 hover:border-gray-500',
                        ]"
                        @click="settings.fontColor = preset.value"
                    >
                        <span class="size-3.5 rounded-full border border-gray-600" :style="{ backgroundColor: preset.value }" />
                        <span class="text-xs text-gray-300">{{ preset.label }}</span>
                    </button>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    <input
                        type="color"
                        :value="settings.fontColor"
                        class="size-8 cursor-pointer rounded border-none bg-transparent"
                        @input="settings.fontColor = ($event.target as HTMLInputElement).value"
                    />
                    <span class="text-xs text-gray-500">Custom color</span>
                </div>
            </div>

            <div class="gp-settings-group">
                <label class="gp-field-label">Panel tint</label>
                <p class="mb-2 text-xs text-gray-500">Tints narration and choice glass panels.</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in backgroundPresets"
                        :key="preset.value"
                        type="button"
                        :class="[
                            'flex h-9 items-center gap-2 rounded-lg border px-3 transition-all',
                            settings.backgroundColor === preset.value
                                ? 'border-primary-400 bg-primary-400/10'
                                : 'border-gray-700/80 hover:border-gray-500',
                        ]"
                        @click="settings.backgroundColor = preset.value"
                    >
                        <span
                            v-if="preset.value"
                            class="size-3.5 rounded-full border border-gray-600"
                            :style="{ backgroundColor: preset.value }"
                        />
                        <span v-else class="size-3.5 rounded-full border border-dashed border-gray-500" />
                        <span class="text-xs text-gray-300">{{ preset.label }}</span>
                    </button>
                </div>
            </div>

            <div class="gp-settings-group">
                <label class="gp-field-label">Preview</label>
                <div class="settings-glass-preview rounded-xl border border-white/10 p-4" :style="glassPreviewStyle">
                    <p
                        class="font-light leading-relaxed"
                        :style="{ fontSize: settings.fontSize + 'px', color: settings.fontColor }"
                    >
                        The ancient door creaked open, revealing a corridor bathed in moonlight...
                    </p>
                </div>
            </div>
        </section>

        <section v-if="gameId" class="gp-settings-section">
            <div v-if="subscriptionUsage" class="rounded-xl border border-primary-400/20 bg-primary-400/5 p-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="gp-field-label text-primary-200">Plan allowance</p>
                        <p class="mt-1 text-sm font-medium text-gray-100">{{ subscriptionUsage.plan.name }}</p>
                    </div>
                    <span class="text-right text-xs leading-relaxed text-gray-400">{{ formatRenewalDate(subscriptionUsage.period_ends_at) }}</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-white/8 bg-black/10 p-2.5">
                        <span class="block text-xs text-gray-400">Actions left</span>
                        <strong class="mt-1 block text-base font-semibold tabular-nums text-primary-100">
                            {{ formatUsageCount(subscriptionUsage.actions.remaining) }} / {{ formatUsageCount(subscriptionUsage.actions.limit) }}
                        </strong>
                    </div>
                    <div class="rounded-lg border border-white/8 bg-black/10 p-2.5">
                        <span class="block text-xs text-gray-400">Voice left</span>
                        <strong class="mt-1 block text-base font-semibold tabular-nums text-primary-100">
                            {{ formatVoiceDuration(subscriptionUsage.voice.remaining_milliseconds) }}
                        </strong>
                        <span class="mt-0.5 block text-[0.6875rem] text-gray-500">of {{ subscriptionUsage.voice.limit_minutes }}m</span>
                    </div>
                </div>
            </div>
            <div>
                <h3 class="gp-section-title">Usage</h3>
                <p class="mt-1 text-xs text-gray-500">This game</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                    <span class="block text-xs text-gray-400">Turns played</span>
                    <strong class="mt-1 block text-lg font-semibold tabular-nums text-gray-100">
                        {{ formatUsageCount(usage.game.turns) }}
                    </strong>
                    <span class="mt-1 block text-xs text-gray-500">Completed moves</span>
                </div>
                <div class="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                    <span class="block text-xs text-gray-400">Voice generated</span>
                    <strong class="mt-1 block text-lg font-semibold tabular-nums text-gray-100">
                        {{ formatVoiceDuration(usage.game.voice_duration_ms) }}
                    </strong>
                    <span class="mt-1 block text-xs text-gray-500">Narration time</span>
                </div>
            </div>
            <div class="border-t border-gray-700/40 pt-4">
                <p class="gp-field-label">Total (all games)</p>
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                        <span class="block text-xs text-gray-400">Turns played</span>
                        <strong class="mt-1 block text-lg font-semibold tabular-nums text-gray-100">
                            {{ formatUsageCount(usage.total.turns) }}
                        </strong>
                        <span class="mt-1 block text-xs text-gray-500">Across your games</span>
                    </div>
                    <div class="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                        <span class="block text-xs text-gray-400">Voice generated</span>
                        <strong class="mt-1 block text-lg font-semibold tabular-nums text-gray-100">
                            {{ formatVoiceDuration(usage.total.voice_duration_ms) }}
                        </strong>
                        <span class="mt-1 block text-xs text-gray-500">Across your games</span>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="gameId" class="gp-settings-section">
            <h3 class="gp-section-title">Story</h3>
            <div class="gp-settings-group">
                <BaseButton severity="gray-muted" class="w-full" :disabled="isResetting" @click="handleResetGame">
                    <LucideRefreshCw :size="16" :class="{ 'animate-spin': isResetting }" />
                    <span>{{ isResetting ? 'Resetting…' : 'Start a fresh story' }}</span>
                </BaseButton>
                <p class="text-xs leading-relaxed text-gray-500">
                    Erases all progress and begins a new playthrough from the start.
                </p>
            </div>
            <div class="gp-settings-group">
                <a
                    href="https://lorespinner.com/faq"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex w-full items-center justify-between rounded-xl border border-white/8 bg-white/[0.03] px-4 py-3 text-sm text-gray-300 no-underline transition hover:border-primary/20 hover:text-primary-300"
                >
                    <span>FAQ &amp; help</span>
                    <LucideExternalLink class="size-4 opacity-60" />
                </a>
            </div>
        </section>

        <section class="gp-settings-section">
            <h3 class="gp-section-title">Audio</h3>
            <div class="gp-settings-group">
                <label class="gp-field-label">Narration Volume</label>
                <div class="flex items-center gap-3">
                    <input type="range" min="0" max="100" :value="volumePercent()" class="gp-range flex-1" @input="onVolumeInput" />
                    <span class="w-10 text-right text-sm tabular-nums text-gray-400">{{ volumePercent() }}%</span>
                </div>
            </div>
            <div class="gp-settings-group">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-xl border border-white/8 bg-white/[0.03] px-4 py-3 transition hover:border-primary/20"
                    @click="toggleAutoplay"
                >
                    <div class="flex items-center gap-3">
                        <LucideZap
                            class="size-4 transition-colors"
                            :class="settings.autoplay ? 'text-primary fill-primary' : 'text-gray-500'"
                        />
                        <span class="text-sm text-gray-200">Autoplay narrations</span>
                    </div>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="settings.autoplay ? 'bg-primary/15 text-primary-300' : 'bg-white/5 text-gray-500'"
                    >
                        {{ settings.autoplay ? 'On' : 'Off' }}
                    </span>
                </button>
            </div>
        </section>
    </div>
</template>

<style scoped>
.gameplay-range {
    appearance: none;
    height: 0.25rem;
    background: #373737;
    border-radius: 0.25rem;
    outline: none;
}

.gameplay-range::-webkit-slider-thumb {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #54f4da;
    cursor: pointer;
    border: 2px solid #013231;
}

.gameplay-range::-moz-range-thumb {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #54f4da;
    cursor: pointer;
    border: 2px solid #013231;
}

.gp-settings-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.gp-settings-section + .gp-settings-section {
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.07);
}

.gp-settings-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.gp-section-title {
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(84, 244, 218, 0.75);
}

.gp-settings-group {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.gp-field-label {
    font-size: 0.6875rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(156, 163, 175);
}

.gp-range {
    appearance: none;
    height: 0.25rem;
    background: rgba(84, 244, 218, 0.15);
    border-radius: 0.25rem;
    outline: none;
}

.gp-range::-webkit-slider-thumb {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #54f4da;
    cursor: pointer;
    border: 2px solid #013231;
}

.gp-range::-moz-range-thumb {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #54f4da;
    cursor: pointer;
    border: 2px solid #013231;
}

.settings-glass-preview {
    background:
        linear-gradient(var(--glass-tint-solid, transparent), var(--glass-tint-solid, transparent)),
        rgba(10, 10, 18, 0.2);
    backdrop-filter: blur(18px) saturate(140%);
    -webkit-backdrop-filter: blur(18px) saturate(140%);
    box-shadow:
        inset 0 0 56px 4px var(--glass-tint, transparent),
        inset 1px 1px 0.5px -1px rgba(255, 255, 255, 0.14),
        0 4px 20px rgba(0, 0, 0, 0.24);
}
</style>
