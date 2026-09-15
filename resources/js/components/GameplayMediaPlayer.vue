<script setup lang="ts">
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import {
    LucideLoader,
    LucidePause,
    LucidePlay,
    LucideRotateCcw,
    LucideRotateCw,
    LucideSettings,
    LucideVolume2,
    LucideVolumeX,
    LucideX,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ collapsed?: boolean }>(), { collapsed: false });

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
</script>

<template>
    <Transition name="player-slide">
        <div
            v-if="tts.isActive.value && !props.collapsed"
            class="player-bar pointer-events-auto relative isolate z-10 flex w-full items-center gap-2 overflow-hidden rounded-full px-2 py-2 sm:gap-2.5 sm:px-2.5 md:w-auto"
        >
            <span class="player-bar__backdrop" aria-hidden="true" />
            <div class="player-bar__controls relative z-[1] flex items-center justify-between gap-2 sm:gap-2.5">
                <!-- Play / Pause -->
                <button
                    type="button"
                    class="player-btn gameplay-player-btn grid shrink-0 place-items-center rounded-full text-primary-600 transition-[transform,color,background-color] hover:scale-105 hover:text-white active:scale-95"
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
                    class="player-btn gameplay-player-btn grid shrink-0 place-items-center rounded-full text-primary-600 transition-[transform,color,opacity,background-color] hover:scale-105 hover:text-white active:scale-95 md:hidden"
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
                    class="player-btn gameplay-player-btn grid shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color,background-color] hover:scale-105 hover:text-primary-600 active:scale-95 md:hidden"
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
                        class="player-btn gameplay-player-btn grid shrink-0 place-items-center rounded-full text-primary-600 transition-[transform,color,opacity,background-color] hover:scale-105 hover:text-white active:scale-95"
                        :class="isVolumeMuted ? 'text-white' : 'opacity-80 hover:opacity-100'"
                        :aria-pressed="isVolumeMuted"
                        :title="isVolumeMuted ? 'Unmute' : 'Mute'"
                        aria-label="Toggle mute"
                        @click="tts.toggleMute"
                    >
                        <LucideVolumeX class="player-btn-icon" :stroke-width="1.75" />
                    </button>
                    <span
                        class="player-btn gameplay-player-btn grid shrink-0 place-items-center text-primary-600"
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
                    class="player-btn gameplay-player-btn relative hidden shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color,background-color] hover:scale-105 hover:text-primary-600 active:scale-95 md:grid"
                    title="Skip back 15s"
                    @click="tts.seekBy(-15)"
                >
                    <LucideRotateCcw class="player-btn-icon" :stroke-width="1.5" />
                    <span class="absolute text-[9px] font-semibold tabular-nums">15</span>
                </button>

                <!-- Skip forward 15s (desktop) -->
                <button
                    type="button"
                    class="player-btn gameplay-player-btn relative hidden shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color,background-color] hover:scale-105 hover:text-primary-600 active:scale-95 md:grid"
                    title="Skip forward 15s"
                    @click="tts.seekBy(15)"
                >
                    <LucideRotateCw class="player-btn-icon" :stroke-width="1.5" />
                    <span class="absolute text-[9px] font-semibold tabular-nums">15</span>
                </button>

                <!-- Speed -->
                <button
                    type="button"
                    class="player-btn gameplay-player-btn player-speed-btn grid shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color,background-color] hover:scale-105 hover:text-primary-600 active:scale-95"
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
                    class="player-btn gameplay-player-btn grid shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color,background-color] hover:scale-105 hover:text-primary-600 active:scale-95"
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
        0 10px 40px rgba(0, 0, 0, 0.55),
        0 0 0 1px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.player-bar__backdrop {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.025) 42%, rgba(8, 18, 22, 0.2)),
        rgba(6, 10, 12, 0.5);
    backdrop-filter: blur(32px) saturate(180%);
    -webkit-backdrop-filter: blur(32px) saturate(180%);
}

.player-bar__controls {
    width: 100%;
    min-width: 0;
}

.volume-control {
    padding: 0.125rem 0.25rem;
}

.player-btn {
    width: 2.25rem;
    height: 2.25rem;
}

.gameplay-player-btn {
    border: 1px solid rgba(255, 255, 255, 0.1);
    background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.035)),
        rgba(255, 255, 255, 0.035);
    box-shadow:
        inset 1.5px 1.5px 0.5px -1px rgba(255, 255, 255, 0.5),
        inset -1px -1px 0.5px -1px rgba(0, 0, 0, 0.35),
        inset 0 0 12px rgba(255, 255, 255, 0.045);
    backdrop-filter: blur(14px) saturate(150%);
    -webkit-backdrop-filter: blur(14px) saturate(150%);
}

.gameplay-player-btn:hover {
    background:
        linear-gradient(145deg, rgba(143, 203, 211, 0.28), rgba(255, 255, 255, 0.06)),
        rgba(84, 244, 218, 0.08);
    border-color: rgba(143, 203, 211, 0.45);
    box-shadow:
        0 4px 16px rgba(84, 244, 218, 0.16),
        inset 1.5px 1.5px 0.5px -1px rgba(255, 255, 255, 0.6),
        inset 0 0 14px rgba(143, 203, 211, 0.1);
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
