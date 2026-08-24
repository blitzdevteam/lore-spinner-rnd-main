<script setup lang="ts">
import backIcon from '@/assets/arbiter/back.svg';
import billyAvatar from '@/assets/arbiter/billy-avatar.png';
import chatIcon from '@/assets/arbiter/chat.svg';
import copyIcon from '@/assets/arbiter/copy.svg';
import hourglassIcon from '@/assets/arbiter/hourglass.svg';
import kevinAvatar from '@/assets/arbiter/kevin-avatar.png';
import lightningIcon from '@/assets/arbiter/lightning.svg';
import miraAvatar from '@/assets/arbiter/mira-avatar.png';
import notesIcon from '@/assets/arbiter/notes.svg';
import playersIcon from '@/assets/arbiter/players.svg';
import questionIcon from '@/assets/arbiter/question.svg';
import sendIcon from '@/assets/arbiter/send.svg';
import settingsIcon from '@/assets/arbiter/settings.svg';
import BaseButton from '@/components/BaseButton.vue';
import GameplayChatCard from '@/components/GameplayChatCard.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import GameplayMediaPlayer from '@/components/GameplayMediaPlayer.vue';
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import type {
    GameInterface,
    MultiplayerArbiterStateInterface,
    MultiplayerLobbyInterface,
    MultiplayerLobbyMemberInterface,
    MultiplayerLobbyProposalInterface,
    PromptInterface,
} from '@/types';
import { show as showMultiplayerLobby } from '@/wayfinder/actions/App/Http/Controllers/User/MultiplayerLobbyController';
import { useEchoPresence } from '@laravel/echo-vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { LucideCrown, LucideList } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

defineOptions({ layout: null });

type PlayerTone = 'cyan' | 'gold';
type ChatTone = 'cyan' | 'fantasy' | 'green';

type Player = {
    id: string;
    name: string;
    role: string;
    state: string;
    avatar: string;
    tone: PlayerTone;
    host?: boolean;
};

type ChatMessage = {
    name: string;
    text: string;
    time: string;
    tone: ChatTone;
};

type ArbiterStateSignal = {
    lobby_id: string;
};

type ArbiterNarrationSignal = {
    lobby_id: string;
    source_prompt_id: string;
    phase: 'ready' | 'delta' | 'done' | 'error';
    text?: string;
    source_prompt_value?: string;
    prompt?: PromptInterface;
    message?: string;
};

const props = defineProps<{
    lobby: MultiplayerLobbyInterface;
    game: GameInterface | null;
    arbiter: MultiplayerArbiterStateInterface;
}>();

const page = usePage();
const tts = useTextToSpeech();
const gameplayBackgroundImage = computed(
    () => props.game?.story?.banner || props.lobby.story.banner || props.game?.story?.cover || props.lobby.story.cover || null,
);
const roomCode = computed(() => props.lobby.room_code);
const members = computed(() => props.lobby.members);
const currentUserId = computed(() => page.props.auth?.id ?? null);
const currentMember = computed(() => members.value.find((member) => member.user.id === currentUserId.value) ?? null);
const currentArbiter = computed(
    () => members.value.find((member) => member.id === props.arbiter.current_arbiter_member_id) ?? null,
);
const turnStatus = computed(() => props.arbiter.turn_status);
const isCurrentUserArbiter = computed(() => props.arbiter.is_current_user_arbiter);
const isCollectingProposals = computed(() => turnStatus.value === 'collecting_proposals');
const isAwaitingArbiter = computed(() => turnStatus.value === 'awaiting_arbiter');
const isNarrating = computed(() => turnStatus.value === 'narrating');
const isSessionComplete = computed(() => turnStatus.value === 'session_complete');
const isStoryComplete = computed(() => props.lobby.status === 'completed');
const isLastSession = computed(() => {
    const totalSessions = props.game?.total_sessions ?? 0;
    const currentSession = props.game?.current_session_number ?? 1;

    return totalSessions > 0 && currentSession >= totalSessions;
});
const nonArbiterMembers = computed(() =>
    members.value.filter((member) => member.id !== props.arbiter.current_arbiter_member_id),
);
const proposalByMemberId = computed(() =>
    new Map(props.arbiter.proposals.map((proposal) => [proposal.member_id, proposal])),
);
const ownProposal = computed(() => {
    const member = currentMember.value;

    return member ? proposalByMemberId.value.get(member.id) ?? null : null;
});
const availableProposals = computed(() =>
    props.arbiter.proposals.filter((proposal) => proposal.text !== null),
);
const canSubmitProposal = computed(() => {
    return !!(
        currentMember.value
        && !isCurrentUserArbiter.value
        && isCollectingProposals.value
        && !ownProposal.value
    );
});
const canChooseProposal = computed(
    () => isAwaitingArbiter.value && isCurrentUserArbiter.value && availableProposals.value.length > 0,
);
const canAdvanceSession = computed(() =>
    isSessionComplete.value && !isStoryComplete.value && props.lobby.is_host,
);
const decisionWindowSeconds = computed(() => props.lobby.settings.decision_window_seconds);
const streamingPromptId = ref<string | null>(null);
const localPrompts = ref<PromptInterface[]>([...(props.game?.prompts ?? [])]);
const promptCardElements = ref<Record<string, HTMLElement | null>>({});
const prompts = computed(() => localPrompts.value);
const latestPromptId = computed(() => prompts.value.at(-1)?.id ?? null);
const hasVisibleStreamedResponse = computed(() => {
    if (!streamingPromptId.value) {
        return false;
    }

    const streamingPrompt = prompts.value.find((prompt) => prompt.id === streamingPromptId.value);

    return !!streamingPrompt?.response && stripHtml(streamingPrompt.response).length > 0;
});

const messages = ref<ChatMessage[]>([
    { name: 'Billy (Host)', text: 'This place gives me chills', time: '10:20 PM', tone: 'cyan' },
    { name: 'Billy (Host)', text: 'Let’s explore!', time: '10:20 PM', tone: 'cyan' },
    { name: 'Mira', text: 'Remember, choose wisely. Your choices shape the story.', time: '10:22 PM', tone: 'fantasy' },
    { name: 'Kevin', text: 'Maybe there’s another way...', time: '10:22 PM', tone: 'green' },
]);

const chatDraft = ref('');
const copiedRoomCode = ref(false);
const isSubmittingProposal = ref(false);
const isSelectingProposal = ref(false);
const isAdvancingSession = ref(false);
const actionError = ref<string | null>(null);

function goBack(): void {
    tts.dismiss();
    window.history.back();
}

function stripHtml(value: string): string {
    if (typeof document === 'undefined') {
        return value.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    const element = document.createElement('div');
    element.innerHTML = value;

    return (element.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function mergeServerPrompts(serverPrompts: PromptInterface[]): PromptInterface[] {
    const localById = new Map(localPrompts.value.map((prompt) => [String(prompt.id), prompt]));
    const serverIds = new Set(serverPrompts.map((prompt) => String(prompt.id)));
    const reconciled = serverPrompts.map((prompt) => {
        const localPrompt = localById.get(String(prompt.id));

        return localPrompt?.prompt && !prompt.prompt ? { ...prompt, prompt: localPrompt.prompt } : prompt;
    });
    const newestServerId = serverPrompts.length > 0 ? String(serverPrompts.at(-1)?.id ?? '') : '';
    const newerLocalPrompts = localPrompts.value.filter(
        (prompt) =>
            prompt.id !== streamingPromptId.value
            && !serverIds.has(String(prompt.id))
            && String(prompt.id) > newestServerId,
    );

    return [...reconciled, ...newerLocalPrompts];
}

watch(
    () => props.game?.prompts,
    (serverPrompts) => {
        const streamingPrompt = streamingPromptId.value
            ? localPrompts.value.find((prompt) => prompt.id === streamingPromptId.value) ?? null
            : null;
        const mergedPrompts = mergeServerPrompts([...(serverPrompts ?? [])]);

        localPrompts.value = streamingPrompt
            ? [...mergedPrompts.filter((prompt) => prompt.id !== streamingPrompt.id), streamingPrompt]
            : mergedPrompts;
    },
    { deep: true },
);

watch(turnStatus, (status) => {
    if (status === 'narrating' || !streamingPromptId.value) {
        return;
    }

    const transientPromptId = streamingPromptId.value;
    localPrompts.value = localPrompts.value.filter((prompt) => prompt.id !== transientPromptId);
    streamingPromptId.value = null;
});

function ensureStreamingPrompt(sourcePromptId: string): string {
    const promptId = `stream-${sourcePromptId}`;

    if (streamingPromptId.value && streamingPromptId.value !== promptId) {
        const stalePromptId = streamingPromptId.value;
        localPrompts.value = localPrompts.value.filter((prompt) => prompt.id !== stalePromptId);
    }

    streamingPromptId.value = promptId;

    if (!localPrompts.value.some((prompt) => prompt.id === promptId)) {
        const sourcePrompt = localPrompts.value.find((prompt) => prompt.id === sourcePromptId);

        localPrompts.value = [
            ...localPrompts.value,
            {
                id: promptId,
                game_id: props.game?.id ?? '',
                session_number: sourcePrompt?.session_number ?? props.game?.current_session_number ?? null,
                prompt: null,
                response: '',
                choices: [],
                created_at: null,
                updated_at: null,
            },
        ];
    }

    return promptId;
}

function appendNarrationDelta(sourcePromptId: string, text: string): void {
    const promptId = ensureStreamingPrompt(sourcePromptId);

    localPrompts.value = localPrompts.value.map((prompt) =>
        prompt.id === promptId ? { ...prompt, response: `${prompt.response ?? ''}${text}` } : prompt,
    );
}

function commitStreamedNarration(signal: ArbiterNarrationSignal): void {
    const responsePrompt = signal.prompt;

    if (!responsePrompt) {
        return;
    }

    const transientPromptId = `stream-${signal.source_prompt_id}`;

    localPrompts.value = localPrompts.value
        .map((prompt) =>
            prompt.id === signal.source_prompt_id
                ? { ...prompt, prompt: signal.source_prompt_value ?? prompt.prompt }
                : prompt,
        )
        .filter((prompt) => prompt.id !== transientPromptId && prompt.id !== responsePrompt.id);
    localPrompts.value = [...localPrompts.value, responsePrompt];
    if (streamingPromptId.value === transientPromptId) {
        streamingPromptId.value = null;
    }
}

function setPromptCardElement(promptId: string, element: unknown): void {
    promptCardElements.value[promptId] = element instanceof HTMLElement ? element : null;
}

function scrollToPromptStart(promptId: string): void {
    const element = promptCardElements.value[promptId];

    if (!element) {
        return;
    }

    const headerOffset = 112;
    const top = Math.max(0, window.scrollY + element.getBoundingClientRect().top - headerOffset);

    window.scrollTo({ top, behavior: 'smooth' });
}

function memberAvatar(member: MultiplayerLobbyMemberInterface, index: number): string {
    return member.user.avatar || [billyAvatar, kevinAvatar, miraAvatar][index % 3];
}

function memberDisplayName(member: MultiplayerLobbyMemberInterface): string {
    return member.user.id === currentUserId.value ? `${member.user.full_name} (you)` : member.user.full_name;
}

function memberRole(member: MultiplayerLobbyMemberInterface): string {
    if (member.id === props.arbiter.current_arbiter_member_id) {
        return 'Arbiter';
    }

    return member.role === 'host' ? 'Host' : 'Player';
}

function memberState(member: MultiplayerLobbyMemberInterface): string {
    if (turnStatus.value === 'opening') {
        return 'Preparing story…';
    }

    if (isStoryComplete.value) {
        return 'Story complete';
    }

    if (isSessionComplete.value) {
        return 'Episode complete';
    }

    if (member.id === props.arbiter.current_arbiter_member_id) {
        if (isAwaitingArbiter.value) {
            return 'Choosing…';
        }

        return 'Arbiter';
    }

    if (proposalByMemberId.value.has(member.id)) {
        return 'Choice submitted';
    }

    return isNarrating.value ? 'Listening…' : 'Thinking…';
}

const players = computed<Player[]>(() =>
    members.value.map((member, index) => ({
        id: member.id,
        name: memberDisplayName(member),
        role: memberRole(member),
        state: memberState(member),
        avatar: memberAvatar(member, index),
        tone: member.id === props.arbiter.current_arbiter_member_id ? 'gold' : 'cyan',
        host: member.role === 'host',
    })),
);

const decisionTitle = computed(() => {
    if (isStoryComplete.value) {
        return 'Story Complete';
    }

    if (isAwaitingArbiter.value) {
        return `It’s ${currentArbiter.value?.user.full_name ?? 'the Arbiter'}’s Turn!`;
    }

    if (isNarrating.value) {
        return 'The Story Is Moving Forward';
    }

    if (isSessionComplete.value) {
        return 'Episode Complete';
    }

    return 'Everyone Gets a Say';
});

const decisionDescription = computed(() => {
    if (isStoryComplete.value) {
        return 'Every chapter of this shared story has reached its ending.';
    }

    if (isAwaitingArbiter.value) {
        return 'Everyone has contributed. The Arbiter is making the final choice.';
    }

    if (isNarrating.value) {
        return 'The selected choice is becoming the next moment in the story.';
    }

    if (isSessionComplete.value) {
        return 'This episode has reached its ending.';
    }

    if (turnStatus.value === 'opening') {
        return 'Xen is preparing the opening narration.';
    }

    return 'Write your choice. The Arbiter will make the final decision after everyone has contributed.';
});

const waitingStatus = computed(() => {
    if (actionError.value) {
        return actionError.value;
    }

    if (props.game?.opening_error) {
        return props.game.opening_error;
    }

    if (props.game?.turn_error && !isNarrating.value) {
        return props.game.turn_error;
    }

    if (turnStatus.value === 'opening') {
        return 'Preparing the opening narration…';
    }

    if (isStoryComplete.value) {
        return 'This shared story is complete.';
    }

    if (isSessionComplete.value) {
        return canAdvanceSession.value
            ? (isLastSession.value ? 'End the story for everyone when you are ready.' : 'Continue to the next chapter when you are ready.')
            : 'Waiting for the host to continue the story…';
    }

    if (isNarrating.value) {
        return 'The selected choice is being narrated…';
    }

    if (isAwaitingArbiter.value) {
        return isCurrentUserArbiter.value
            ? 'Choose the proposal that becomes canon.'
            : `Waiting for ${currentArbiter.value?.user.full_name ?? 'the Arbiter'} to choose…`;
    }

    if (isCurrentUserArbiter.value) {
        return 'Waiting for players to write their choices…';
    }

    return ownProposal.value ? 'Choice submitted. Waiting for the other players…' : 'Waiting for you to submit your choice…';
});

async function copyRoomCode(): Promise<void> {
    try {
        await navigator.clipboard.writeText(roomCode.value);
        copiedRoomCode.value = true;
    } catch {
        copiedRoomCode.value = false;
    }
}

function submitChat(): void {
    const message = chatDraft.value.trim();

    if (!message) {
        return;
    }

    messages.value.push({ name: `${currentMember.value?.user.full_name ?? 'You'} (you)`, text: message, time: 'Now', tone: 'fantasy' });
    chatDraft.value = '';
}

function lobbyEndpoint(suffix: string): string {
    return `${showMultiplayerLobby(props.lobby.room_code).url}${suffix}`;
}

function submitProposal(proposal: string): void {
    if (!canSubmitProposal.value || isSubmittingProposal.value) {
        return;
    }

    actionError.value = null;
    tts.primeAudio();
    isSubmittingProposal.value = true;

    router.post(
        lobbyEndpoint('/proposals'),
        { proposal },
        {
            preserveScroll: true,
            onError: (errors) => {
                actionError.value = errors.lobby ?? errors.proposal ?? 'We could not submit your proposal.';
            },
            onFinish: () => {
                isSubmittingProposal.value = false;
            },
        },
    );
}

function selectProposal(proposal: MultiplayerLobbyProposalInterface): void {
    if (!canChooseProposal.value || !proposal.text || isSelectingProposal.value) {
        return;
    }

    actionError.value = null;
    tts.primeAudio();
    isSelectingProposal.value = true;

    router.post(
        lobbyEndpoint(`/proposals/${proposal.id}/select`),
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                actionError.value = errors.lobby ?? 'We could not select that proposal.';
            },
            onFinish: () => {
                isSelectingProposal.value = false;
            },
        },
    );
}

function advanceSession(): void {
    if (!canAdvanceSession.value || isAdvancingSession.value) {
        return;
    }

    actionError.value = null;
    isAdvancingSession.value = true;

    router.post(
        lobbyEndpoint('/next-session'),
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                actionError.value = errors.lobby ?? 'We could not advance the story.';
            },
            onFinish: () => {
                isAdvancingSession.value = false;
            },
        },
    );
}

useEchoPresence<ArbiterStateSignal>(
    `multiplayer.lobby.${props.lobby.id}`,
    '.multiplayer.arbiter.state.changed',
    (signal) => {
        if (signal.lobby_id !== props.lobby.id) {
            return;
        }

        router.reload({
            only: ['lobby', 'game', 'arbiter'],
            preserveScroll: true,
        });
    },
);

useEchoPresence<ArbiterNarrationSignal>(
    `multiplayer.lobby.${props.lobby.id}`,
    '.multiplayer.arbiter.narration.streamed',
    (signal) => {
        if (signal.lobby_id !== props.lobby.id) {
            return;
        }

        if (signal.phase === 'ready') {
            actionError.value = null;
            ensureStreamingPrompt(signal.source_prompt_id);

            return;
        }

        if (signal.phase === 'delta' && signal.text) {
            const isFirstVisibleDelta = !hasVisibleStreamedResponse.value;
            const promptId = `stream-${signal.source_prompt_id}`;

            appendNarrationDelta(signal.source_prompt_id, signal.text);

            if (isFirstVisibleDelta) {
                nextTick(() => {
                    scrollToPromptStart(promptId);
                });
            }

            return;
        }

        if (signal.phase === 'done') {
            commitStreamedNarration(signal);

            return;
        }

        if (signal.phase === 'error') {
            const transientPromptId = `stream-${signal.source_prompt_id}`;

            localPrompts.value = localPrompts.value.filter((prompt) => prompt.id !== transientPromptId);
            if (streamingPromptId.value === transientPromptId) {
                streamingPromptId.value = null;
            }
            actionError.value = signal.message ?? 'Narration hiccuped — please retry this turn.';
        }
    },
);

onUnmounted(() => {
    tts.dismiss();
});
</script>

<template>
    <Head title="Arbiter" />

    <main class="arbiter-page">
        <div
            v-if="gameplayBackgroundImage"
            class="arbiter-scene"
            :style="{ backgroundImage: `url(${gameplayBackgroundImage})` }"
            aria-hidden="true"
        />

        <div class="arbiter-shell">
            <header class="arbiter-topbar">
                <BaseButton severity="header-glass" :icon-only="true" class="arbiter-back" title="Go back" @click="goBack">
                    <img :src="backIcon" alt="" class="h-6 w-4" aria-hidden="true" />
                </BaseButton>

                <div v-if="tts.isActive.value" class="arbiter-media-player" aria-label="Narration controls">
                    <GameplayMediaPlayer />
                </div>
            </header>

            <div class="arbiter-grid">
                <aside class="arbiter-rail arbiter-left-rail" aria-label="Players">
                    <div class="rail-tabs">
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Help">
                            <img :src="questionIcon" alt="" class="size-6" aria-hidden="true" />
                        </BaseButton>
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Settings">
                            <img :src="settingsIcon" alt="" class="size-5" aria-hidden="true" />
                        </BaseButton>
                        <BaseButton severity="header-glass" class="rail-tab gameplay-header-glass--active" title="Players">
                            <span class="rail-tab-content">
                                <img :src="playersIcon" alt="" class="size-7" aria-hidden="true" />
                                <span>Players</span>
                            </span>
                        </BaseButton>
                    </div>

                    <section class="arbiter-panel">
                        <div class="panel-title"><span>Players</span><small>{{ members.length }}/{{ props.lobby.max_players }}</small></div>
                        <div class="player-list">
                            <article v-for="player in players" :key="player.id" class="player-card">
                                <img class="player-avatar" :src="player.avatar" :alt="player.name + ' avatar'" />
                                <div class="player-details">
                                    <div class="player-name-row">
                                        <span>{{ player.name }}</span>
                                        <span v-if="player.host" class="player-host">
                                            <LucideCrown class="size-3" :stroke-width="1.75" />
                                            Host
                                        </span>
                                    </div>
                                    <span :class="['player-state', 'player-state--' + player.tone]"><i />{{ player.state }}</span>
                                </div>
                                <span class="player-score">{{ player.role }}</span>
                            </article>
                        </div>

                        <div class="invite-card">
                            <div class="invite-label">
                                <img :src="playersIcon" alt="" class="size-5" aria-hidden="true" />
                                <span>Invite Players</span>
                            </div>
                            <p>Share room code</p>
                            <div class="room-code-card">
                                <div><span>Room Code</span><strong>{{ roomCode }}</strong></div>
                                <button type="button" :aria-label="copiedRoomCode ? 'Room code copied' : 'Copy room code'" @click="copyRoomCode">
                                    <img :src="copyIcon" alt="" aria-hidden="true" />
                                </button>
                            </div>
                            <span v-if="copiedRoomCode" class="copy-confirmation" role="status">Room code copied</span>
                        </div>
                        <button type="button" class="leave-room-button">Leave Room</button>
                    </section>
                </aside>

                <section class="arbiter-story-column" aria-labelledby="arbiter-story-title">
                    <div class="story-heading">
                        <h1 id="arbiter-story-title">{{ props.lobby.story.title }}</h1>
                        <GameplayOrnamentDivider :label="`EPISODE ${props.game?.current_session_number ?? 1}`" color="#59C1CA" />
                    </div>

                    <div class="story-history" aria-live="polite">
                        <div
                            v-for="prompt in prompts"
                            :key="prompt.id"
                            :ref="(element) => setPromptCardElement(prompt.id, element)"
                            class="story-turn"
                        >
                            <GameplayChatCard
                                :prompt="prompt"
                                :game-id="props.game?.id ?? ''"
                                :cover-url="props.lobby.story.cover"
                                :is-latest="prompt.id === latestPromptId && !isSessionComplete"
                                :is-submitting="isNarrating"
                                :is-streaming-response="prompt.id === streamingPromptId"
                                :narration-only="true"
                            />
                        </div>

                        <div
                            v-if="(turnStatus === 'opening' || isNarrating) && !hasVisibleStreamedResponse"
                            class="story-stream-placeholder"
                            aria-label="Xen is preparing the next passage"
                        >
                            <span />
                            <span />
                            <span />
                            <span />
                        </div>

                        <p v-else-if="prompts.length === 0" class="story-history-empty">Xen is preparing the opening narration…</p>
                    </div>

                    <section class="decision-section" aria-labelledby="decision-title">
                        <div class="decision-rule" aria-hidden="true" />
                        <div class="decision-heading">
                            <div>
                                <h2 id="decision-title">{{ decisionTitle }}</h2>
                                <p>{{ decisionDescription }}</p>
                            </div>
                            <span class="decision-timer" :aria-label="`${decisionWindowSeconds} seconds decision window`">{{ decisionWindowSeconds }}<small>s</small></span>
                        </div>

                        <div v-if="canChooseProposal" class="proposal-choice-list">
                            <button
                                v-for="proposal in availableProposals"
                                :key="proposal.id"
                                type="button"
                                class="proposal-choice-card"
                                :class="{ 'proposal-choice-card--selected': proposal.is_selected }"
                                :disabled="isSelectingProposal"
                                @click="selectProposal(proposal)"
                            >
                                <span class="proposal-choice-radio" aria-hidden="true" />
                                <span>{{ proposal.text }}</span>
                            </button>
                        </div>

                        <div v-else class="contributions">
                            <article
                                v-for="(member, index) in nonArbiterMembers"
                                :key="member.id"
                                class="contribution-card"
                                :class="{
                                    'contribution-card--active': member.user.id === currentUserId,
                                    'contribution-card--waiting': !proposalByMemberId.has(member.id),
                                    'contribution-card--selected': proposalByMemberId.get(member.id)?.is_selected,
                                }"
                            >
                                <img class="contribution-avatar" :src="memberAvatar(member, index + 1)" :alt="`${member.user.full_name} avatar`" />
                                <div class="contribution-content">
                                    <strong>{{ memberDisplayName(member) }}</strong>
                                    <span v-if="proposalByMemberId.get(member.id)?.text" class="submitted-proposal">
                                        {{ proposalByMemberId.get(member.id)?.text }}
                                    </span>
                                    <span v-else-if="proposalByMemberId.has(member.id)" class="typing-label">Choice submitted</span>
                                    <span v-else-if="member.user.id === currentUserId" class="proposal-prompt">What do you propose?</span>
                                    <span v-else class="typing-label">Thinking <span aria-hidden="true">•••</span></span>
                                </div>
                                <p v-if="member.user.id === currentUserId" class="contribution-tip">
                                    {{ ownProposal ? 'Your proposal is waiting for the Arbiter.' : 'Share the choice point you want the Arbiter to consider.' }}
                                </p>
                            </article>
                        </div>
                        <div v-if="isSessionComplete && !isStoryComplete" class="session-transition">
                            <button
                                v-if="canAdvanceSession"
                                type="button"
                                class="session-transition-button"
                                :disabled="isAdvancingSession"
                                @click="advanceSession"
                            >
                                {{ isAdvancingSession ? 'Opening chapter…' : isLastSession ? 'End story' : 'Continue to next chapter' }}
                            </button>
                            <p v-else>Waiting for the host to continue the story.</p>
                        </div>
                        <p :class="['waiting-status', { 'waiting-status--error': actionError }]" aria-live="polite">
                            <img :src="hourglassIcon" alt="" aria-hidden="true" />
                            {{ waitingStatus }}
                        </p>
                    </section>
                </section>

                <aside class="arbiter-rail arbiter-right-rail" aria-label="Chat">
                    <div class="rail-tabs rail-tabs--right">
                        <BaseButton severity="header-glass" class="rail-tab gameplay-header-glass--active" title="Chat">
                            <span class="rail-tab-content">
                                <img :src="chatIcon" alt="" class="size-7" aria-hidden="true" />
                                <span>Chat</span>
                            </span>
                        </BaseButton>
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Power">
                            <img :src="lightningIcon" alt="" class="size-6" aria-hidden="true" />
                        </BaseButton>
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Game order">
                            <LucideList class="size-5 text-gray-300" :stroke-width="1.75" />
                        </BaseButton>
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Notes">
                            <img :src="notesIcon" alt="" class="size-5" aria-hidden="true" />
                        </BaseButton>
                    </div>

                    <section class="arbiter-panel chat-panel">
                        <div class="chat-window">
                            <article v-for="message in messages" :key="message.name + '-' + message.text" class="chat-message">
                                <div class="chat-message-meta">
                                    <strong :class="'chat-tone--' + message.tone">{{ message.name }}</strong>
                                    <time>{{ message.time }}</time>
                                </div>
                                <p>{{ message.text }}</p>
                            </article>
                        </div>
                        <form class="chat-compose" @submit.prevent="submitChat">
                            <label class="sr-only" for="arbiter-chat-message">Type a message</label>
                            <input id="arbiter-chat-message" v-model="chatDraft" type="text" placeholder="Type a message..." autocomplete="off" />
                            <button type="submit" aria-label="Send message"><img :src="sendIcon" alt="" aria-hidden="true" /></button>
                        </form>
                    </section>
                </aside>
            </div>
        </div>

        <footer class="arbiter-proposal-dock">
            <GameplayInput
                class="arbiter-proposal-input"
                :disabled="!canSubmitProposal || isSubmittingProposal"
                :placeholder="canSubmitProposal ? 'What Do You Do?' : ownProposal ? 'Choice submitted' : ''"
                @submit="submitProposal"
            />
        </footer>
    </main>
</template>

<style scoped>
:global(body) {
    margin: 0;
    background: #000;
}

.arbiter-page {
    position: relative;
    min-height: 100svh;
    padding-bottom: 10rem;
    overflow-x: clip;
    background: #000;
    color: #fff;
    font-family: 'Inter', sans-serif;
}

.arbiter-scene {
    position: absolute;
    top: 3.75rem;
    left: 50%;
    width: min(52rem, 66vw);
    height: min(34rem, 48vw);
    transform: translateX(-50%);
    background-position: center;
    background-size: cover;
    opacity: 0.88;
    mask-image: linear-gradient(to bottom, transparent 0%, #000 10%, #000 58%, transparent 96%);
    -webkit-mask-image: linear-gradient(to bottom, transparent 0%, #000 10%, #000 58%, transparent 96%);
}

.arbiter-scene::after {
    position: absolute;
    inset: 0;
    content: '';
    background:
        linear-gradient(90deg, #000 0%, transparent 31%, transparent 69%, #000 100%),
        linear-gradient(to bottom, rgba(0, 0, 0, 0.62) 0%, transparent 30%, rgba(0, 0, 0, 0.78) 86%);
}

.arbiter-shell {
    position: relative;
    z-index: 1;
    width: min(100% - 3rem, 91.25rem);
    min-height: inherit;
    margin: 0 auto;
}

.arbiter-topbar {
    position: absolute;
    z-index: 4;
    top: 1.25rem;
    right: 0;
    left: 0;
    display: flex;
    justify-content: center;
}

.arbiter-back {
    position: absolute !important;
    top: 0;
    left: 0;
    width: 3.25rem !important;
    height: 3.25rem !important;
}

.arbiter-media-player {
    width: min(39.75rem, calc(100vw - 15rem));
    margin-top: 2.65rem;
}

.arbiter-media-player :deep(.player-bar) {
    width: 100%;
}

.arbiter-grid {
    display: grid;
    grid-template-columns: minmax(16.75rem, 20.75rem) minmax(34rem, 38.5rem) minmax(16.75rem, 20.75rem);
    align-items: start;
    column-gap: clamp(2rem, 5.5vw, 6.75rem);
}

.arbiter-rail {
    position: sticky;
    top: 0;
    display: flex;
    max-height: 100svh;
    min-width: 0;
    flex-direction: column;
    align-self: start;
    box-sizing: border-box;
    padding-top: 5.5rem;
    padding-bottom: 0.75rem;
}

.rail-tabs {
    display: flex;
    min-height: 3.25rem;
    align-items: center;
    gap: 0.625rem;
    margin-bottom: 1.55rem;
}

.rail-tabs--right {
    justify-content: flex-end;
}

.rail-icon-button {
    width: 3.25rem !important;
    height: 3.25rem !important;
    flex: 0 0 auto;
}

.rail-tab {
    width: auto !important;
    height: 3.25rem !important;
    flex: 0 0 auto;
    border-radius: 9999px !important;
    padding: 0 1rem 0 0.45rem !important;
    color: #ffbe58;
    font-size: 1.125rem;
    font-weight: 500;
}

.rail-tab-content {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}

.arbiter-panel {
    border: 1px solid rgba(255, 255, 255, 0.035);
    border-radius: 1.125rem;
    padding: 1.5rem;
    background: rgba(15, 15, 15, 0.96);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
}

.arbiter-rail > .arbiter-panel {
    min-height: 0;
}

.arbiter-left-rail > .arbiter-panel {
    max-height: calc(100svh - 10.5rem);
    overflow-y: auto;
    scrollbar-color: rgba(89, 193, 202, 0.35) transparent;
    scrollbar-width: thin;
}

.panel-title {
    display: flex;
    align-items: baseline;
    justify-content: flex-start;
    gap: 0.6rem;
    margin-bottom: 1.15rem;
    color: #59c1ca;
    font-size: 1.15rem;
}

.panel-title small {
    color: #7e7e7e;
    font-size: 0.875rem;
}

.player-list {
    display: grid;
    gap: 0.75rem;
}

.player-card {
    display: grid;
    min-height: 6.25rem;
    grid-template-columns: 3.1rem minmax(0, 1fr);
    gap: 0 0.75rem;
    border-radius: 0.625rem;
    padding: 0.75rem;
    background: #1a1a1a;
}

.player-avatar,
.contribution-avatar {
    width: 3.1rem;
    height: 3.1rem;
    border-radius: 9999px;
    object-fit: cover;
}

.player-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 1rem;
}

.player-host,
.player-state {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.player-host {
    color: #ffbe58;
    font-size: 0.75rem;
}

.player-state {
    margin-top: 0.55rem;
    font-size: 0.8125rem;
}

.player-state i {
    width: 0.35rem;
    height: 0.35rem;
    border-radius: 9999px;
    background: currentColor;
}

.player-state--cyan {
    color: #59c1ca;
}

.player-state--gold {
    color: #ffbe58;
}

.player-score {
    grid-column: 1 / -1;
    margin-top: 0.7rem;
    border-top: 1px solid #2f2f2f;
    padding-top: 0.55rem;
    color: #8f8f8f;
    font-size: 0.8125rem;
}

.invite-card {
    position: relative;
    margin-top: 1.3rem;
    border: 1px dashed #5c5c5c;
    border-radius: 0.5rem;
    padding: 1rem;
}

.invite-label {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: #e4e4e4;
    font-size: 1rem;
}

.invite-card > p {
    margin: 0.25rem 0 0.85rem;
    color: #8f8f8f;
    font-size: 0.8125rem;
}

.room-code-card {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    overflow: hidden;
    border: 1px solid #555;
    border-radius: 0.5rem;
    background: #202020;
}

.room-code-card > div {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem 0.875rem;
}

.room-code-card span,
.room-code-card strong {
    display: block;
}

.room-code-card span {
    color: #c5c5c5;
    font-size: 0.8125rem;
}

.room-code-card strong {
    color: #59c1ca;
    font-size: 0.875rem;
    font-weight: 500;
    letter-spacing: 0.04em;
}

.room-code-card button {
    display: grid;
    width: 3.65rem;
    place-items: center;
    border: 0;
    border-left: 1px solid #373737;
    background: transparent;
}

.room-code-card button:hover {
    background: rgba(255, 255, 255, 0.06);
}

.room-code-card img {
    width: 2.65rem;
    height: 2.65rem;
}

.copy-confirmation {
    position: absolute;
    right: 0.85rem;
    bottom: -1.35rem;
    color: #59c1ca;
    font-size: 0.75rem;
}

.leave-room-button {
    width: 100%;
    height: 3.3rem;
    margin-top: 1.75rem;
    border: 1px solid #fd6d6d;
    border-radius: 0.45rem;
    color: #fd6d6d;
    background: transparent;
    font-size: 0.875rem;
}

.leave-room-button:hover {
    color: #fff;
    background: rgba(253, 109, 109, 0.14);
}

.arbiter-story-column {
    min-width: 0;
    padding-top: clamp(15rem, 25vh, 21.5rem);
}

.story-heading {
    display: grid;
    gap: 1.55rem;
}

.story-heading h1 {
    margin: 0;
    color: #fff;
    font-family: 'Inria Serif', serif;
    font-size: clamp(2.75rem, 3.8vw, 4rem);
    font-weight: 700;
    line-height: 0.96;
    text-align: center;
}

.story-history {
    display: grid;
    gap: 1.75rem;
    margin-top: 1.55rem;
}

.story-turn {
    scroll-margin-top: 7rem;
}

.story-history :deep(.narration-card) {
    color: #f0f0f0;
    font-size: 1.05rem;
    line-height: 1.45;
}

.story-history-empty {
    margin: 0;
    color: #8f8f8f;
    text-align: center;
}

.story-stream-placeholder {
    display: grid;
    gap: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.875rem;
    padding: 1.25rem;
    background: rgba(20, 20, 20, 0.64);
}

.story-stream-placeholder span {
    height: 0.8rem;
    border-radius: 9999px;
    background: rgba(126, 126, 126, 0.24);
    animation: story-stream-pulse 1.4s ease-in-out infinite;
}

.story-stream-placeholder span:nth-child(2) {
    width: 92%;
    animation-delay: 100ms;
}

.story-stream-placeholder span:nth-child(3) {
    width: 78%;
    animation-delay: 200ms;
}

.story-stream-placeholder span:nth-child(4) {
    width: 58%;
    animation-delay: 300ms;
}

@keyframes story-stream-pulse {
    0%,
    100% {
        opacity: 0.42;
    }

    50% {
        opacity: 0.9;
    }
}

.decision-section {
    margin-top: 4rem;
}

.decision-rule {
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(255, 190, 88, 0.75), transparent);
}

.decision-heading {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 1.85rem;
    max-width: 33.5rem;
    margin: 1.6rem auto 0;
    text-align: center;
}

.decision-heading h2 {
    margin: 0;
    color: #ffbe58;
    font-size: 1.15rem;
    font-weight: 400;
}

.decision-heading p {
    max-width: 23rem;
    margin: 0.45rem auto 0;
    color: #7e7e7e;
    font-size: 0.8125rem;
    line-height: 1.35;
}

.decision-timer {
    display: grid;
    width: 3rem;
    height: 3rem;
    place-items: center;
    border: 3px solid #59c1ca;
    border-radius: 9999px;
    color: #59c1ca;
    font-size: 0.85rem;
    font-weight: 500;
}

.decision-timer small {
    margin-left: -0.12rem;
    color: #8f8f8f;
    font-size: 0.625rem;
}

.proposal-choice-list {
    display: grid;
    gap: 0.85rem;
    max-width: 38.5rem;
    margin: 2rem auto 0;
}

.proposal-choice-card {
    display: flex;
    width: 100%;
    min-height: 5rem;
    align-items: center;
    gap: 0.85rem;
    border: 1px solid #373737;
    border-radius: 0.75rem;
    padding: 1.15rem;
    color: #e6e6e6;
    background: rgba(7, 7, 7, 0.72);
    font: inherit;
    font-size: 1rem;
    line-height: 1.35;
    text-align: left;
    transition:
        border-color 160ms ease,
        background 160ms ease,
        transform 160ms ease;
}

.proposal-choice-card:hover:not(:disabled) {
    border-color: #59c1ca;
    background: rgba(18, 63, 68, 0.44);
    transform: translateY(-1px);
}

.proposal-choice-card:disabled {
    cursor: wait;
    opacity: 0.65;
}

.contribution-card--selected {
    border-color: #59c1ca !important;
    background: rgba(18, 63, 68, 0.58) !important;
    box-shadow: 0 0 0 1px rgba(89, 193, 202, 0.15), 0 0 24px rgba(89, 193, 202, 0.1);
}

.proposal-choice-card--selected {
    border-color: #4bcbd3 !important;
    color: #fff;
    background: #61d9d5 !important;
    box-shadow: 0 0 0 1px rgba(89, 193, 202, 0.38), 0 0 28px rgba(89, 193, 202, 0.16);
}

.proposal-choice-card--selected:disabled {
    opacity: 1;
}

.proposal-choice-radio {
    position: relative;
    display: grid;
    width: 1.5rem;
    height: 1.5rem;
    flex: 0 0 auto;
    place-items: center;
    border: 1px solid #5c5c5c;
    border-radius: 9999px;
    margin-top: 0.05rem;
}

.proposal-choice-card:hover:not(:disabled) .proposal-choice-radio,
.proposal-choice-card--selected .proposal-choice-radio {
    border-color: #59c1ca;
    box-shadow: inset 0 0 0 4px #0f282b, inset 0 0 0 6px #59c1ca;
}

.proposal-choice-card--selected .proposal-choice-radio {
    border-color: #fff;
    box-shadow: none;
}

.proposal-choice-card--selected .proposal-choice-radio::after {
    width: 0.65rem;
    height: 0.35rem;
    border-bottom: 2px solid #fff;
    border-left: 2px solid #fff;
    content: '';
    transform: translateY(-0.08rem) rotate(-45deg);
}

.session-transition {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.65rem;
    margin: 1.65rem auto 0;
    text-align: center;
}

.session-transition > p {
    margin: 0;
    color: #8f8f8f;
    font-size: 0.875rem;
}

.session-transition-button {
    border: 1px solid rgba(89, 193, 202, 0.7);
    border-radius: 9999px;
    padding: 0.7rem 1.35rem;
    color: #8eeaf0;
    background: rgba(18, 63, 68, 0.45);
    font: inherit;
    font-size: 0.875rem;
    transition: background 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.session-transition-button:hover:not(:disabled) {
    border-color: #59c1ca;
    background: rgba(18, 85, 91, 0.66);
    transform: translateY(-1px);
}

.session-transition-button:disabled {
    cursor: wait;
    opacity: 0.65;
}

.contributions {
    display: grid;
    gap: 0.85rem;
    max-width: 38.5rem;
    margin: 2rem auto 0;
}

.contribution-card {
    display: grid;
    grid-template-columns: 3.25rem minmax(0, 1fr);
    gap: 0.7rem;
    border: 1px solid #373737;
    border-radius: 0.625rem;
    padding: 0.85rem;
    background: rgba(7, 7, 7, 0.62);
}

.contribution-avatar {
    width: 3.25rem;
    height: 3.25rem;
}

.contribution-content {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.25rem;
    padding-top: 0.2rem;
}

.contribution-content > strong {
    color: #e6e6e6;
    font-size: 1rem;
    font-weight: 400;
}

.contribution-content > strong span {
    color: #b5b5b5;
}

.proposal-prompt,
.submitted-proposal {
    overflow: hidden;
    color: #59c1ca;
    font-size: 1rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.proposal-prompt::before {
    display: inline-block;
    width: 0.8rem;
    height: 0.8rem;
    margin-right: 0.4rem;
    border: 1px solid currentColor;
    border-radius: 9999px;
    content: '';
    vertical-align: -0.08em;
}

.contribution-tip {
    grid-column: 1 / -1;
    margin: 0.05rem 0 0;
    color: #8f8f8f;
    font-size: 0.8125rem;
}

.contribution-card--waiting {
    opacity: 0.47;
}

.typing-label {
    color: #d8d8d8;
    font-size: 0.9375rem;
}

.waiting-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    margin: 1.35rem 0 0;
    color: #59c1ca;
    font-size: 0.875rem;
}

.waiting-status img {
    width: 1.25rem;
    height: 1.25rem;
}

.waiting-status--error {
    color: #fd8b8b;
}

.chat-panel {
    display: flex;
    height: min(40rem, calc(100svh - 10.5rem));
    min-height: 27rem;
    flex-direction: column;
    padding: 1.25rem;
    overflow: hidden;
}

.chat-window {
    display: flex;
    min-height: 0;
    flex: 1;
    flex-direction: column;
    justify-content: flex-end;
    gap: 0.75rem;
    overflow-y: auto;
    border: 1px solid #262626;
    border-radius: 0.65rem;
    padding: 1rem;
    scrollbar-color: rgba(89, 193, 202, 0.35) transparent;
    scrollbar-width: thin;
}

.chat-message {
    border-radius: 0.6rem;
    padding: 0.75rem;
    background: #222;
}

.chat-message:nth-child(n + 3) {
    background: #1a1a1a;
}

.chat-message-meta {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
}

.chat-message strong {
    font-size: 0.875rem;
    font-weight: 400;
}

.chat-message time {
    flex: 0 0 auto;
    color: #666;
    font-size: 0.6875rem;
}

.chat-message p {
    margin: 0.55rem 0 0;
    color: #d4d4d4;
    font-size: 0.8125rem;
    line-height: 1.35;
}

.chat-tone--cyan {
    color: #59c1ca;
}

.chat-tone--fantasy {
    color: #cca0f3;
}

.chat-tone--green {
    color: #58d9a1;
}

.chat-compose {
    display: flex;
    height: 4.4rem;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.9rem;
    border: 1px solid #626262;
    border-radius: 0.65rem;
    padding: 0 0.65rem 0 1rem;
    background: #151515;
}

.chat-compose input {
    min-width: 0;
    flex: 1;
    border: 0;
    outline: 0;
    color: #efefef;
    background: transparent;
    font: inherit;
    font-size: 0.875rem;
}

.chat-compose input::placeholder {
    color: #666;
}

.chat-compose button {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    border: 0;
    border-radius: 9999px;
    background: #777;
}

.chat-compose button:hover {
    background: #8f8f8f;
}

.chat-compose img {
    width: 2rem;
    height: 2rem;
}

.arbiter-proposal-dock {
    position: fixed;
    z-index: 10;
    right: 1.5rem;
    bottom: 1.75rem;
    left: 1.5rem;
    display: flex;
    justify-content: center;
    pointer-events: none;
}

.arbiter-proposal-dock::before {
    position: fixed;
    z-index: -1;
    right: 0;
    bottom: 0;
    left: 0;
    height: 10rem;
    content: '';
    background: linear-gradient(to top, #000 15%, rgba(0, 0, 0, 0.82) 58%, transparent);
}

.arbiter-proposal-input {
    width: min(100%, 38.5rem) !important;
    pointer-events: auto;
}

@media (max-width: 1360px) {
    .arbiter-shell {
        width: min(100% - 2.5rem, 78rem);
    }

    .arbiter-grid {
        grid-template-columns: minmax(15.5rem, 18rem) minmax(31rem, 35rem) minmax(15.5rem, 18rem);
        column-gap: clamp(1.25rem, 3.2vw, 3.5rem);
    }

    .arbiter-panel {
        padding: 1.1rem;
    }
}

@media (max-width: 1060px) {
    .arbiter-page {
        min-height: 100svh;
        padding-bottom: 8.5rem;
    }

    .arbiter-shell {
        min-height: 0;
    }

    .arbiter-topbar {
        position: relative;
        top: auto;
        min-height: 6.5rem;
        padding-top: 1.25rem;
    }

    .arbiter-back {
        top: 1.25rem;
    }

    .arbiter-grid {
        display: flex;
        max-width: 40rem;
        flex-direction: column;
        gap: 2.5rem;
        margin: 0 auto;
    }

    .arbiter-story-column {
        order: 1;
        padding-top: 4.5rem;
    }

    .arbiter-left-rail {
        order: 2;
    }

    .arbiter-right-rail {
        order: 3;
    }

    .arbiter-rail {
        position: static;
        max-height: none;
        width: 100%;
        padding-top: 0;
        padding-bottom: 0;
    }

    .arbiter-left-rail > .arbiter-panel {
        max-height: none;
        overflow-y: visible;
    }

    .chat-panel {
        height: auto;
        min-height: 0;
        overflow: visible;
    }

    .chat-window {
        height: 31.75rem;
        flex: none;
    }

    .rail-tabs--right {
        justify-content: flex-start;
    }

    .arbiter-scene {
        top: 3.5rem;
        width: min(50rem, 100vw);
        height: 30rem;
    }
}

@media (max-width: 700px) {
    .arbiter-shell {
        width: min(100% - 2rem, 40rem);
    }

    .arbiter-topbar {
        min-height: 6rem;
        justify-content: flex-end;
    }

    .arbiter-back {
        left: 0;
    }

    .arbiter-media-player {
        width: min(31rem, calc(100vw - 6rem));
        margin-top: 0;
    }

    .arbiter-story-column {
        padding-top: 3.5rem;
    }

    .story-heading h1 {
        font-size: clamp(2.5rem, 11vw, 3.5rem);
    }

    .story-history :deep(.narration-card) {
        font-size: 1rem;
        line-height: 1.35;
    }

    .decision-section {
        margin-top: 3rem;
    }

    .decision-heading {
        gap: 1rem;
    }

    .chat-window {
        height: 24rem;
    }

    .arbiter-proposal-dock {
        right: 1rem;
        bottom: 1rem;
        left: 1rem;
    }
}

@media (max-width: 440px) {
    .rail-tabs {
        gap: 0.35rem;
    }

    .rail-tab {
        padding-right: 0.75rem;
        font-size: 1rem;
    }

    .arbiter-panel {
        padding: 0.875rem;
    }

    .contribution-card {
        grid-template-columns: 2.8rem minmax(0, 1fr);
    }

    .contribution-avatar {
        width: 2.8rem;
        height: 2.8rem;
    }

    .contribution-content > strong,
    .proposal-prompt,
    .submitted-proposal {
        font-size: 0.875rem;
    }

    .contribution-tip,
    .waiting-status {
        font-size: 0.75rem;
    }
}
</style>
