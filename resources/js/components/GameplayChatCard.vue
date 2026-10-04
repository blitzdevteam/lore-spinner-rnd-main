<script setup lang="ts">
import AnimatedStreamingHtml from '@/components/AnimatedStreamingHtml.vue';
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import GameplayContinueIcon from '@/components/icons/GameplayContinueIcon.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { useTypewriter } from '@/composables/useTypewriter';
import { PromptChoice, PromptInterface } from '@/types';
import { glassTintVars } from '@/utils/color';
import { pricing } from '@/wayfinder/routes';
import { Link } from '@inertiajs/vue3';
import { LucideCheck, LucideLoader, LucidePause, LucidePlay } from 'lucide-vue-next';
import { computed, onMounted, watch } from 'vue';

const CONTINUE_MARKER = '__continue__';

const { settings: gameplaySettings } = useGameplaySettings();

const panelGlassStyle = computed(() => glassTintVars(gameplaySettings.backgroundColor));

const props = withDefaults(
    defineProps<{
        prompt: PromptInterface;
        gameId: string;
        isLatest?: boolean;
        pendingChoice?: string;
        isSubmitting?: boolean;
        actionsDisabled?: boolean;
        animate?: boolean;
        isStreamingResponse?: boolean;
        usesPlayableMoments?: boolean;
        narrationOnly?: boolean;
        voiceOverEnabled?: boolean;
    }>(),
    {
        voiceOverEnabled: true,
    },
);

const emit = defineEmits<{
    'choice-selected': [promptId: string, suggestionIndex: number, choice: PromptChoice];
    continue: [];
    /** Fired once when the choice buttons first become visible on this (latest) card. */
    'choices-ready': [];
    /** Fired when the typewriter finishes revealing this card's narration. */
    'narration-finished': [];
}>();

const tts = useTextToSpeech();
const typewriter = useTypewriter();
const voiceOverEnabled = computed(() => props.voiceOverEnabled);

const effectiveSelection = computed(() => {
    return props.prompt.prompt || props.pendingChoice || null;
});

const isContinued = computed(() => {
    return effectiveSelection.value === CONTINUE_MARKER;
});

const canInteract = computed(() => {
    return props.isLatest && !effectiveSelection.value && !props.isSubmitting && !props.actionsDisabled;
});

const hasChoices = computed(() => (props.prompt.choices?.length ?? 0) > 0);
const normalizedChoices = computed(() =>
    (props.prompt.choices ?? []).map((choice, index) => ({
        index,
        raw: choice,
        text: typeof choice === 'string' ? choice : choice.text,
    })),
);

// Show the player's action as soon as it is selected or submitted. During a
// streamed turn this is sourced from pendingChoice before the server commits it.
const showPlayerAction = computed(() => !!effectiveSelection.value);
const playerActionLabel = computed(() => {
    return isContinued.value ? 'Continue story' : effectiveSelection.value ?? '';
});

// Continue is always available when the player can interact and a response exists.
// In Boiler this means "let the story move without me steering" — a legitimate,
// low-effort way to stay in the scene rather than being forced to invent an action.
const showContinueButton = computed(() => {
    return canInteract.value && !!props.prompt.response && !props.narrationOnly;
});

const renderedResponse = computed(() => {
    if (props.isStreamingResponse || props.animate) {
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

const showChoiceList = computed(() => hasChoices.value && showChoicesAndActions.value && !props.narrationOnly);

const showFreeformNote = computed(() => canInteract.value && showChoicesAndActions.value && !props.narrationOnly);

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
        if (props.actionsDisabled) {
            return `${base} cursor-not-allowed border-white/5 text-gray-500 opacity-55`;
        }

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
    if (!voiceOverEnabled.value || listenDisabled.value) return;
    tts.toggle(props.gameId, props.prompt.id);
};

const voiceUpgradeNotice = computed(() =>
    tts.playbackNoticeKey.value === thisKey.value ? tts.playbackNotice.value : null,
);

const handleNarrationClick = () => {
    if (typewriter.isTyping.value && props.prompt.response) {
        typewriter.skipToEnd(props.prompt.response);
    }
};

// Start typing and, in parallel, warm the narration audio so it is downloaded
// by the time the text finishes.
const beginNarration = (response: string) => {
    typewriter.start(response);
    if (voiceOverEnabled.value && gameplaySettings.autoplay) {
        tts.prefetch(props.gameId, props.prompt.id);
    }
};

onMounted(() => {
    if (props.isStreamingResponse && props.prompt.response) {
        typewriter.streamTo(props.prompt.response);
    } else if (props.animate && props.prompt.response) {
        beginNarration(props.prompt.response);
    } else if (props.prompt.response) {
        typewriter.complete(props.prompt.response);
        if (voiceOverEnabled.value && props.isLatest && gameplaySettings.autoplay) {
            tts.autoplay(props.gameId, props.prompt.id);
        }
    }
});

watch(
    () => props.prompt.response,
    (newVal) => {
        if (!newVal) return;
        if (props.isStreamingResponse) {
            typewriter.streamTo(newVal);
        } else if (props.animate && props.isLatest) {
            beginNarration(newVal);
        } else {
            typewriter.complete(newVal);
        }
    },
);

watch(
    () => props.animate,
    (animate, wasAnimate) => {
        if (!animate || wasAnimate || !props.prompt.response) return;
        beginNarration(props.prompt.response);
    },
);

// Autoplay: once the typewriter finishes, play the narration.
// Works the same on desktop and mobile — text must be fully generated first.
// skipToEnd() also triggers this (cancel() sets isTyping → false).
watch(
    () => typewriter.isTyping.value,
    (isTyping, wasTyping) => {
        if (isTyping || !wasTyping) return; // only on true → false transition
        emit('narration-finished');
        if (!props.isLatest || !props.animate) return;
        if (!voiceOverEnabled.value || !gameplaySettings.autoplay) return;
        tts.autoplay(props.gameId, props.prompt.id);
    },
);

watch(
    voiceOverEnabled,
    (enabled) => {
        if (!enabled) {
            tts.dismiss();
        }
    },
    { immediate: true },
);

// Nudge trigger: fire once when this card first becomes actionable. Not conditioned
// on listed choices — the player can always act by speaking or writing.
const choicesVisible = computed(
    () => !props.narrationOnly && !!props.isLatest && canInteract.value && showChoicesAndActions.value && !!props.prompt.response,
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
            <div
                class="text-justify leading-relaxed font-normal tracking-[0.04em]"
                style="font-size: inherit"
            >
                <template v-if="props.isStreamingResponse">
                    <AnimatedStreamingHtml :html="renderedResponse ?? ''" />
                    <span class="ml-0.5 inline-block h-5 w-0.5 animate-pulse bg-primary-400 align-middle" />
                </template>
                <div v-else v-html="renderedResponse"></div>
            </div>
            <span v-if="typewriter.isTyping.value && !props.isStreamingResponse" class="mt-1 inline-block h-5 w-0.5 animate-pulse bg-primary-400 align-middle" />
        </div>

        <!-- ── Listen Again + Continue ── -->
        <div v-if="showResponseActions && (voiceOverEnabled || showContinueButton)" class="flex flex-row flex-wrap items-center gap-1.5">
            <button
                v-if="voiceOverEnabled"
                type="button"
                class="narration-action-pill bg-glass-effect flex h-[50px] w-auto min-w-0 items-center gap-2 overflow-hidden rounded-full p-1.5 pe-4 sm:pe-5 bg-[#33333330]"
                :class="{ 'cursor-not-allowed opacity-55': listenDisabled }"
                :title="listenTitle"
                :disabled="listenDisabled"
                :aria-disabled="listenDisabled"
                @click="handleListenAgain"
            >
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-primary-600 text-white">
                    <LucideLoader v-if="isThisLoading" class="size-4 animate-spin" />
                    <LucidePause v-else-if="isThisPlaying" class="size-4" fill="currentColor" />
                    <LucidePlay v-else class="size-4" fill="currentColor" />
                </span>
                <span class="flex min-w-0 flex-col items-start leading-tight tracking-[0.5px]">
                    <span class="text-sm text-primary-600">{{ hasPlayedOnce ? 'Listen Again' : 'Listen' }}</span>
                    <span class="text-xs font-light text-[#7e7e7e]">{{ hasPlayedOnce ? 'Replay Narration' : 'Play Narration' }}</span>
                </span>
            </button>

            <p
                v-if="voiceUpgradeNotice"
                class="basis-full rounded-xl border border-primary-400/30 bg-primary-400/10 px-4 py-3 text-sm text-primary-100"
            >
                {{ voiceUpgradeNotice }}
                <Link :href="pricing().url" class="font-medium text-primary underline">View plans</Link>
            </p>

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

        <!-- ── Player's action echo ── -->
        <div v-if="showPlayerAction" class="border-l-2 border-primary py-2 pl-4">
            <p class="text-base text-primary italic">"{{ playerActionLabel }}"</p>
        </div>

        <!-- ── Choices ── -->
        <div v-if="showChoiceList" class="flex flex-col gap-4">
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
        </div>

        <!-- Off-path note — shown whenever the player can act, with or without
             listed suggestions, so freeform never looks like a fallback -->
        <div v-if="showFreeformNote" class="flex flex-col gap-0.5">
            <p class="text-base text-secondary-300">Want To Go Off-Path?</p>
            <p class="text-xs font-light tracking-wide text-gray-400">Speak Or Write Your Own Choice.</p>
        </div>
    </div>
</template>

<style scoped>
.narration-action-pill {
    isolation: isolate;
    contain: paint;
    clip-path: inset(0 round 60px);
    -webkit-mask-image: -webkit-radial-gradient(white, black);
    backface-visibility: hidden;
    transform: translateZ(0);
    transition:
        transform 150ms ease,
        color 150ms ease;
}

.narration-action-pill:hover {
    transform: translateZ(0) scale(1.02);
}

.narration-action-pill:active {
    transform: translateZ(0) scale(0.98);
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

.narration-card :deep(.streaming-word) {
    animation: streaming-word-reveal 320ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes streaming-word-reveal {
    from {
        opacity: 0;
        filter: blur(3px);
    }

    to {
        opacity: 1;
        filter: blur(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .narration-card :deep(.streaming-word) {
        animation: none;
    }
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
