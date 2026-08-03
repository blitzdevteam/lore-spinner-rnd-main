<script setup lang="ts">
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import GameplayContinueIcon from '@/components/icons/GameplayContinueIcon.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { useTypewriter } from '@/composables/useTypewriter';
import { PromptChoice, PromptInterface } from '@/types';
import { glassTintVars } from '@/utils/color';
import { LucideCheck, LucideLoader, LucidePause, LucidePlay } from 'lucide-vue-next';
import { computed, onMounted, watch } from 'vue';

const CONTINUE_MARKER = '__continue__';

const { settings: gameplaySettings } = useGameplaySettings();

const panelGlassStyle = computed(() => glassTintVars(gameplaySettings.backgroundColor));

const props = defineProps<{
    prompt: PromptInterface;
    gameId: string;
    coverUrl?: string | null;
    isLatest?: boolean;
    pendingChoice?: string;
    isSubmitting?: boolean;
    animate?: boolean;
    isStreamingResponse?: boolean;
    usesPlayableMoments?: boolean;
}>();

const emit = defineEmits<{
    'choice-selected': [promptId: string, suggestionIndex: number, choice: PromptChoice];
    continue: [];
    /** Fired once when the choice buttons first become visible on this (latest) card. */
    'choices-ready': [];
}>();

const tts = useTextToSpeech();
const typewriter = useTypewriter();

const effectiveSelection = computed(() => {
    return props.prompt.prompt || props.pendingChoice || null;
});

const isContinued = computed(() => {
    return effectiveSelection.value === CONTINUE_MARKER;
});

const canInteract = computed(() => {
    return props.isLatest && !effectiveSelection.value && !props.isSubmitting;
});

const hasChoices = computed(() => (props.prompt.choices?.length ?? 0) > 0);
const normalizedChoices = computed(() =>
    (props.prompt.choices ?? []).map((choice, index) => ({
        index,
        raw: choice,
        text: typeof choice === 'string' ? choice : choice.text,
    })),
);

// Player's own typed/spoken action (not one of the listed choices, not a continue)
const showCustomAction = computed(() => {
    if (!effectiveSelection.value || isContinued.value) return false;
    return !normalizedChoices.value.some((choice) => choice.text === effectiveSelection.value);
});

// Continue is always available when the player can interact and a response exists
const showContinueButton = computed(() => {
    return !props.usesPlayableMoments && canInteract.value && !!props.prompt.response;
});

const renderedResponse = computed(() => {
    if (props.animate && props.isLatest) {
        return typewriter.displayedHtml.value;
    }
    return props.prompt.response;
});

const showChoicesAndActions = computed(() => {
    if (props.isStreamingResponse) {
        return false;
    }

    if (props.animate && typewriter.isTyping.value) {
        return false;
    }
    return true;
});

const showResponseActions = computed(() => {
    return !!props.prompt.response && (props.isStreamingResponse || showChoicesAndActions.value);
});

const listenDisabled = computed(() => {
    return !!props.isStreamingResponse || (!!props.isLatest && !!props.isSubmitting);
});

const listenTitle = computed(() => {
    if (listenDisabled.value) return 'Listen is available when narration finishes';
    return isThisPlaying.value ? 'Pause narration' : hasPlayedOnce.value ? 'Listen again' : 'Listen';
});

const getChoiceClass = (choice: string) => {
    const base = 'choice-glass flex min-h-[52px] items-center gap-2 rounded-lg border px-2 py-2 transition-all duration-300 sm:min-h-[60px] sm:gap-2.5 sm:py-1';

    if (!effectiveSelection.value) {
        return `${base} choice-glass--idle border-white/10 text-white cursor-pointer hover:border-primary-600/70`;
    }

    if (effectiveSelection.value === choice) {
        return `${base} choice-glass--selected border-primary-600 text-white pointer-events-none`;
    }

    return `${base} choice-glass--dimmed border-white/5 text-gray-400 opacity-50 pointer-events-none`;
};

const handleChoiceClick = (index: number, choice: PromptChoice) => {
    if (!canInteract.value) return;
    tts.primeAudio();
    emit('choice-selected', props.prompt.id, index, choice);
};

const handleContinue = () => {
    if (!canInteract.value) return;
    tts.primeAudio();
    emit('continue');
};

const thisKey = computed(() => `${props.gameId}:${props.prompt.id}`);
const isThisPlaying = computed(() => tts.isPlaying.value && tts.activeKey.value === thisKey.value);
const isThisLoading = computed(() => tts.isLoading.value && tts.activeKey.value === thisKey.value);
const hasPlayedOnce = computed(() => tts.hasPlayed(props.gameId, props.prompt.id) || isThisPlaying.value || isThisLoading.value);

const handleListenAgain = () => {
    if (listenDisabled.value) return;
    tts.toggle(props.gameId, props.prompt.id);
};

const handleNarrationClick = () => {
    if (typewriter.isTyping.value && props.prompt.response) {
        typewriter.skipToEnd(props.prompt.response);
    }
};

onMounted(() => {
    if (props.animate && props.isLatest && props.prompt.response) {
        typewriter.start(props.prompt.response);
    } else if (props.prompt.response) {
        typewriter.complete(props.prompt.response);
    }
});

watch(
    () => props.prompt.response,
    (newVal) => {
        if (!newVal) return;
        if (props.animate && props.isLatest) {
            typewriter.start(newVal);
        } else {
            typewriter.complete(newVal);
        }
    },
);

watch(
    () => props.animate,
    (animate, wasAnimate) => {
        if (!animate || wasAnimate || !props.isLatest || !props.prompt.response) return;
        typewriter.start(props.prompt.response);
    },
);

// Autoplay: once the typewriter finishes, play the narration.
// Works the same on desktop and mobile — text must be fully generated first.
// skipToEnd() also triggers this (cancel() sets isTyping → false).
watch(
    () => typewriter.isTyping.value,
    (isTyping, wasTyping) => {
        if (isTyping || !wasTyping) return; // only on true → false transition
        if (!props.isLatest || !props.animate) return;
        if (!gameplaySettings.autoplay) return;
        tts.play(props.gameId, props.prompt.id);
    },
);

// Nudge trigger: fire once when choice buttons first become visible on this card.
// This is the single most reliable signal that the player can now act.
const choicesVisible = computed(
    () => !!props.isLatest && canInteract.value && showChoicesAndActions.value && hasChoices.value,
);

watch(choicesVisible, (visible, wasVisible) => {
    if (visible && !wasVisible) {
        emit('choices-ready');
    }
});
</script>

<template>
    <div class="flex flex-col gap-4 sm:gap-5">
        <!-- ── Narration card ── -->
        <div
            v-if="prompt.response"
            class="narration-card gameplay-narration-glass rounded-xl p-4 sm:rounded-[14px] sm:p-5"
            :style="panelGlassStyle"
            @click="handleNarrationClick"
        >
            <div class="text-justify leading-relaxed font-normal tracking-[0.04em]" style="font-size: inherit" v-html="renderedResponse"></div>
            <span v-if="typewriter.isTyping.value || props.isStreamingResponse" class="mt-1 inline-block h-5 w-0.5 animate-pulse bg-primary-400 align-middle" />
        </div>

        <!-- ── Player's custom action echo ── -->
        <div v-if="showCustomAction" class="border-l-2 border-primary py-2 pl-4">
            <p class="text-base text-primary italic">"{{ effectiveSelection }}"</p>
        </div>

        <!-- ── Listen Again + Continue ── -->
        <div v-if="showResponseActions" class="flex flex-row flex-wrap items-center gap-1.5">
            <button
                type="button"
                class="narration-action-pill listen-again-pill relative flex h-[50px] w-auto items-center gap-[5px] overflow-hidden rounded-[60px] p-[6px] pe-3.5"
                :class="{ 'cursor-not-allowed opacity-55': listenDisabled }"
                :title="listenTitle"
                :disabled="listenDisabled"
                :aria-disabled="listenDisabled"
                @click="handleListenAgain"
            >
                <!-- Background layers -->
                <span aria-hidden class="pointer-events-none absolute inset-0">
                    <span class="absolute inset-0 bg-[#33333330]" />
                    <img
                        v-if="props.coverUrl"
                        class="absolute top-1/2 left-1/2 h-[58px] w-[195px] max-w-none -translate-x-1/2 -translate-y-1/2 object-cover"
                        :src="props.coverUrl"
                        alt=""
                    />
                    <span class="absolute inset-0 bg-[rgba(51,51,51,0.3)] mix-blend-saturation backdrop-blur-[6px]" />
                </span>
                <!-- Inner shine overlay -->
                <span aria-hidden class="listen-again-shine pointer-events-none absolute inset-0 rounded-[60px]" />
                <!-- Icon -->
                <span class="relative grid size-[37px] shrink-0 place-items-center overflow-hidden rounded-full bg-primary-600 text-white">
                    <LucideLoader v-if="isThisLoading" class="size-4 animate-spin" />
                    <LucidePause v-else-if="isThisPlaying" class="size-4" fill="currentColor" />
                    <LucidePlay v-else class="size-4" fill="currentColor" />
                </span>
                <!-- Label -->
                <span class="relative flex min-w-0 flex-col items-start leading-tight tracking-[0.5px]">
                    <span class="text-sm text-primary-600">{{ hasPlayedOnce ? 'Listen Again' : 'Listen' }}</span>
                    <span class="text-xs font-light text-[#7e7e7e]">{{ hasPlayedOnce ? 'Replay Narration' : 'Play Narration' }}</span>
                </span>
            </button>

            <button
                v-if="showContinueButton"
                type="button"
                class="narration-action-pill bg-glass-effect flex h-[50px] w-auto min-w-0 items-center gap-2 overflow-hidden rounded-full p-1.5 pe-4 sm:pe-5 bg-[#33333330]"
                title="Continue the story"
                @click="handleContinue"
            >
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-primary-600 text-white">
                    <GameplayContinueIcon />
                </span>
                <span class="flex min-w-0 flex-col items-start leading-tight">
                    <span class="text-sm text-primary-400 sm:hidden">Continue</span>
                    <span class="hidden text-sm text-primary-400 sm:inline">Continue: Hear What Happens Next</span>
                    <span class="text-xs font-light text-[#7e7e7e]">Let the story progress</span>
                </span>
            </button>
        </div>

        <!-- ── Choices ── -->
        <div v-if="hasChoices && showChoicesAndActions" class="flex flex-col gap-4">
            <GameplayOrnamentDivider v-if="canInteract" label="Make a choice" color="#ffbe58" />

            <div class="flex flex-col gap-2.5">
                <div
                    v-for="choice in normalizedChoices"
                    :key="`${choice.index}:${choice.text}`"
                    :class="getChoiceClass(choice.text)"
                    :style="panelGlassStyle"
                    @click="handleChoiceClick(choice.index, choice.raw)"
                >
                    <span
                        v-if="effectiveSelection === choice.text"
                        class="grid size-7 shrink-0 place-items-center rounded-full border-2 border-primary-600 bg-primary-600/20 text-primary-600"
                    >
                        <LucideCheck class="size-4" />
                    </span>
                    <span v-else class="size-7 shrink-0 rounded-full border border-gray-500" />
                    <p class="text-sm font-normal sm:text-[15px]">{{ choice.text }}</p>
                </div>
            </div>

            <!-- Off-path note -->
            <div v-if="canInteract" class="flex flex-col gap-0.5">
                <p class="text-base text-secondary-300">Want To Go Off-Path?</p>
                <p class="text-xs font-light tracking-wide text-gray-400">Speak Or Write Your Own Choice.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.narration-action-pill {
    transition:
        transform 150ms ease,
        color 150ms ease;
}

.narration-action-pill:hover {
    transform: scale(1.02);
}

.narration-action-pill:active {
    transform: scale(0.98);
}

.listen-again-pill {
    box-shadow:
        0 0 2px 0 rgba(0, 0, 0, 0.1),
        0 1px 8px 0 rgba(0, 0, 0, 0.12);
}

.listen-again-shine {
    box-shadow:
        inset 3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.75),
        inset -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.8),
        inset 1px 1px 1px -0.5px rgba(255, 255, 255, 0.75),
        inset -1px -1px 1px -0.5px rgba(255, 255, 255, 0.75),
        inset 0 0 1px 1px rgba(153, 153, 153, 0.15),
        inset 0 0 16px 0 rgba(242, 242, 242, 0.15);
}

.choice-glass {
    background:
        linear-gradient(var(--glass-tint-solid, transparent), var(--glass-tint-solid, transparent)),
        rgba(10, 10, 18, 0.18);
    backdrop-filter: blur(14px) saturate(135%);
    -webkit-backdrop-filter: blur(14px) saturate(135%);
    box-shadow:
        inset 0 0 40px 2px var(--glass-tint, transparent),
        inset 1px 1px 0.5px -1px rgba(255, 255, 255, 0.12),
        0 2px 12px rgba(0, 0, 0, 0.2);
}

.choice-glass--idle:hover {
    background:
        linear-gradient(var(--glass-tint-solid, transparent), var(--glass-tint-solid, transparent)),
        rgba(10, 10, 18, 0.24);
    box-shadow:
        inset 0 0 48px 4px var(--glass-tint, transparent),
        inset 1px 1px 0.5px -1px rgba(255, 255, 255, 0.14),
        0 0 0 1px rgba(229, 173, 83, 0.18),
        0 4px 16px rgba(0, 0, 0, 0.24);
}

.choice-glass--selected {
    background:
        linear-gradient(var(--glass-tint-strong, rgba(229, 173, 83, 0.2)), var(--glass-tint-strong, rgba(229, 173, 83, 0.2))),
        rgba(10, 10, 18, 0.22);
    box-shadow:
        inset 0 0 52px 6px var(--glass-tint-strong, rgba(229, 173, 83, 0.25)),
        inset 1px 1px 0.5px -1px rgba(255, 255, 255, 0.14),
        0 0 0 1px rgba(229, 173, 83, 0.35);
}

.narration-card :deep(p) {
    margin-bottom: 1rem;
}

.narration-card :deep(p:last-child) {
    margin-bottom: 0;
}

.narration-card :deep(blockquote) {
    border-left: 1px solid rgba(255, 255, 255, 0.6);
    padding-left: 0.75rem;
    margin: 1rem 0;
    font-style: italic;
}

.narration-card :deep(em) {
    font-style: italic;
    color: var(--color-primary);
}

.narration-card :deep(strong) {
    font-weight: 600;
    color: var(--color-primary);
}
</style>
