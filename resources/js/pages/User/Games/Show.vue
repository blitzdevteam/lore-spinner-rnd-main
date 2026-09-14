<script setup lang="ts">
import GameCinematicOpening from '@/components/GameCinematicOpening.vue';
import GameCinematicOutro from '@/components/GameCinematicOutro.vue';
import GameplayChatCard from '@/components/GameplayChatCard.vue';
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import GameplayLayout from '@/layouts/GameplayLayout.vue';
import { GameInterface, GameplayUsageSummary, PromptChoice, PromptInterface, SubscriptionUsageSummary } from '@/types';
import { store as storePrompt } from '@/wayfinder/actions/App/Http/Controllers/User/Game/PromptController';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const CONTINUE_MARKER = '__continue__';
const TURN_FAILED_MESSAGE = 'Narration hiccuped — your move was not saved. Retry to send it again.';
const TURN_REJECTED_MESSAGE = 'That move arrived out of turn — retry to send it again.';

type SubmitOptions = {
    promptId: string;
    suggestionIndex?: number;
    canonicalChoiceId?: string | null;
    canonicalPathLabel?: 'A' | 'B' | 'C' | null;
};

const props = defineProps<{
    game: GameInterface;
    usage: GameplayUsageSummary;
    subscriptionUsage?: SubscriptionUsageSummary | null;
}>();

const tts = useTextToSpeech();
const { settings: gameplaySettings } = useGameplaySettings();

const handleBack = () => {
    tts.dismiss();
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
};

function visitLeavesThisGame(url: string | { toString?: () => string; href?: string } | null | undefined): boolean {
    const raw =
        typeof url === 'string'
            ? url
            : typeof url?.href === 'string'
              ? url.href
              : typeof url?.toString === 'function'
                ? url.toString()
                : '';
    const path = raw.split('?')[0] ?? '';
    return !path.startsWith(`/user/games/${props.game.id}`);
}

let removeNavigationListener: (() => void) | undefined;

const isSubmitting = ref(false);
const beginSettled = ref(false);
const isRetryingBegin = ref(false);
const isStartingNextSession = ref(false);
const pendingSelection = ref<Record<string, string>>({});
const shouldAnimate = ref(false);
const isStreamingTurn = ref(false);
const streamingPromptId = ref<string | null>(null);
const localPrompts = ref<PromptInterface[]>([...(props.game.prompts ?? [])]);
const promptCardElements = ref<Record<string, HTMLElement | null>>({});
const streamError = ref<string | null>(null);
const lastAttempt = ref<{ prompt: string; options: SubmitOptions } | null>(null);

/**
 * A game refresh is fire-and-forget, so its response can land after a later turn
 * has already been committed locally. The server list is authoritative for every
 * turn it contains, but a committed turn it has not caught up with has to
 * survive the merge: deleting narration the player has already read is far worse
 * than showing it a moment before the server agrees it exists.
 */
function mergeServerPrompts(serverPrompts: PromptInterface[]): PromptInterface[] {
    const localById = new Map(localPrompts.value.map((item) => [String(item.id), item]));
    const serverIds = new Set(serverPrompts.map((item) => String(item.id)));

    const reconciled = serverPrompts.map((item) => {
        const local = localById.get(String(item.id));
        // A committed player action the server has not returned yet.
        return local?.prompt && !item.prompt ? { ...item, prompt: local.prompt } : item;
    });

    // Only turns newer than everything the server returned. Prompt ids are ULIDs, so
    // this is a time comparison, and it keeps the merge from resurrecting a turn the
    // server deliberately removed — a reset or a rewind still takes effect.
    const newestServerId = serverPrompts.length > 0 ? String(serverPrompts[serverPrompts.length - 1].id) : '';
    const newerLocalOnly = localPrompts.value.filter(
        (item) =>
            item.id !== streamingPromptId.value
            && !serverIds.has(String(item.id))
            && String(item.id) > newestServerId,
    );

    return [...reconciled, ...newerLocalOnly];
}

watch(
    () => props.game.prompts,
    (nextPrompts) => {
        if (isStreamingTurn.value) return;
        localPrompts.value = mergeServerPrompts([...(nextPrompts ?? [])]);
    },
    { deep: true },
);

const prompts = computed(() => localPrompts.value);

/**
 * The card the player is answering: the newest turn with no committed action yet.
 * Derived from the data rather than array position because the server accepts no
 * other source prompt, and answers a mismatch with a redirect the turn stream
 * cannot parse.
 */
const awaitingPrompt = computed(() => {
    return (
        [...prompts.value]
            .reverse()
            .find((item) => item.id !== streamingPromptId.value && !item.prompt) ?? null
    );
});
const hasVisibleStreamedResponse = computed(() => {
    if (!streamingPromptId.value) return false;

    const streamingPrompt = prompts.value.find((prompt) => prompt.id === streamingPromptId.value);

    return hasVisibleStreamedText(streamingPrompt?.response ?? '');
});
const latestPromptId = computed(() => {
    const latestPrompt = prompts.value[prompts.value.length - 1];
    return latestPrompt ? String(latestPrompt.id) : null;
});
const hasPrompts = computed(() => prompts.value.length > 0);
const sessionComplete = computed(() => props.game.current_session_complete === true);
const isLastSession = computed(() => {
    const total = props.game.total_sessions ?? 0;
    const current = props.game.current_session_number ?? 1;
    return total > 0 && current >= total;
});

const episodePosition = computed(() => props.game.currentEvent?.chapter?.position);

const journalMeta = computed(() => ({
    storyTitle: props.game.story?.title,
    episodeLabel: episodePosition.value != null ? `Episode ${episodePosition.value}` : null,
    sessionNumber: props.game.current_session_number,
    turnCount: prompts.value.length,
    sessionComplete: sessionComplete.value,
}));

function stripHtml(html: string): string {
    const input = typeof html === 'string' ? html : '';
    if (typeof document === 'undefined') {
        return input.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }
    const el = document.createElement('div');
    el.innerHTML = input;
    return (el.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function hasVisibleStreamedText(html: string): boolean {
    return html
        .replace(/<[^>]*>/g, ' ')
        .replace(/&(?:nbsp|#160);/gi, ' ')
        .trim().length > 0;
}

function excerpt(text: string, max = 120): string {
    const plain = stripHtml(text);
    if (plain.length <= max) return plain;
    return `${plain.slice(0, max).trim()}…`;
}

// Cinematic outro: shown when the full story is completed
const page = usePage();
const showOutro = computed(() => (page.props.flash as Record<string, unknown>)?.story_complete === true);
const handleOutroDone = () => {
    tts.dismiss();
    router.visit(storiesIndex().url, { replace: true });
};

// Cinematic opening: shown on first visit (no prompts yet); hidden once begin fires
const showCinematic = ref(!hasPrompts.value && !showOutro.value);
const cameFromCinematic = ref(!hasPrompts.value);
const openingNarrationPlayed = ref(false);

// Auto-play the first narration while the showcard is still visible (or when begin finishes).
watch(
    [() => prompts.value[0]?.response, shouldAnimate],
    () => {
        if (openingNarrationPlayed.value) return;

        const response = prompts.value[0]?.response;
        if (!response) return;

        const isOpeningTurn = prompts.value.length === 1;
        const shouldPlayFirst = cameFromCinematic.value || (shouldAnimate.value && isOpeningTurn);
        if (!shouldPlayFirst) return;
        if (!gameplaySettings.autoplay) {
            cameFromCinematic.value = false;
            return;
        }

        const first = prompts.value[0];
        if (!first) return;
        cameFromCinematic.value = false;
        openingNarrationPlayed.value = true;
        tts.play(String(props.game.id), String(first.id));
    },
);

// Subsequent-prompt autoplay is owned by the typewriter-completion watcher in
// GameplayChatCard.vue — it fires once text is fully generated, same on all platforms.

// Onboarding staging — the first two moves of a game teach the two ways to play,
// one at a time, before both are offered together.
//   Move 1: no choice buttons. The only way forward is to speak or type.
//   Move 2: choice buttons only. The chat bar is disabled.
//   Move 3+: both, which is the normal game from here on.
// `prompts` spans every session of this game, so the staging runs once per game
// and never reappears at a later session boundary.
//
// Two different lifetimes here. The chat bar is a single live control, so it reads
// the stage the game is in NOW (below). A card's staging belongs to its own turn and
// is permanent: the first card was authored without buttons, so it must never grow
// them once later turns arrive. That per-card rule is bound by index in the template.
const introStage = computed(() => {
    if (prompts.value.length === 1) return 1;
    if (prompts.value.length === 2) return 2;
    return 0;
});

const freeformLocked = computed(() => introStage.value === 2);

const hasActionAllowance = computed(() => {
    if (!props.subscriptionUsage) return true;

    return props.subscriptionUsage.actions.remaining > 0;
});

const canSubmitInput = computed(() => {
    if (isSubmitting.value) return false;
    if (!hasActionAllowance.value) return false;
    if (sessionComplete.value) return false;
    if (freeformLocked.value) return false;
    const latest = prompts.value[prompts.value.length - 1];
    return latest && !latest.prompt;
});

// Show the story-native freeform hint on the player's very first move ever.
// Once they act (typed or chose a button), the placeholder empties for the rest of the game.
const hasPlayerActed = computed(() => prompts.value.some((p) => !!p.prompt));

const isFirstChoiceMoment = computed(() =>
    !hasPlayerActed.value &&
    canSubmitInput.value &&
    (props.game.current_session_number ?? 1) === 1,
);

const inputPlaceholder = computed(() => {
    if (!hasActionAllowance.value) return 'Your action allowance has been reached for this billing period.';
    if (freeformLocked.value) return 'Pick one of the paths above for this move.';
    if (!isFirstChoiceMoment.value) return '';
    return props.game.first_input_hint ?? '';
});

// Input-bar nudge: increments each time choice buttons appear during the first move.
// GameplayLayout watches this counter and starts the escalating twinkle loop.
const nudgeTrigger = ref(0);

function handleChoicesReady() {
    nudgeTrigger.value++;
}

const handleBegin = () => {
    router.post(
        `/user/games/${props.game.id}/begin`,
        {},
        {
            preserveScroll: false,
            onSuccess: () => {
                void pollForOpeningCompletion('begin');
            },
            onFinish: () => {
                // beginSettled flips when opening narration lands (or errors).
            },
        },
    );
};

const handleNextSession = () => {
    if (isStartingNextSession.value) return;
    tts.primeAudio();
    isStartingNextSession.value = true;
    const priorSession = props.game.current_session_number ?? 1;

    router.post(
        `/user/games/${props.game.id}/next-session`,
        {},
        {
            preserveScroll: false,
            onSuccess: () => {
                void pollForOpeningCompletion('next', priorSession);
            },
            onFinish: () => {
                // Cleared by the opening poller.
            },
        },
    );
};

const handleChoiceSelected = (promptId: string, suggestionIndex: number, choice: PromptChoice) => {
    if (isSubmitting.value || !hasActionAllowance.value) return;
    tts.primeAudio();
    const text = typeof choice === 'string' ? choice : choice.text;
    pendingSelection.value[promptId] = text;
    submitPrompt(text, {
        promptId,
        suggestionIndex,
        canonicalChoiceId: typeof choice === 'string' ? null : choice.canonical_choice_id,
        canonicalPathLabel: typeof choice === 'string' ? null : choice.canonical_path_label,
    });
};

const handleContinue = () => {
    if (isSubmitting.value || !hasActionAllowance.value) return;
    tts.primeAudio();
    const source = awaitingPrompt.value;
    if (!source) return;
    pendingSelection.value[source.id] = CONTINUE_MARKER;
    submitPrompt(CONTINUE_MARKER, { promptId: source.id });
};

const handleSubmit = (prompt: string) => {
    if (isSubmitting.value || !hasActionAllowance.value) return;
    tts.primeAudio();
    const source = awaitingPrompt.value;
    if (!source) return;
    submitPrompt(prompt, { promptId: source.id });
};

const handleRetryTurn = () => {
    const attempt = lastAttempt.value;
    if (isSubmitting.value || !hasActionAllowance.value || !attempt) return;
    tts.primeAudio();

    // The source card may have been reissued by a refresh since the failure.
    const source = awaitingPrompt.value;
    submitPrompt(attempt.prompt, {
        ...attempt.options,
        promptId: source ? source.id : attempt.options.promptId,
    });
};

const submitPrompt = (prompt: string, options: SubmitOptions) => {
    void streamPrompt(prompt, options);
};

function csrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return cookie ? decodeURIComponent(cookie) : '';
}

function appendToStreamingPrompt(promptId: string, text: string) {
    localPrompts.value = localPrompts.value.map((item) =>
        item.id === promptId ? { ...item, response: `${item.response ?? ''}${text}` } : item,
    );
}

function setPromptCardElement(promptId: string, element: unknown) {
    promptCardElements.value[promptId] = element instanceof HTMLElement ? element : null;
}

function scrollToPromptStart(promptId: string) {
    const element = promptCardElements.value[promptId];
    if (!element) return;

    const stickyHeaderOffset = 112;
    const top = Math.max(0, window.scrollY + element.getBoundingClientRect().top - stickyHeaderOffset);

    window.scrollTo({ top, behavior: 'smooth' });
}

function commitStreamedPrompt(
    streamingPromptId: string,
    sourcePromptId: string,
    sourcePromptValue: string,
    responsePrompt: PromptInterface,
) {
    localPrompts.value = localPrompts.value
        .map((item) => (item.id === sourcePromptId ? { ...item, prompt: sourcePromptValue } : item))
        .filter((item) => item.id !== streamingPromptId);

    localPrompts.value = [...localPrompts.value, responsePrompt];
}

async function streamPrompt(prompt: string, options: SubmitOptions) {
    isSubmitting.value = true;
    isStreamingTurn.value = true;
    shouldAnimate.value = false;
    streamError.value = null;
    lastAttempt.value = { prompt, options };

    const activeStreamingPromptId = `stream-${options.promptId}`;
    streamingPromptId.value = activeStreamingPromptId;
    localPrompts.value = [
        ...localPrompts.value.filter((item) => item.id !== activeStreamingPromptId),
        {
            id: activeStreamingPromptId,
            game_id: props.game.id,
            session_number: props.game.current_session_number ?? null,
            prompt: null,
            response: '',
            choices: [],
            created_at: null,
            updated_at: null,
        },
    ];

    try {
        const route = storePrompt(props.game.id);
        const response = await fetch(typeof route === 'string' ? route : route.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/x-ndjson',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                prompt,
                prompt_id: options.promptId,
                suggestion_index: options.suggestionIndex,
                canonical_choice_id: options.canonicalChoiceId ?? null,
                canonical_path_label: options.canonicalPathLabel ?? null,
                stream: true,
            }),
        });

        if (response.status === 429 || response.status === 403) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
                reason?: string;
                pricing_url?: string;
            } | null;

            if (response.status === 403 && payload?.reason === 'subscription_required' && payload.pricing_url) {
                isSubmitting.value = false;
                isStreamingTurn.value = false;
                streamingPromptId.value = null;
                pendingSelection.value = {};
                localPrompts.value = localPrompts.value.filter((item) => item.id !== activeStreamingPromptId);
                router.visit(payload.pricing_url);

                return;
            }

            throw new Error(
                payload?.message
                    ?? (response.status === 429
                        ? 'Your action allowance has been reached for this billing period.'
                        : 'An active subscription is required to keep playing.'),
            );
        }

        if (!response.ok || !response.body) {
            throw new Error(TURN_FAILED_MESSAGE);
        }

        // A rejected turn is answered with a redirect to the game page, which
        // arrives as HTML. Reading it as NDJSON would throw on the first line and
        // surface as a generic failure, so name it while we still can.
        if (!(response.headers.get('Content-Type') ?? '').includes('application/x-ndjson')) {
            throw new Error(TURN_REJECTED_MESSAGE);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let completed = false;
        let anchoredToStreamStart = false;

        const handleStreamEvent = (line: string) => {
            if (!line.trim()) return;

            const event = JSON.parse(line) as {
                event: 'ready' | 'delta' | 'done' | 'error';
                text?: string;
                message?: string;
                source_prompt_id?: string;
                source_prompt_value?: string;
                prompt?: PromptInterface;
            };

            if (event.event === 'delta' && event.text) {
                appendToStreamingPrompt(activeStreamingPromptId, event.text);
                if (!anchoredToStreamStart) {
                    anchoredToStreamStart = true;
                    nextTick(() => {
                        scrollToPromptStart(activeStreamingPromptId);
                    });
                }
            }

            if (event.event === 'done' && event.prompt) {
                commitStreamedPrompt(
                    activeStreamingPromptId,
                    event.source_prompt_id ?? options.promptId,
                    event.source_prompt_value ?? prompt,
                    event.prompt,
                );
                completed = true;
                isSubmitting.value = false;
                isStreamingTurn.value = false;
                streamingPromptId.value = null;
                pendingSelection.value = {};
                lastAttempt.value = null;
                // Do not flip shouldAnimate here. streamTo already revealed the text;
                // setting animate remounts the committed card and restarts the typewriter
                // from empty — the vanish-then-rerender glitch.
                router.reload({ only: ['game', 'usage', 'subscriptionUsage'] });
            }

            if (event.event === 'error') {
                throw new Error(event.message ?? TURN_FAILED_MESSAGE);
            }
        };

        while (true) {
            const { value, done } = await reader.read();
            if (done) {
                buffer += decoder.decode();
                break;
            }

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() ?? '';

            for (const line of lines) {
                handleStreamEvent(line);
            }
        }

        if (buffer.trim()) {
            handleStreamEvent(buffer);
        }

        if (!completed) {
            throw new Error(TURN_FAILED_MESSAGE);
        }
    } catch (error) {
        isSubmitting.value = false;
        isStreamingTurn.value = false;
        streamingPromptId.value = null;
        pendingSelection.value = {};
        localPrompts.value = localPrompts.value.filter((item) => item.id !== activeStreamingPromptId);
        // Say so. A turn that vanishes with no explanation reads as a broken game
        // rather than a failed request, and the player retypes instead of retrying.
        streamError.value = error instanceof Error && error.message ? error.message : TURN_FAILED_MESSAGE;
        router.reload({ only: ['game', 'subscriptionUsage'] });
    }
}

async function pollForOpeningCompletion(kind: 'begin' | 'next', priorSession?: number) {
    const started = Date.now();
    const maxMs = 180_000;
    let delayMs = 400;

    while (Date.now() - started < maxMs) {
        await new Promise((resolve) => setTimeout(resolve, delayMs));
        delayMs = Math.min(800, delayMs + 100);

        await new Promise<void>((resolve) => {
            router.reload({
                only: ['game'],
                onFinish: () => resolve(),
            });
        });

        const openingError = props.game.opening_error;
        const stillPending = props.game.opening_pending === true;

        if (openingError) {
            showCinematic.value = false;
            beginSettled.value = true;
            isRetryingBegin.value = false;
            isStartingNextSession.value = false;
            return;
        }

        if (kind === 'begin' && hasPrompts.value && !stillPending) {
            beginSettled.value = true;
            isRetryingBegin.value = false;
            shouldAnimate.value = true;
            nextTick(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            return;
        }

        if (
            kind === 'next'
            && !stillPending
            && (props.game.current_session_number ?? 1) > (priorSession ?? 0)
        ) {
            isStartingNextSession.value = false;
            shouldAnimate.value = true;
            nextTick(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            return;
        }

        // Story-complete flash path: next-session finished the arc without a new opening.
        if (kind === 'next' && !stillPending && showOutro.value) {
            isStartingNextSession.value = false;
            return;
        }
    }

    beginSettled.value = true;
    isRetryingBegin.value = false;
    isStartingNextSession.value = false;
}

async function pollForTurnCompletion(sourcePromptId: string) {
    const started = Date.now();
    const maxMs = 180_000;
    let delayMs = 400;

    while (Date.now() - started < maxMs) {
        await new Promise((resolve) => setTimeout(resolve, delayMs));
        // Start quick so a finished turn doesn't sit for 1.5s; ease up slightly to spare the server.
        delayMs = Math.min(800, delayMs + 100);

        await new Promise<void>((resolve) => {
            router.reload({
                only: ['game'],
                onFinish: () => resolve(),
            });
        });

        const source = prompts.value.find((p) => p.id === sourcePromptId);
        const turnError = props.game.turn_error;
        const stillPending = props.game.turn_pending === true;

        if (turnError) {
            isSubmitting.value = false;
            pendingSelection.value = {};
            return;
        }

        if (source?.prompt && !stillPending) {
            isSubmitting.value = false;
            pendingSelection.value = {};
            shouldAnimate.value = true;
            nextTick(() => {
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            });
            return;
        }
    }

    isSubmitting.value = false;
    pendingSelection.value = {};
}

onMounted(() => {
    removeNavigationListener = router.on('before', (event) => {
        try {
            if (visitLeavesThisGame(event.detail?.visit?.url)) {
                tts.dismiss();
            }
        } catch {
            // Never break Inertia navigation.
        }
    });

    if (hasPrompts.value) {
        showCinematic.value = false;
        nextTick(() => {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        });
    }

    // Resume polling after refresh if a deferred turn/opening is still running.
    if (props.game.turn_pending) {
        const awaiting = [...prompts.value].reverse().find((p) => !p.prompt);
        if (awaiting) {
            isSubmitting.value = true;
            pendingSelection.value[awaiting.id] = pendingSelection.value[awaiting.id] ?? '…';
            void pollForTurnCompletion(awaiting.id);
        }
    } else if (props.game.opening_pending) {
        if (!hasPrompts.value) {
            void pollForOpeningCompletion('begin');
        } else {
            isStartingNextSession.value = true;
            void pollForOpeningCompletion('next', Math.max(0, (props.game.current_session_number ?? 1) - 1));
        }
    }
});

const handleCinemaPrepare = () => {
    handleBegin();
};

const handleRetryBegin = () => {
    tts.primeAudio();
    beginSettled.value = false;
    isRetryingBegin.value = true;
    handleBegin();
};

const handleCinematicDone = () => {
    showCinematic.value = false;
};

onUnmounted(() => {
    removeNavigationListener?.();
});
</script>

<template>
    <!-- ── Cinematic outro sequence (story completed) ── -->
    <GameCinematicOutro v-if="showOutro" :outro-poster="props.game.story?.outro_poster ?? null" @done="handleOutroDone" />

    <!-- ── Cinematic opening sequence (new games only) ── -->
    <GameCinematicOpening
        v-else-if="showCinematic"
        :can-exit="beginSettled"
        @prepare="handleCinemaPrepare"
        @done="handleCinematicDone"
    />

    <!-- Begin failed — show retry -->
    <div v-else-if="(beginSettled && !hasPrompts) || game.opening_error" class="grid h-svh place-items-center bg-gray-950">
        <div class="flex flex-col items-center gap-6 text-center">
            <p class="text-sm text-gray-400">
                {{ game.opening_error || 'Opening narration hiccuped — please retry.' }}
            </p>
            <button
                class="rounded-full border border-primary-400/60 bg-primary-400/10 px-8 py-3 text-sm font-medium text-primary-300 transition hover:bg-primary-400/20 hover:text-primary-200 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="isRetryingBegin"
                @click="handleRetryBegin"
            >
                <span v-if="isRetryingBegin" class="flex items-center justify-center gap-2">
                    <span class="size-4 animate-spin rounded-full border-2 border-primary-400 border-t-transparent" />
                    Retrying…
                </span>
                <span v-else>Retry</span>
            </button>
        </div>
    </div>

    <!-- ── Gameplay phase ── -->
    <GameplayLayout
        v-else
        :input-disabled="!canSubmitInput"
        :input-locked-by-design="freeformLocked"
        :input-placeholder="inputPlaceholder"
        :nudge-trigger="nudgeTrigger"
        :game-id="game.id"
        :usage="usage"
        :subscription-usage="props.subscriptionUsage"
        :latest-prompt-id="latestPromptId"
        :cover-url="game.story?.cover ?? null"
        :story-slug="game.story?.slug ?? null"
        :journal-meta="journalMeta"
        @submit="handleSubmit"
        @back="handleBack"
    >
        <template #header>
            <div class="flex flex-col items-center gap-3 pt-2 pb-4">
                <h1 class="text-center text-2xl font-semibold text-white md:text-[28px]">
                    {{ game.story?.title ?? 'Adventure' }}
                </h1>
                <GameplayOrnamentDivider
                    v-if="journalMeta.episodeLabel"
                    :label="journalMeta.episodeLabel"
                    color="#ffffff"
                />
                <span v-if="game.current_session_number" class="rounded-full bg-gray-800 px-2 py-1 text-sm text-gray-300">
                    Session {{ game.current_session_number }}
                </span>
            </div>
        </template>

        <template #game>
            <p v-if="game.turn_error" class="mb-4 text-center text-sm text-red-300/90">
                {{ game.turn_error }}
            </p>

            <!-- A failed turn stream: name it and offer the move back, rather than
                 letting the narration vanish with no explanation. -->
            <div
                v-if="streamError"
                class="mb-4 flex flex-col items-center gap-3 rounded-xl border border-red-400/30 bg-red-400/5 px-4 py-3 text-center"
            >
                <p class="text-sm text-red-300/90">{{ streamError }}</p>
                <button
                    v-if="lastAttempt && hasActionAllowance"
                    class="rounded-full border border-primary-400/60 bg-primary-400/10 px-6 py-2 text-sm font-medium text-primary-300 transition hover:bg-primary-400/20 hover:text-primary-200 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="isSubmitting"
                    @click="handleRetryTurn"
                >
                    Retry that move
                </button>
            </div>

            <p
                v-if="!hasActionAllowance"
                class="mb-4 rounded-xl border border-primary-400/30 bg-primary-400/10 px-4 py-3 text-center text-sm text-primary-100"
            >
                Your action allowance has been reached for this billing period. Your allowance will renew at the end of the period.
            </p>

            <div
                v-for="(prompt, index) in prompts"
                :key="prompt.id"
                :ref="(element) => setPromptCardElement(prompt.id, element)"
            >
                <GameplayChatCard
                    :prompt="prompt"
                    :game-id="game.id"
                    :cover-url="game.story?.cover ?? null"
                    :is-latest="prompt.id === prompts[prompts.length - 1]?.id && !sessionComplete"
                    :pending-choice="pendingSelection[prompt.id]"
                    :is-submitting="isSubmitting"
                    :actions-disabled="!hasActionAllowance"
                    :animate="shouldAnimate && prompt.id === prompts[prompts.length - 1]?.id"
                    :is-streaming-response="prompt.id === streamingPromptId"
                    :uses-playable-moments="game.uses_playable_moments"
                    :choices-locked="index === 0"
                    :freeform-locked="index === 1"
                    @choice-selected="handleChoiceSelected"
                    @continue="handleContinue"
                    @choices-ready="handleChoicesReady"
                />
            </div>

            <!-- Session complete — next chapter or end story -->
            <div v-if="sessionComplete && !isStartingNextSession" class="flex flex-col items-center gap-6 py-12 text-center">
                <div class="flex flex-col gap-2">
                    <p class="text-lg font-light text-gray-200">Session complete.</p>
                    <p v-if="!isLastSession" class="text-sm text-gray-500">
                        The story continues in the next chapter.
                    </p>
                </div>
                <button
                    class="rounded-full border border-primary-400/60 bg-primary-400/10 px-8 py-3 text-sm font-medium text-primary-300 transition hover:bg-primary-400/20 hover:text-primary-200"
                    @click="handleNextSession"
                >
                    {{ isLastSession ? 'Click to end story' : 'Continue to next chapter' }}
                </button>
            </div>

            <!-- Loading state for next-session call -->
            <div v-if="isStartingNextSession" class="flex flex-col items-center gap-4 py-12">
                <div class="size-8 animate-spin rounded-full border-2 border-primary-400 border-t-transparent" />
                <p class="text-sm text-gray-400">
                    {{ isLastSession ? 'Ending story…' : 'Opening the next chapter…' }}
                </p>
            </div>

            <!-- Loading skeleton while AI generates the next response -->
            <div v-if="isSubmitting && !hasVisibleStreamedResponse" class="py-8">
                <div class="flex animate-pulse flex-col gap-4">
                    <div class="h-4 w-full rounded bg-gray-700/50"></div>
                    <div class="h-4 w-5/6 rounded bg-gray-700/50"></div>
                    <div class="h-4 w-3/4 rounded bg-gray-700/50"></div>
                    <div class="h-4 w-4/6 rounded bg-gray-700/40"></div>
                </div>
            </div>
        </template>

        <template #journals>
            <!-- Desktop sidebar: classic session progress -->
            <div class="hidden flex-col gap-3 md:flex">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Session Progress</p>
                <div class="rounded-xl border border-gray-700/50 bg-gray-800/40 p-4">
                    <p class="text-sm text-gray-300">
                        Session <span class="font-medium text-primary-300">{{ game.current_session_number ?? 1 }}</span>
                    </p>
                    <p class="mt-1 text-xs text-gray-500">{{ prompts.length }} turn{{ prompts.length === 1 ? '' : 's' }} played this session</p>
                    <p v-if="sessionComplete" class="mt-2 text-xs text-primary-400">✓ Session complete</p>
                </div>
            </div>

            <div class="gp-timeline flex flex-col">
                <article
                    v-for="(prompt, index) in prompts"
                    :key="prompt.id"
                    class="gp-timeline__item relative flex gap-3 pb-5 last:pb-0"
                >
                    <div class="gp-timeline__rail flex flex-col items-center">
                        <span
                            class="gp-timeline__dot grid size-7 shrink-0 place-items-center rounded-full border border-primary/30 bg-primary/10 text-[10px] font-semibold tabular-nums text-primary-300"
                        >
                            {{ index + 1 }}
                        </span>
                        <span
                            v-if="index < prompts.length - 1"
                            class="gp-timeline__line mt-1 w-px flex-1 min-h-4 bg-gradient-to-b from-primary/30 to-transparent"
                        />
                    </div>
                    <div class="gp-timeline__card min-w-0 flex-1 rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                        <p
                            v-if="index === 0"
                            class="text-[10px] font-semibold uppercase tracking-wider text-gray-500"
                        >
                            Opening
                        </p>
                        <p
                            v-else-if="!prompt.prompt && index === prompts.length - 1"
                            class="text-[10px] font-semibold uppercase tracking-wider text-gray-500"
                        >
                            Now
                        </p>
                        <p
                            class="text-sm leading-relaxed text-gray-300"
                            :class="{ 'mt-2': index === 0 || (!prompt.prompt && index === prompts.length - 1) }"
                        >
                            {{ excerpt(prompt.response ?? '') }}
                        </p>
                        <p
                            v-if="prompt.prompt"
                            class="mt-2 border-t border-white/6 pt-2 text-xs font-medium text-primary-300/90"
                        >
                            You · {{ prompt.prompt === '__continue__' ? 'Continued' : prompt.prompt }}
                        </p>
                    </div>
                </article>
                <p v-if="!prompts.length" class="py-4 text-center text-sm text-gray-500">
                    Your story events will appear here as you play.
                </p>
            </div>
        </template>

        <template #characters>
            <p class="py-6 text-center text-sm text-gray-500">Coming soon</p>
        </template>
    </GameplayLayout>
</template>

<style scoped>
.gp-timeline__item:last-child .gp-timeline__line {
    display: none;
}
</style>
