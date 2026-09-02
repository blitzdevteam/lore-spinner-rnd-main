<script setup lang="ts">
import GameplayVoicePicker from '@/components/GameplayVoicePicker.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { LucideLoader, LucidePause, LucidePlay, LucideRotateCcw, LucideRotateCw, LucideVolume2, LucideVolumeX, LucideZap } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    gameId?: string;
    promptId?: string | null;
}>();

const tts = useTextToSpeech();
const { settings } = useGameplaySettings();

const volumePercent = computed(() => {
    if (tts.isMuted.value || tts.volume.value === 0) return 0;
    return Math.round(tts.volume.value * 100);
});

const isVolumeMuted = computed(() => tts.isMuted.value || tts.volume.value === 0);

const toggleAutoplay = () => {
    tts.primeAudio();
    settings.autoplay = !settings.autoplay;
};

const speedLabel = computed(() => {
    const r = tts.playbackRate.value;
    return r === 1 ? '1×' : `${r}×`;
});

const progressPercent = computed(() => {
    if (!tts.duration.value) return 0;
    return Math.min(100, (tts.currentTime.value / tts.duration.value) * 100);
});

const onVolumeInput = (event: Event) => {
    tts.setVolume(Number((event.target as HTMLInputElement).value) / 100);
};

const onProgressInput = (event: Event) => {
    const pct = Number((event.target as HTMLInputElement).value);
    if (tts.duration.value) {
        tts.seekTo((pct / 100) * tts.duration.value);
    }
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Now playing -->
        <section class="gp-audio-section">
            <h3 class="gp-section-label">Narration</h3>
            <div class="gp-audio-card rounded-2xl border border-white/8 bg-white/[0.03] p-4">
                <div v-if="tts.isActive.value" class="flex flex-col gap-4">
                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            class="grid size-14 shrink-0 place-items-center rounded-full border border-primary/30 bg-primary/15 text-primary-300 transition hover:bg-primary/25 hover:text-primary-200"
                            :title="tts.isLoading.value ? 'Loading…' : tts.isPlaying.value ? 'Pause' : 'Play'"
                            :disabled="tts.isLoading.value"
                            @click="tts.togglePause"
                        >
                            <LucideLoader v-if="tts.isLoading.value" class="size-6 animate-spin" />
                            <LucidePause v-else-if="tts.isPlaying.value" class="size-6" fill="currentColor" />
                            <LucidePlay v-else class="size-6" fill="currentColor" />
                        </button>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-200">Now playing</p>
                            <p class="mt-0.5 text-xs tabular-nums text-gray-500">
                                {{ tts.formattedCurrentTime.value }} / {{ tts.formattedDuration.value }}
                            </p>
                        </div>
                    </div>

                    <input
                        type="range"
                        min="0"
                        max="100"
                        :value="progressPercent"
                        class="gp-audio-range w-full"
                        :style="{ '--range-fill': `${progressPercent}%` }"
                        aria-label="Playback progress"
                        @input="onProgressInput"
                    />

                    <div class="flex items-center justify-center gap-3">
                        <button
                            type="button"
                            class="gp-audio-btn"
                            title="Skip back 15s"
                            @click="tts.seekBy(-15)"
                        >
                            <LucideRotateCcw class="size-5" :stroke-width="1.5" />
                            <span class="text-[9px] font-semibold">15</span>
                        </button>
                        <button
                            type="button"
                            class="gp-audio-btn gp-audio-btn--speed"
                            :class="{ 'text-primary-300!': tts.playbackRate.value !== 1 }"
                            title="Playback speed"
                            @click="tts.cycleSpeed"
                        >
                            {{ speedLabel }}
                        </button>
                        <button
                            type="button"
                            class="gp-audio-btn"
                            title="Skip forward 15s"
                            @click="tts.seekBy(15)"
                        >
                            <LucideRotateCw class="size-5" :stroke-width="1.5" />
                            <span class="text-[9px] font-semibold">15</span>
                        </button>
                    </div>
                </div>
                <p v-else class="py-4 text-center text-sm text-gray-500">
                    Tap Listen on any narration to start audio playback.
                </p>
            </div>
        </section>

        <!-- Voice -->
        <section class="gp-audio-section">
            <GameplayVoicePicker :game-id="gameId" :prompt-id="promptId" />
        </section>

        <!-- Volume -->
        <section class="gp-audio-section">
            <h3 class="gp-section-label">Narration Volume</h3>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="grid size-10 shrink-0 place-items-center rounded-full border border-white/10 bg-white/5 text-gray-400 transition hover:text-primary-300"
                    :title="isVolumeMuted ? 'Unmute' : 'Mute'"
                    @click="tts.toggleMute"
                >
                    <LucideVolumeX v-if="isVolumeMuted" class="size-4" />
                    <LucideVolume2 v-else class="size-4" />
                </button>
                <input
                    type="range"
                    min="0"
                    max="100"
                    :value="volumePercent"
                    class="gp-audio-range flex-1"
                    :style="{ '--range-fill': `${volumePercent}%` }"
                    aria-label="Volume"
                    @input="onVolumeInput"
                />
                <span class="w-10 text-right text-sm tabular-nums text-gray-400">{{ volumePercent }}%</span>
            </div>
        </section>

        <!-- Autoplay -->
        <section class="gp-audio-section">
            <h3 class="gp-section-label">Playback</h3>
            <button
                type="button"
                class="flex w-full items-center justify-between rounded-xl border border-white/8 bg-white/[0.03] px-4 py-3.5 transition hover:border-primary/20 hover:bg-white/[0.05]"
                @click="toggleAutoplay"
            >
                <div class="flex items-center gap-3">
                    <LucideZap
                        class="size-5 transition-colors"
                        :class="settings.autoplay ? 'text-primary fill-primary' : 'text-gray-500'"
                    />
                    <div class="text-left">
                        <p class="text-sm font-medium text-gray-200">Autoplay narrations</p>
                        <p class="text-xs text-gray-500">Play new responses automatically</p>
                    </div>
                </div>
                <span
                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="settings.autoplay ? 'bg-primary/15 text-primary-300' : 'bg-white/5 text-gray-500'"
                >
                    {{ settings.autoplay ? 'On' : 'Off' }}
                </span>
            </button>
        </section>
    </div>
</template>

<style scoped>
.gp-section-label {
    margin-bottom: 0.75rem;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(84, 244, 218, 0.65);
}

.gp-audio-section + .gp-audio-section {
    padding-top: 0.25rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.gp-audio-btn {
    position: relative;
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.04);
    color: rgb(209, 213, 219);
    transition: color 0.15s ease, background 0.15s ease, border-color 0.15s ease;
}

.gp-audio-btn:hover {
    border-color: rgba(84, 244, 218, 0.25);
    color: rgb(84, 244, 218);
    background: rgba(84, 244, 218, 0.08);
}

.gp-audio-btn--speed {
    width: 3rem;
    font-size: 0.75rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.gp-audio-range {
    --range-fill: 0%;
    appearance: none;
    height: 12px;
    border-radius: 4px;
    background: transparent;
    outline: none;
    cursor: pointer;
}

.gp-audio-range::-webkit-slider-runnable-track {
    height: 4px;
    border-radius: 4px;
    background: linear-gradient(
        to right,
        #54f4da 0%,
        #54f4da var(--range-fill),
        rgba(84, 244, 218, 0.2) var(--range-fill),
        rgba(84, 244, 218, 0.2) 100%
    );
}

.gp-audio-range::-webkit-slider-thumb {
    appearance: none;
    width: 14px;
    height: 14px;
    margin-top: -5px;
    border-radius: 50%;
    background: #54f4da;
    border: 2px solid #013231;
    cursor: pointer;
}

.gp-audio-range::-moz-range-track {
    height: 4px;
    border-radius: 4px;
    background: rgba(84, 244, 218, 0.2);
}

.gp-audio-range::-moz-range-progress {
    height: 4px;
    border-radius: 4px;
    background: #54f4da;
}

.gp-audio-range::-moz-range-thumb {
    width: 14px;
    height: 14px;
    border: 2px solid #013231;
    border-radius: 50%;
    background: #54f4da;
    cursor: pointer;
}
</style>
