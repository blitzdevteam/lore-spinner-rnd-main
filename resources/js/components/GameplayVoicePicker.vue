<script setup lang="ts">
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { useTtsVoices } from '@/composables/useTtsVoices';
import { LucideMic } from 'lucide-vue-next';
import { computed } from 'vue';

const { settings } = useGameplaySettings();
const { voices, recommendedVoiceId, recommendedLabel } = useTtsVoices();
const tts = useTextToSpeech();

const isRecommended = computed(() => settings.voiceId === '');

const selectVoice = (voiceId: string) => {
    if (settings.voiceId === voiceId) return;
    settings.voiceId = voiceId;
    tts.invalidateCache();
};
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <label class="gp-field-label">Narrator voice</label>
        <p class="text-xs text-gray-500">
            Recommended for this story:
            <span class="text-gray-400">{{ recommendedLabel() }}</span>
        </p>
        <div class="flex flex-col gap-2">
            <button
                type="button"
                :class="[
                    'flex w-full items-start gap-3 rounded-xl border px-3.5 py-3 text-left transition-all',
                    isRecommended
                        ? 'border-primary-400 bg-primary-400/10'
                        : 'border-gray-700/80 hover:border-gray-500',
                ]"
                @click="selectVoice('')"
            >
                <LucideMic class="mt-0.5 size-4 shrink-0" :class="isRecommended ? 'text-primary-300' : 'text-gray-500'" />
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-gray-200">Recommended</span>
                        <span class="rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-primary-300">
                            Default
                        </span>
                    </span>
                    <span class="mt-0.5 block text-xs text-gray-500">{{ recommendedLabel() }} for this story</span>
                </span>
            </button>

            <button
                v-for="voice in voices"
                :key="voice.id"
                type="button"
                :class="[
                    'flex w-full items-start gap-3 rounded-xl border px-3.5 py-3 text-left transition-all',
                    settings.voiceId === voice.id
                        ? 'border-primary-400 bg-primary-400/10'
                        : 'border-gray-700/80 hover:border-gray-500',
                ]"
                @click="selectVoice(voice.id)"
            >
                <LucideMic
                    class="mt-0.5 size-4 shrink-0"
                    :class="settings.voiceId === voice.id ? 'text-primary-300' : 'text-gray-500'"
                />
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-gray-200">{{ voice.label }}</span>
                        <span
                            v-if="voice.id === recommendedVoiceId"
                            class="text-[10px] uppercase tracking-wide text-gray-500"
                        >
                            Story pick
                        </span>
                    </span>
                    <span v-if="voice.description" class="mt-0.5 block text-xs text-gray-500">{{ voice.description }}</span>
                </span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.gp-field-label {
    font-size: 0.6875rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(156, 163, 175);
}
</style>
