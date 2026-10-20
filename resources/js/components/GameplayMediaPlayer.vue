<script setup lang="ts">
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import {
    LucideLoader,
    LucidePause,
    LucidePlay,
    LucideRefreshCw,
    LucideRotateCcw,
    LucideRotateCw,
    LucideSettings,
    LucideVolume2,
    LucideVolumeX,
    LucideX,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ collapsed?: boolean; docked?: boolean }>(), { collapsed: false, docked: false });

const emit = defineEmits<{
    'open-audio-settings': [];
}>();

const tts = useTextToSpeech();

const volumePercent = computed(() => {
    if (tts.isMuted.value || tts.volume.value === 0) {
        return 0;
    }

    return Math.round(tts.volume.value * 100);
});

const isVolumeMuted = computed(() => tts.isMuted.value || tts.volume.value === 0);

const speedLabel = computed(() => {
    const r = tts.playbackRate.value;
    return r === 1 ? '1×' : `${r}×`;
});

const onVolumeInput = (event: Event) => {
    tts.setVolume(Number((event.target as HTMLInputElement).value) / 100);
};

const progressPercent = computed(() => {
    if (!tts.duration.value) return 0;

    return Math.min(100, Math.max(0, (tts.currentTime.value / tts.duration.value) * 100));
});

const onProgressInput = (event: Event) => {
    if (!tts.duration.value) return;

    const percentage = Number((event.target as HTMLInputElement).value);
    tts.seekTo((percentage / 100) * tts.duration.value);
};
</script>

<template>
    <Transition name="player-slide">
        <div
            v-if="tts.isActive.value && !props.collapsed"
            class="player-bar pointer-events-auto relative isolate z-10"
            :class="
                props.docked
                    ? 'player-bar--docked mx-1.5 flex min-w-0 flex-col gap-1.5 px-3 pt-2 pb-3 sm:flex-row sm:items-center'
                    : 'flex w-full items-center gap-2 overflow-hidden rounded-full px-2 py-2 sm:gap-2.5 sm:px-2.5 md:w-auto'
            "
        >
            <template v-if="props.docked">
                <div class="player-docked-transport flex w-full min-w-0 flex-1 items-center gap-1.5">
                    <button
                        type="button"
                        class="player-docked-btn player-docked-btn--play"
                        :title="tts.isLoading.value ? 'Loading…' : tts.isPlaying.value ? 'Pause' : 'Play'"
                        :aria-label="tts.isLoading.value ? 'Loading audio' : tts.isPlaying.value ? 'Pause' : 'Play'"
                        :disabled="tts.isLoading.value"
                        @click="tts.togglePause"
                    >
                        <LucideLoader v-if="tts.isLoading.value" class="size-4 animate-spin" />
                        <LucidePause v-else-if="tts.isPlaying.value" class="size-4" fill="currentColor" />
                        <LucidePlay v-else class="size-4" fill="currentColor" />
                    </button>
                    <span class="player-docked-time shrink-0 whitespace-nowrap tabular-nums">
                        <span class="player-docked-time__current">{{ tts.formattedCurrentTime.value }}</span>
                        <span class="player-docked-time__duration"> / {{ tts.formattedDuration.value }}</span>
                    </span>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        step="0.1"
                        :value="progressPercent"
                        :disabled="tts.isLoading.value || !tts.duration.value"
                        class="media-range min-w-0 flex-1"
                        :style="{ '--range-fill': `${progressPercent}%` }"
                        aria-label="Playback progress"
                        :aria-valuetext="`${tts.formattedCurrentTime.value} of ${tts.formattedDuration.value}`"
                        @input="onProgressInput"
                    />
                </div>
                <div class="player-docked-secondary flex w-full items-center justify-between sm:w-auto sm:justify-start">
                    <div class="player-docked-volume flex shrink-0 items-center gap-1.5">
                        <button
                            type="button"
                            class="player-docked-btn player-docked-btn--plain player-docked-btn--volume"
                            :aria-pressed="isVolumeMuted"
                            :title="isVolumeMuted ? 'Unmute' : 'Mute'"
                            aria-label="Toggle mute"
                            @click="tts.toggleMute"
                        >
                            <LucideVolumeX v-if="isVolumeMuted" class="size-4" fill="currentColor" />
                            <LucideVolume2 v-else class="size-4" fill="currentColor" />
                        </button>
                        <input
                            type="range"
                            min="0"
                            max="100"
                            :value="volumePercent"
                            class="media-range min-w-0 flex-1"
                            :style="{ '--range-fill': `${volumePercent}%` }"
                            aria-label="Volume"
                            :aria-valuetext="isVolumeMuted ? 'Muted' : `${volumePercent}%`"
                            @input="onVolumeInput"
                        />
                    </div>
                    <div class="player-docked-actions flex items-center">
                        <button
                            type="button"
                            class="player-docked-btn relative"
                            title="Skip back 15s"
                            aria-label="Skip back 15s"
                            @click="tts.seekBy(-15)"
                        >
                            <LucideRotateCcw class="size-7" :stroke-width="1.4" />
                            <span class="absolute pt-1 text-[11px] leading-none tabular-nums">15</span>
                        </button>
                        <button
                            type="button"
                            class="player-docked-btn relative"
                            title="Skip forward 15s"
                            aria-label="Skip forward 15s"
                            @click="tts.seekBy(15)"
                        >
                            <LucideRotateCw class="size-7" :stroke-width="1.4" />
                            <span class="absolute pt-1 text-[11px] leading-none tabular-nums">15</span>
                        </button>
                        <button
                            type="button"
                            class="player-docked-btn"
                            :aria-pressed="tts.isLooping.value"
                            title="Loop narration"
                            aria-label="Loop narration"
                            @click="tts.toggleLoop"
                        >
                            <LucideRefreshCw class="size-7 -rotate-45" :stroke-width="1.4" />
                        </button>
                    </div>
                    <button
                        type="button"
                        class="player-docked-btn player-docked-btn--plain"
                        title="Close player"
                        aria-label="Close player"
                        @click="tts.dismiss"
                    >
                        <LucideX class="size-3.5" />
                    </button>
                </div>
            </template>
            <div v-else class="player-bar__controls relative z-[1] flex items-center justify-between gap-2 sm:gap-2.5">
                <!-- Play / Pause -->
                <button
                    type="button"
                    class="player-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full text-primary-600 transition-[transform,color] hover:scale-105 hover:text-white active:scale-95"
                    :title="tts.isLoading.value ? 'Loading…' : tts.isPlaying.value ? 'Pause' : 'Play'"
                    :disabled="tts.isLoading.value"
                    @click="tts.togglePause"
                >
                    <LucideLoader v-if="tts.isLoading.value" class="player-btn-icon animate-spin" />
                    <LucidePause v-else-if="tts.isPlaying.value" class="player-btn-icon" fill="currentColor" />
                    <LucidePlay v-else class="player-btn-icon" fill="currentColor" />
                </button>

                <!-- Time -->
                <span class="min-w-12 text-base font-medium text-primary-600 tabular-nums">
                    {{ tts.formattedCurrentTime.value }}
                </span>

                <!-- Mute (mobile) -->
                <button
                    type="button"
                    class="player-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full text-primary-600 transition-[transform,color,opacity] hover:scale-105 hover:text-white active:scale-95 md:hidden"
                    :class="isVolumeMuted ? 'text-white' : 'opacity-80 hover:opacity-100'"
                    :aria-pressed="isVolumeMuted"
                    :title="isVolumeMuted ? 'Unmute' : 'Mute'"
                    aria-label="Toggle mute"
                    @click="tts.toggleMute"
                >
                    <LucideVolumeX v-if="isVolumeMuted" class="player-btn-icon" :stroke-width="1.75" />
                    <LucideVolume2 v-else class="player-btn-icon" :stroke-width="1.75" />
                </button>

                <!-- Settings (mobile) -->
                <button
                    type="button"
                    class="player-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color] hover:scale-105 hover:text-primary-600 active:scale-95 md:hidden"
                    title="Settings"
                    aria-label="Settings"
                    @click.stop="emit('open-audio-settings')"
                >
                    <LucideSettings class="player-btn-icon" :stroke-width="1.75" />
                </button>

                <!-- Mute + volume slider (desktop) -->
                <div class="volume-control hidden items-center gap-1.5 md:flex">
                    <button
                        type="button"
                        class="player-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full text-primary-600 transition-[transform,color,opacity] hover:scale-105 hover:text-white active:scale-95"
                        :class="isVolumeMuted ? 'text-white' : 'opacity-80 hover:opacity-100'"
                        :aria-pressed="isVolumeMuted"
                        :title="isVolumeMuted ? 'Unmute' : 'Mute'"
                        aria-label="Toggle mute"
                        @click="tts.toggleMute"
                    >
                        <LucideVolumeX class="player-btn-icon" :stroke-width="1.75" />
                    </button>
                    <span
                        class="player-btn grid shrink-0 place-items-center text-primary-600"
                        :class="{ 'opacity-40': isVolumeMuted }"
                        aria-hidden="true"
                    >
                        <LucideVolume2 class="player-btn-icon" :stroke-width="1.75" />
                    </span>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        :value="volumePercent"
                        class="media-range w-20"
                        :style="{ '--range-fill': `${volumePercent}%` }"
                        aria-label="Volume"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-valuenow="volumePercent"
                        :aria-valuetext="isVolumeMuted ? 'Muted' : `${volumePercent}%`"
                        @input="onVolumeInput"
                    />
                </div>

                <span class="hidden h-6 w-px bg-white/15 md:block" />

                <!-- Skip back 15s (desktop) -->
                <button
                    type="button"
                    class="player-btn bg-muted-glass-effect relative grid shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color] hover:scale-105 hover:text-primary-600 active:scale-95 md:grid"
                    title="Skip back 15s"
                    @click="tts.seekBy(-15)"
                >
                    <LucideRotateCcw class="player-btn-icon" :stroke-width="1.5" />
                    <span class="absolute text-[9px] font-semibold tabular-nums">15</span>
                </button>

                <!-- Skip forward 15s (desktop) -->
                <button
                    type="button"
                    class="player-btn bg-muted-glass-effect relative grid shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color] hover:scale-105 hover:text-primary-600 active:scale-95 md:grid"
                    title="Skip forward 15s"
                    @click="tts.seekBy(15)"
                >
                    <LucideRotateCw class="player-btn-icon" :stroke-width="1.5" />
                    <span class="absolute text-[9px] font-semibold tabular-nums">15</span>
                </button>

                <!-- Speed -->
                <button
                    type="button"
                    class="player-btn player-speed-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color] hover:scale-105 hover:text-primary-600 active:scale-95"
                    :class="{ 'text-primary-600': tts.playbackRate.value !== 1 }"
                    title="Playback speed"
                    aria-label="Playback speed"
                    @click="tts.cycleSpeed"
                >
                    <span class="player-speed-label leading-none tabular-nums">{{ speedLabel }}</span>
                </button>

                <!-- Close -->
                <button
                    type="button"
                    class="player-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color] hover:scale-105 hover:text-primary-600 active:scale-95"
                    title="Close player"
                    @click="tts.dismiss"
                >
                    <LucideX class="player-btn-icon" :stroke-width="1.75" />
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.player-slide-enter-active,
.player-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.player-slide-enter-from,
.player-slide-leave-to {
    opacity: 0;
    transform: translateY(-12px) scale(0.95);
}

.player-bar {
    border: 0.5px solid rgba(255, 255, 255, 0.14);
    box-shadow:
        inset 3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.12),
        inset -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.1),
        inset 1px 1px 1px -0.5px rgba(255, 255, 255, 0.08),
        inset -1px -1px 1px -0.5px rgba(255, 255, 255, 0.08),
        inset 0 0 1px 1px rgba(0, 0, 0, 0.4),
        0 8px 30px rgba(0, 0, 0, 0.55);
    background-color: #1e1e1e;
}

.player-bar__controls {
    width: 100%;
    min-width: 0;
}

.player-bar--docked {
    --player-accent: #54bec9;
    border: none;
    background: transparent;
    box-shadow: none;
}

.player-docked-btn {
    display: grid;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    place-items: center;
    border: 1px solid #3c3c3c;
    border-top-color: #535353;
    border-radius: 9999px;
    background: linear-gradient(180deg, #292929 0%, #202020 100%);
    box-shadow: inset 0 -1px 0 #2d2d2d;
    color: #808080;
    cursor: pointer;
    transition:
        color 150ms ease,
        border-color 150ms ease;
}

.player-docked-btn:hover {
    border-color: var(--player-accent);
    color: var(--player-accent);
}

.player-docked-btn--play,
.player-docked-btn[aria-pressed='true']:not(.player-docked-btn--volume) {
    color: var(--player-accent);
}

.player-docked-btn:focus-visible {
    outline: 2px solid var(--player-accent);
    outline-offset: 2px;
}

.player-docked-btn:disabled {
    cursor: wait;
    opacity: 0.6;
}

.player-docked-btn--plain {
    width: 1.5rem;
    border: none;
    background: transparent;
    box-shadow: none;
}

.player-docked-btn--volume {
    color: #fff;
}

.player-docked-time {
    font-size: 0.875rem;
    line-height: 1.25rem;
}

.player-docked-time__current {
    color: var(--player-accent);
}

.player-docked-time__duration {
    color: #808080;
}

.player-docked-secondary {
    gap: 0.375rem;
}

.player-docked-volume {
    width: 5.25rem;
}

.player-docked-actions {
    gap: 0.375rem;
}

.player-bar--docked .media-range {
    --range-accent: var(--player-accent);
    --range-track-empty: #808080;
}

.player-bar--docked .media-range::-webkit-slider-thumb {
    width: 9px;
    height: 9px;
    margin-top: -2.5px;
    border: 1px solid var(--range-accent);
    background: #1c1c1c;
}

.player-bar--docked .media-range::-moz-range-thumb {
    width: 9px;
    height: 9px;
    border: 1px solid var(--range-accent);
    background: #1c1c1c;
}

@media (min-width: 640px) {
    .player-docked-btn {
        width: 2.375rem;
        height: 2.375rem;
    }

    .player-docked-btn--plain {
        width: 1.5rem;
    }

    .player-docked-btn--play {
        width: 2.25rem;
        height: 2.25rem;
        margin-right: 0.625rem;
    }

    .player-bar--docked,
    .player-docked-secondary {
        gap: 2.125rem;
    }

    .player-docked-time {
        min-width: 4.75rem;
    }

    .player-docked-volume {
        width: 7.75rem;
    }

    .player-docked-btn--volume {
        width: 1rem;
    }

    .player-docked-actions {
        gap: 0.5625rem;
    }
}

.volume-control {
    padding: 0.125rem 0.25rem;
}

.player-btn {
    width: 2.25rem;
    height: 2.25rem;
}

.player-btn-icon {
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
}

.player-speed-btn {
    min-width: 2.75rem;
    padding-inline: 0.375rem;
}

.player-speed-label {
    font-size: 1rem;
    font-weight: 600;
}

.media-range {
    --range-fill: 0%;
    --range-accent: var(--color-primary-600);
    --range-track-empty: color-mix(in srgb, var(--color-primary-600) 32%, transparent);
    appearance: none;
    height: 12px;
    border-radius: 4px;
    background: transparent;
    outline: none;
    cursor: pointer;
}

.media-range:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--color-primary-600) 55%, transparent);
    outline-offset: 2px;
    border-radius: 9999px;
}

.media-range::-webkit-slider-runnable-track {
    height: 4px;
    border-radius: 4px;
    background: linear-gradient(
        to right,
        var(--range-accent) 0%,
        var(--range-accent) var(--range-fill),
        var(--range-track-empty) var(--range-fill),
        var(--range-track-empty) 100%
    );
}

.media-range::-webkit-slider-thumb {
    appearance: none;
    width: 12px;
    height: 12px;
    margin-top: -4px;
    border-radius: 50%;
    background: var(--range-accent);
    cursor: pointer;
    transition:
        transform 150ms ease,
        background-color 150ms ease,
        box-shadow 150ms ease;
}

.media-range:active::-webkit-slider-thumb {
    transform: scale(1.1);
}

.media-range:focus-visible::-webkit-slider-thumb {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary-600) 35%, transparent);
}

.media-range::-moz-range-track {
    height: 4px;
    border-radius: 4px;
    background: var(--range-track-empty);
}

.media-range::-moz-range-progress {
    height: 4px;
    border-radius: 4px;
    background: var(--range-accent);
}

.media-range::-moz-range-thumb {
    width: 12px;
    height: 12px;
    border: none;
    border-radius: 50%;
    background: var(--range-accent);
    cursor: pointer;
    transition:
        transform 150ms ease,
        background-color 150ms ease,
        box-shadow 150ms ease;
}

.media-range:active::-moz-range-thumb {
    transform: scale(1.1);
}

.media-range:focus-visible::-moz-range-thumb {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary-600) 35%, transparent);
}
</style>
