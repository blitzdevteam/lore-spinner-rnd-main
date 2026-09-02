<script setup lang="ts">
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { useTtsVoices, type TtsVoiceOption } from '@/composables/useTtsVoices';
import { LucideLoader, LucideMic, LucidePlay, LucideSearch } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    gameId?: string;
    promptId?: string | null;
}>();

const { settings } = useGameplaySettings();
const { voices, recommendedVoiceId, recommendedLabel } = useTtsVoices();
const tts = useTextToSpeech();

type VoiceFilter = 'all' | 'male' | 'female';

const query = ref('');
const filter = ref<VoiceFilter>('all');

const recommendedVoice = computed(() => voices.value.find((voice) => voice.id === recommendedVoiceId.value));
const isRecommended = computed(() => settings.voiceId === '');
const canPreview = computed(() => Boolean(props.gameId && props.promptId));
const selectedVoiceId = computed(() => settings.voiceId || recommendedVoiceId.value);

const filteredVoices = computed(() => {
    const needle = query.value.trim().toLowerCase();

    return voices.value.filter((voice) => {
        if (filter.value !== 'all' && voice.gender !== filter.value) {
            return false;
        }

        if (!needle) {
            return true;
        }

        return `${voice.label} ${voice.description}`.toLowerCase().includes(needle);
    });
});

const groups = computed(() => {
    const next: { label: string; voices: TtsVoiceOption[] }[] = [];
    const male = filteredVoices.value.filter((voice) => voice.gender === 'male');
    const female = filteredVoices.value.filter((voice) => voice.gender === 'female');

    if (filter.value !== 'female' && male.length > 0) {
        next.push({ label: 'Male', voices: male });
    }

    if (filter.value !== 'male' && female.length > 0) {
        next.push({ label: 'Female', voices: female });
    }

    return next;
});

const selectVoice = (voiceId: string) => {
    if (settings.voiceId === voiceId) {
        return;
    }

    settings.voiceId = voiceId;
    tts.invalidateCache();
};

const previewVoice = (voiceId: string) => {
    tts.primeAudio();
    selectVoice(voiceId);

    if (props.gameId && props.promptId) {
        tts.play(props.gameId, props.promptId);
    }
};

const isSelected = (voiceId: string) => settings.voiceId === voiceId;

const isPreviewing = (voiceId: string) => {
    const resolved = voiceId || recommendedVoiceId.value;

    return selectedVoiceId.value === resolved && (tts.isPlaying.value || tts.isLoading.value);
};

const rowClass = (active: boolean) => [
    'flex w-full items-start gap-3 rounded-xl border px-3.5 py-3 text-left transition-all',
    active ? 'border-primary-400 bg-primary-400/10' : 'border-gray-700/80 hover:border-gray-500',
];
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <label class="gp-field-label">Narrator voice</label>
                <p class="mt-1 text-xs text-gray-500">
                    Story default:
                    <span class="text-gray-300">{{ recommendedLabel() }}</span>
                </p>
            </div>
            <p v-if="!canPreview" class="shrink-0 pt-0.5 text-[11px] text-gray-600">Play a turn to preview</p>
        </div>

        <div class="flex gap-1.5">
            <button
                v-for="option in [
                    { id: 'all', label: 'All' },
                    { id: 'male', label: 'Male' },
                    { id: 'female', label: 'Female' },
                ]"
                :key="option.id"
                type="button"
                :class="[
                    'rounded-full px-3 py-1 text-xs font-medium transition',
                    filter === option.id
                        ? 'bg-primary/20 text-primary-200'
                        : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-gray-200',
                ]"
                @click="filter = option.id as VoiceFilter"
            >
                {{ option.label }}
            </button>
        </div>

        <label class="relative block">
            <LucideSearch class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-gray-500" />
            <input
                v-model="query"
                type="search"
                placeholder="Search voices"
                class="w-full rounded-xl border border-white/8 bg-white/[0.03] py-2 pr-3 pl-9 text-sm text-gray-200 outline-none placeholder:text-gray-600 focus:border-primary/30"
            />
        </label>

        <div class="flex max-h-80 flex-col gap-2 overflow-y-auto pr-0.5">
            <div :class="rowClass(isRecommended)">
                <button type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left" @click="selectVoice('')">
                    <LucideMic class="mt-0.5 size-4 shrink-0" :class="isRecommended ? 'text-primary-300' : 'text-gray-500'" />
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-gray-200">{{ recommendedLabel() }}</span>
                            <span class="rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-primary-300">
                                Story default
                            </span>
                        </span>
                        <span class="mt-0.5 block text-xs text-gray-500">
                            {{ recommendedVoice?.description || 'Recommended for this story' }}
                        </span>
                    </span>
                </button>
                <button
                    v-if="canPreview"
                    type="button"
                    class="gp-voice-preview"
                    :title="`Preview ${recommendedLabel()}`"
                    :aria-label="`Preview ${recommendedLabel()}`"
                    @click="previewVoice('')"
                >
                    <LucideLoader v-if="isPreviewing('') && tts.isLoading.value" class="size-3.5 animate-spin" />
                    <LucidePlay v-else class="size-3.5" fill="currentColor" />
                </button>
            </div>

            <template v-for="group in groups" :key="group.label">
                <p class="gp-voice-group">{{ group.label }}</p>
                <div v-for="voice in group.voices" :key="voice.id" :class="rowClass(isSelected(voice.id))">
                    <button type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left" @click="selectVoice(voice.id)">
                        <LucideMic
                            class="mt-0.5 size-4 shrink-0"
                            :class="isSelected(voice.id) ? 'text-primary-300' : 'text-gray-500'"
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
                    <button
                        v-if="canPreview"
                        type="button"
                        class="gp-voice-preview"
                        :title="`Preview ${voice.label}`"
                        :aria-label="`Preview ${voice.label}`"
                        @click="previewVoice(voice.id)"
                    >
                        <LucideLoader v-if="isPreviewing(voice.id) && tts.isLoading.value" class="size-3.5 animate-spin" />
                        <LucidePlay v-else class="size-3.5" fill="currentColor" />
                    </button>
                </div>
            </template>

            <p v-if="filteredVoices.length === 0" class="py-6 text-center text-xs text-gray-500">No voices match that search.</p>
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

.gp-voice-group {
    margin-top: 0.35rem;
    padding-left: 0.15rem;
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(84, 244, 218, 0.55);
}

.gp-voice-preview {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 2rem;
    height: 2rem;
    margin-top: 0.05rem;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgb(209, 213, 219);
    transition:
        color 0.15s ease,
        background 0.15s ease,
        border-color 0.15s ease;
}

.gp-voice-preview:hover {
    border-color: rgba(84, 244, 218, 0.35);
    color: rgb(84, 244, 218);
    background: rgba(84, 244, 218, 0.1);
}
</style>
