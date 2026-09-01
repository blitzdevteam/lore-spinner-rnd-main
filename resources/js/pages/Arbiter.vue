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
import GameplayBottomSheet from '@/components/GameplayBottomSheet.vue';
import GameplayChatCard from '@/components/GameplayChatCard.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import GameplayMediaPlayer from '@/components/GameplayMediaPlayer.vue';
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import type {
    GameInterface,
    MultiplayerArbiterStateInterface,
    MultiplayerGeneratedChoiceInterface,
    MultiplayerLobbyChatMessageInterface,
    MultiplayerLobbyInterface,
    MultiplayerLobbyMemberInterface,
    MultiplayerLobbyProposalInterface,
    PromptInterface,
} from '@/types';
import { chooseRouletteChoice as storeRouletteChoice, show as showMultiplayerLobby } from '@/wayfinder/actions/App/Http/Controllers/User/MultiplayerLobbyController';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useEchoPresence, usePresenceChannel } from '@laravel/echo-vue';
import { LucideCrown, LucideList, LucideStar, LucideUserRound } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

defineOptions({ layout: null });

type PlayerTone = 'cyan' | 'gold' | 'left';
type ChatTone = 'cyan' | 'fantasy' | 'green';
type MobilePanel = 'players' | 'chat' | null;

type Player = {
    id: string;
    name: string;
    role: string;
    state: string;
    isTyping: boolean;
    hasLeft: boolean;
    avatar: string;
    tone: PlayerTone;
    isActiveTurn: boolean;
    isUpNext: boolean;
    isCurrentUser: boolean;
    isArbiter: boolean;
    host?: boolean;
};

type ChatMessage = {
    id: string;
    name: string;
    text: string;
    time: string;
    tone: ChatTone;
    createdAt: string | null;
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

type MultiplayerLobbyMembershipSignal = {
    lobby: {
        id: string;
    };
};

type ArbiterTypingWhisper = {
    is_typing: boolean;
    user_id: number;
};

type MultiplayerLobbyChatMessageWhisper = {
    id: string;
    body: string;
    created_at: string;
    user_id: number;
};

const props = defineProps<{
    lobby: MultiplayerLobbyInterface;
    game: GameInterface | null;
    arbiter: MultiplayerArbiterStateInterface;
    chatMessages: MultiplayerLobbyChatMessageInterface[];
}>();

const lobbyChannelName = `multiplayer.lobby.${props.lobby.id}`;
const typingWhisperEvent = 'multiplayer.arbiter.typing';
const chatWhisperEvent = 'multiplayer.lobby.chat.message';
const typingIndicatorTimeout = 2_500;
const typingWhisperInterval = 750;
const lobbyHeartbeatInterval = 3_000;
const multiplayerLobbyPresenceChannel = usePresenceChannel(lobbyChannelName);
const page = usePage();
const tts = useTextToSpeech();
const gameplayBackgroundImage = computed(
    () => props.game?.story?.banner || props.lobby.story.banner || props.game?.story?.cover || props.lobby.story.cover || null,
);
const roomCode = computed(() => props.lobby.room_code);
const members = computed(() => props.lobby.members);
const currentUserId = computed(() => page.props.auth?.id ?? null);
const currentMember = computed(() => members.value.find((member) => member.user.id === currentUserId.value) ?? null);
const currentArbiter = computed(() => members.value.find((member) => member.id === props.arbiter.current_arbiter_member_id) ?? null);
const currentChooser = computed(() => members.value.find((member) => member.id === props.arbiter.current_chooser_member_id) ?? null);
const isRouletteMode = computed(() => props.lobby.mode === 'roulette');
const turnStatus = computed(() => props.arbiter.turn_status);
const isCurrentUserArbiter = computed(() => props.arbiter.is_current_user_arbiter);
const isCurrentUserChooser = computed(() => props.arbiter.is_current_user_chooser);
const isCollectingProposals = computed(() => turnStatus.value === 'collecting_proposals');
const isAwaitingArbiter = computed(() => turnStatus.value === 'awaiting_arbiter');
const isAwaitingRouletteChoice = computed(() => turnStatus.value === 'awaiting_roulette_choice');
const isNarrating = computed(() => turnStatus.value === 'narrating');
const isSessionComplete = computed(() => turnStatus.value === 'session_complete');
const isStoryComplete = computed(() => props.lobby.status === 'completed');
const isGameplayPaused = computed(() => props.lobby.status === 'paused');
const hasDecisionTimer = computed(() => isAwaitingArbiter.value || isAwaitingRouletteChoice.value);
const decisionWindowSeconds = computed(() => props.arbiter.decision_window_seconds);
const decisionTimerNow = ref(Date.now());
const decisionSecondsRemaining = computed(() => {
    const deadline = props.arbiter.decision_deadline_at;

    if (hasDecisionTimer.value && deadline) {
        const deadlineTimestamp = Date.parse(deadline);

        if (!Number.isNaN(deadlineTimestamp)) {
            return Math.max(0, Math.ceil((deadlineTimestamp - decisionTimerNow.value) / 1_000));
        }
    }

    if (hasDecisionTimer.value && props.arbiter.decision_remaining_seconds !== null) {
        return Math.max(0, props.arbiter.decision_remaining_seconds);
    }

    return decisionWindowSeconds.value;
});
const isDecisionTimerUrgent = computed(() => hasDecisionTimer.value && decisionSecondsRemaining.value <= 5);
const pausedMembers = computed(() => members.value.filter((member) => member.left_at !== null));
const pausedMemberLabel = computed(() => {
    const names = pausedMembers.value.map((member) => member.user.full_name);

    if (names.length <= 1) {
        return names[0] ?? 'a player';
    }

    if (names.length === 2) {
        return `${names[0]} and ${names[1]}`;
    }

    return `${names.slice(0, -1).join(', ')}, and ${names.at(-1)}`;
});
const isLastSession = computed(() => {
    const totalSessions = props.game?.total_sessions ?? 0;
    const currentSession = props.game?.current_session_number ?? 1;

    return totalSessions > 0 && currentSession >= totalSessions;
});
const nonArbiterMembers = computed(() => members.value.filter((member) => member.id !== props.arbiter.current_arbiter_member_id));
const proposalByMemberId = computed(() => new Map(props.arbiter.proposals.map((proposal) => [proposal.member_id, proposal])));
const ownProposal = computed(() => {
    const member = currentMember.value;

    return member ? (proposalByMemberId.value.get(member.id) ?? null) : null;
});
const availableProposals = computed(() => props.arbiter.proposals.filter((proposal) => proposal.text !== null));
const selectedProposal = computed(() => props.arbiter.proposals.find((proposal) => proposal.is_selected && proposal.text !== null) ?? null);
const generatedChoices = computed(() => props.arbiter.generated_choices);
const turnRoleLabel = computed(() => (isRouletteMode.value ? 'Chooser' : 'Arbiter'));
const customInputEnabled = computed(() => props.lobby.settings.custom_input_enabled);
const canSubmitProposal = computed(() => {
    return !!(
        !isRouletteMode.value
        && currentMember.value
        && !isCurrentUserArbiter.value
        && !isGameplayPaused.value
        && isCollectingProposals.value
        && !ownProposal.value
    );
});
const canSubmitRouletteCustomInput = computed(() => {
    return !!(
        isRouletteMode.value
        && customInputEnabled.value
        && currentMember.value
        && !isGameplayPaused.value
        && isAwaitingRouletteChoice.value
        && isCurrentUserChooser.value
        && decisionSecondsRemaining.value > 0
    );
});
const canSubmitCustomInput = computed(() => (isRouletteMode.value ? canSubmitRouletteCustomInput.value : canSubmitProposal.value));
const shouldShowCustomInput = computed(() => !isRouletteMode.value || customInputEnabled.value);
const customInputPlaceholder = computed(() => {
    if (isGameplayPaused.value) {
        return 'Story paused';
    }

    if (canSubmitCustomInput.value) {
        return isRouletteMode.value ? 'Write your own path...' : 'What Do You Do?';
    }

    if (isRouletteMode.value) {
        return 'Wait for your turn...';
    }

    return ownProposal.value ? 'Choice submitted' : '';
});
const canChooseProposal = computed(
    () => !isRouletteMode.value && !isGameplayPaused.value && isAwaitingArbiter.value && isCurrentUserArbiter.value && availableProposals.value.length > 0,
);
const canAdvanceSession = computed(() => !isGameplayPaused.value && isSessionComplete.value && !isStoryComplete.value && props.lobby.is_host);
const streamingPromptId = ref<string | null>(null);
const localPrompts = ref<PromptInterface[]>([...(props.game?.prompts ?? [])]);
const promptCardElements = ref<Record<string, HTMLElement | null>>({});
const storyColumn = ref<HTMLElement | null>(null);
const prompts = computed(() => localPrompts.value);
const latestPromptId = computed(() => prompts.value.at(-1)?.id ?? null);
const hasVisibleStreamedResponse = computed(() => {
    if (!streamingPromptId.value) {
        return false;
    }

    const streamingPrompt = prompts.value.find((prompt) => prompt.id === streamingPromptId.value);

    return !!streamingPrompt?.response?.trim();
});

const chatMessages = ref<MultiplayerLobbyChatMessageInterface[]>([...props.chatMessages]);
const desktopChatWindow = ref<HTMLElement | null>(null);
const mobileChatWindow = ref<HTMLElement | null>(null);
const chatDraft = ref('');
const isPlayersPanelOpen = ref(true);
const isChatPanelOpen = ref(true);
const activeMobilePanel = ref<MobilePanel>(null);
const isMobile = ref(false);
const copiedRoomCode = ref(false);
const isSubmittingProposal = ref(false);
const isSelectingProposal = ref(false);
const canChooseRouletteChoice = computed(
    () =>
        !isGameplayPaused.value
        && isRouletteMode.value
        && isAwaitingRouletteChoice.value
        && isCurrentUserChooser.value
        && decisionSecondsRemaining.value > 0
        && generatedChoices.value.length > 0,
);
const isAdvancingSession = ref(false);
const isLeavingLobby = ref(false);
const actionError = ref<string | null>(null);
const typingMemberIds = ref(new Set<string>());
const typingTimeouts = new Map<string, ReturnType<typeof setTimeout>>();
let lastTypingWhisperAt = 0;
let isReloadingArbiterState = false;
let isArbiterStateReloadPending = false;
let lobbyHeartbeatTimer: ReturnType<typeof window.setInterval> | null = null;
let isSendingLobbyHeartbeat = false;
let decisionTimerInterval: ReturnType<typeof window.setInterval> | null = null;
const MOBILE_MQ = '(max-width: 1120px)';

function goBack(): void {
    tts.dismiss();
    window.history.back();
}

function togglePlayersPanel(): void {
    if (isMobile.value) {
        activeMobilePanel.value = activeMobilePanel.value === 'players' ? null : 'players';

        return;
    }

    isPlayersPanelOpen.value = !isPlayersPanelOpen.value;
}

function toggleChatPanel(): void {
    if (isMobile.value) {
        activeMobilePanel.value = activeMobilePanel.value === 'chat' ? null : 'chat';

        return;
    }

    isChatPanelOpen.value = !isChatPanelOpen.value;

    if (isChatPanelOpen.value) {
        scrollChatToBottom('auto');
    }
}

function closeMobilePanel(): void {
    activeMobilePanel.value = null;
}

function syncMobile(): void {
    if (typeof window === 'undefined') {
        return;
    }

    isMobile.value = window.matchMedia(MOBILE_MQ).matches;
}

function setMemberTyping(memberId: string, isTyping: boolean): void {
    const existingTimeout = typingTimeouts.get(memberId);

    if (existingTimeout !== undefined) {
        clearTimeout(existingTimeout);
        typingTimeouts.delete(memberId);
    }

    const nextTypingMemberIds = new Set(typingMemberIds.value);

    if (!isTyping) {
        nextTypingMemberIds.delete(memberId);
        typingMemberIds.value = nextTypingMemberIds;

        return;
    }

    nextTypingMemberIds.add(memberId);
    typingMemberIds.value = nextTypingMemberIds;
    typingTimeouts.set(
        memberId,
        setTimeout(() => {
            setMemberTyping(memberId, false);
        }, typingIndicatorTimeout),
    );
}

function clearTypingIndicators(): void {
    for (const timeout of typingTimeouts.values()) {
        clearTimeout(timeout);
    }

    typingTimeouts.clear();
    typingMemberIds.value = new Set();
}

function isMemberTyping(member: MultiplayerLobbyMemberInterface): boolean {
    return (
        !isGameplayPaused.value &&
        member.left_at === null &&
        isCollectingProposals.value &&
        !proposalByMemberId.value.has(member.id) &&
        typingMemberIds.value.has(member.id)
    );
}

function handleTypingWhisper(signal: ArbiterTypingWhisper): void {
    if (
        isGameplayPaused.value ||
        !isCollectingProposals.value ||
        !signal ||
        typeof signal.is_typing !== 'boolean' ||
        typeof signal.user_id !== 'number'
    ) {
        return;
    }

    const member = members.value.find((candidate) => candidate.user.id === signal.user_id);

    if (!member || String(member.user.id) === String(currentUserId.value)) {
        return;
    }

    setMemberTyping(member.id, signal.is_typing);
}

function whisperTyping(isTyping: boolean): void {
    const member = currentMember.value;

    if (!member) {
        return;
    }

    multiplayerLobbyPresenceChannel.channel().whisper(typingWhisperEvent, {
        is_typing: isTyping,
        user_id: member.user.id,
    });
}

function handleProposalTyping(isTyping: boolean): void {
    const member = currentMember.value;

    if (!member) {
        return;
    }

    const shouldShowTyping = isTyping && canSubmitProposal.value;

    setMemberTyping(member.id, shouldShowTyping);

    if (!shouldShowTyping) {
        lastTypingWhisperAt = 0;
        whisperTyping(false);

        return;
    }

    const now = Date.now();

    if (now - lastTypingWhisperAt < typingWhisperInterval) {
        return;
    }

    lastTypingWhisperAt = now;
    whisperTyping(true);
}

function setSourcePromptValue(sourcePromptId: string, value: string | null): void {
    localPrompts.value = localPrompts.value.map((prompt) => (String(prompt.id) === String(sourcePromptId) ? { ...prompt, prompt: value } : prompt));
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
        (prompt) => prompt.id !== streamingPromptId.value && !serverIds.has(String(prompt.id)) && String(prompt.id) > newestServerId,
    );

    return [...reconciled, ...newerLocalPrompts];
}

watch(
    () => props.game?.prompts,
    (serverPrompts) => {
        const streamingPrompt = streamingPromptId.value ? (localPrompts.value.find((prompt) => prompt.id === streamingPromptId.value) ?? null) : null;
        const mergedPrompts = mergeServerPrompts([...(serverPrompts ?? [])]);

        localPrompts.value = streamingPrompt
            ? [...mergedPrompts.filter((prompt) => prompt.id !== streamingPrompt.id), streamingPrompt]
            : mergedPrompts;
    },
    { deep: true },
);

watch(
    () => props.chatMessages,
    (serverChatMessages) => {
        for (const message of serverChatMessages) {
            appendChatMessage(message);
        }
    },
);

watch(
    () => [props.arbiter.source_prompt_id, props.arbiter.turn_status, selectedProposal.value?.text] as const,
    ([sourcePromptId, status, selectedProposalText]) => {
        if (status !== 'narrating' || !sourcePromptId || !selectedProposalText) {
            return;
        }

        setSourcePromptValue(sourcePromptId, selectedProposalText);
    },
    { immediate: true },
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
        .map((prompt) => (prompt.id === signal.source_prompt_id ? { ...prompt, prompt: signal.source_prompt_value ?? prompt.prompt } : prompt))
        .filter((prompt) => prompt.id !== transientPromptId && prompt.id !== responsePrompt.id);
    localPrompts.value = [...localPrompts.value, responsePrompt];
    if (streamingPromptId.value === transientPromptId) {
        streamingPromptId.value = null;
    }
}

function setPromptCardElement(promptId: string, element: unknown): void {
    promptCardElements.value[promptId] = element instanceof HTMLElement ? element : null;
}

function scrollToPromptStart(promptId: string, topOffset = 112): void {
    const element = promptCardElements.value[promptId];

    if (!element) {
        return;
    }

    const column = storyColumn.value;

    if (column !== null && column.scrollHeight > column.clientHeight + 1) {
        const columnRect = column.getBoundingClientRect();
        const elementRect = element.getBoundingClientRect();
        const top = Math.max(0, column.scrollTop + elementRect.top - columnRect.top - topOffset);

        column.scrollTo({ top, behavior: 'smooth' });

        return;
    }

    const top = Math.max(0, window.scrollY + element.getBoundingClientRect().top - topOffset);

    window.scrollTo({ top, behavior: 'smooth' });
}

function memberAvatar(member: MultiplayerLobbyMemberInterface, index: number): string {
    return member.user.avatar || [billyAvatar, kevinAvatar, miraAvatar][index % 3];
}

function memberDisplayName(member: MultiplayerLobbyMemberInterface): string {
    return member.user.full_name;
}

function memberRole(member: MultiplayerLobbyMemberInterface): string {
    if (member.id === props.arbiter.current_arbiter_member_id) {
        return turnRoleLabel.value;
    }

    return member.role === 'host' ? 'Host' : 'Player';
}

const activeTurnMemberId = computed(() => {
    if (!isAwaitingArbiter.value && !isAwaitingRouletteChoice.value) {
        return null;
    }

    return props.arbiter.current_arbiter_member_id;
});

const upNextTurnMemberId = computed(() => {
    const activeMemberId = activeTurnMemberId.value;

    if (activeMemberId === null || members.value.length < 2) {
        return null;
    }

    const activeIndex = members.value.findIndex((member) => member.id === activeMemberId);

    if (activeIndex === -1) {
        return null;
    }

    return members.value[(activeIndex + 1) % members.value.length]?.id ?? null;
});

function memberState(member: MultiplayerLobbyMemberInterface): string {
    if (member.left_at !== null) {
        return 'Left game';
    }

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
        if (isAwaitingArbiter.value || isAwaitingRouletteChoice.value) {
            return isRouletteMode.value ? 'Choosing a path…' : 'Choosing…';
        }

        return turnRoleLabel.value;
    }

    if (isMemberTyping(member)) {
        return 'Typing…';
    }

    if (proposalByMemberId.value.has(member.id)) {
        return 'Choice submitted';
    }

    return isNarrating.value ? 'Listening…' : 'Thinking…';
}

const players = computed<Player[]>(() =>
    members.value.map((member, index) => {
        const isActiveTurn = member.id === activeTurnMemberId.value;
        const isUpNext = member.id === upNextTurnMemberId.value;

        return {
            id: member.id,
            name: memberDisplayName(member),
            role: memberRole(member),
            state: memberState(member),
            isTyping: isMemberTyping(member),
            hasLeft: member.left_at !== null,
            avatar: memberAvatar(member, index),
            tone: member.left_at !== null ? 'left' : member.id === props.arbiter.current_arbiter_member_id ? 'gold' : 'cyan',
            isActiveTurn,
            isUpNext,
            isCurrentUser: member.user.id === currentUserId.value,
            isArbiter: member.id === props.arbiter.current_arbiter_member_id,
            host: member.role === 'host',
        };
    }),
);

const messages = computed<ChatMessage[]>(() =>
    chatMessages.value.map((message) => ({
        id: message.id,
        name: message.user.full_name,
        text: message.body,
        time: formatChatMessageTime(message.created_at),
        tone: chatMessageTone(message),
        createdAt: message.created_at,
    })),
);

const decisionTitle = computed(() => {
    if (isGameplayPaused.value) {
        return 'Story Paused';
    }

    if (isStoryComplete.value) {
        return 'Story Complete';
    }

    if (isAwaitingArbiter.value) {
        return `It’s ${currentArbiter.value?.user.full_name ?? 'the Arbiter'}’s Turn!`;
    }

    if (isAwaitingRouletteChoice.value) {
        return `It’s ${currentChooser.value?.user.full_name ?? 'the current player'}’s Turn!`;
    }

    if (isNarrating.value) {
        return 'The Story Is Moving Forward';
    }

    if (isSessionComplete.value) {
        return 'Episode Complete';
    }

    return isRouletteMode.value ? 'The Wheel Is Turning' : 'Everyone Gets a Say';
});

const decisionDescription = computed(() => {
    if (isGameplayPaused.value) {
        return `Waiting for ${pausedMemberLabel.value} to rejoin before play can continue.`;
    }

    if (isStoryComplete.value) {
        return 'Every chapter of this shared story has reached its ending.';
    }

    if (isAwaitingArbiter.value) {
        return 'Everyone has contributed. The Arbiter is making the final choice.';
    }

    if (isAwaitingRouletteChoice.value) {
        return 'Xen has presented the paths. The current player chooses what becomes canon.';
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

    return isRouletteMode.value
        ? 'The next player receives Xen’s generated paths.'
        : 'Write your choice. The Arbiter will make the final decision after everyone has contributed.';
});

const waitingStatus = computed(() => {
    if (isGameplayPaused.value) {
        return `Story paused while ${pausedMemberLabel.value} ${pausedMembers.value.length === 1 ? 'rejoins' : 'rejoin'}.`;
    }

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
            ? isLastSession.value
                ? 'End the story for everyone when you are ready.'
                : 'Continue to the next chapter when you are ready.'
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

    if (isAwaitingRouletteChoice.value) {
        return isCurrentUserChooser.value
            ? 'Choose the path that becomes canon.'
            : `Waiting for ${currentChooser.value?.user.full_name ?? 'the current player'} to choose…`;
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

watch([isCollectingProposals, isGameplayPaused], ([isCollecting, isPaused]) => {
    if (!isCollecting || isPaused) {
        clearTypingIndicators();
    }
});

watch(pausedMembers, (departedMembers) => {
    if (departedMembers.length > 0) {
        isPlayersPanelOpen.value = true;
    }
});

watch(isMobile, (mobile) => {
    if (!mobile) {
        closeMobilePanel();
    }
});

function chatMessageTone(message: MultiplayerLobbyChatMessageInterface): ChatTone {
    if (message.user.id === currentUserId.value) {
        return 'fantasy';
    }

    return message.user.id === props.lobby.host.id ? 'cyan' : 'green';
}

function formatChatMessageTime(value: string | null): string {
    if (!value) {
        return '';
    }

    const timestamp = new Date(value);

    if (Number.isNaN(timestamp.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(timestamp);
}

function scrollChatToBottom(behavior: ScrollBehavior = 'smooth'): void {
    void nextTick(() => {
        window.requestAnimationFrame(() => {
            for (const element of [desktopChatWindow.value, mobileChatWindow.value]) {
                if (element === null) {
                    continue;
                }

                element.scrollTo({
                    top: element.scrollHeight,
                    behavior,
                });
            }
        });
    });
}

watch(activeMobilePanel, (panel) => {
    if (panel === 'chat') {
        scrollChatToBottom();
    }
});

function appendChatMessage(message: MultiplayerLobbyChatMessageInterface): void {
    if (chatMessages.value.some((existingMessage) => existingMessage.id === message.id)) {
        return;
    }

    chatMessages.value = [...chatMessages.value, message];
    scrollChatToBottom();
}

function createChatMessageId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function handleChatWhisper(signal: MultiplayerLobbyChatMessageWhisper): void {
    if (
        !signal ||
        typeof signal.id !== 'string' ||
        typeof signal.body !== 'string' ||
        typeof signal.created_at !== 'string' ||
        typeof signal.user_id !== 'number'
    ) {
        return;
    }

    const sender = members.value.find((member) => member.user.id === signal.user_id);
    const body = signal.body.trim();

    if (!sender || sender.left_at !== null || !body) {
        return;
    }

    appendChatMessage({
        id: signal.id,
        body: body.slice(0, 1_000),
        created_at: signal.created_at,
        user: sender.user,
    });
}

function submitChat(): void {
    const body = chatDraft.value.trim();
    const sender = currentMember.value;

    if (!body || !sender || sender.left_at !== null) {
        return;
    }

    const message = {
        id: createChatMessageId(),
        body: body.slice(0, 1_000),
        created_at: new Date().toISOString(),
        user: sender.user,
    };

    appendChatMessage(message);
    chatDraft.value = '';
    multiplayerLobbyPresenceChannel.channel().whisper(chatWhisperEvent, {
        id: message.id,
        body: message.body,
        created_at: message.created_at ?? new Date().toISOString(),
        user_id: sender.user.id,
    });
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

function submitRouletteCustomChoice(proposal: string): void {
    if (!canSubmitRouletteCustomInput.value || isSubmittingProposal.value) {
        return;
    }

    actionError.value = null;
    tts.primeAudio();
    isSubmittingProposal.value = true;
    const sourcePromptId = props.arbiter.source_prompt_id;
    const previousPromptValue = localPrompts.value.find((prompt) => sourcePromptId && String(prompt.id) === String(sourcePromptId))?.prompt ?? null;

    if (sourcePromptId) {
        setSourcePromptValue(sourcePromptId, proposal);
    }

    router.post(
        storeRouletteChoice(props.lobby.room_code).url,
        { proposal },
        {
            preserveScroll: true,
            onError: (errors) => {
                if (sourcePromptId) {
                    setSourcePromptValue(sourcePromptId, previousPromptValue);
                }
                actionError.value = errors.lobby ?? errors.proposal ?? 'We could not submit that path.';
            },
            onFinish: () => {
                isSubmittingProposal.value = false;
            },
        },
    );
}

function submitCustomInput(proposal: string): void {
    if (isRouletteMode.value) {
        submitRouletteCustomChoice(proposal);

        return;
    }

    submitProposal(proposal);
}

function selectProposal(proposal: MultiplayerLobbyProposalInterface): void {
    if (!canChooseProposal.value || !proposal.text || isSelectingProposal.value) {
        return;
    }

    actionError.value = null;
    tts.primeAudio();
    isSelectingProposal.value = true;
    const sourcePromptId = props.arbiter.source_prompt_id;
    const previousPromptValue = localPrompts.value.find((prompt) => sourcePromptId && String(prompt.id) === String(sourcePromptId))?.prompt ?? null;

    if (sourcePromptId) {
        setSourcePromptValue(sourcePromptId, proposal.text);
    }

    router.post(
        lobbyEndpoint(`/proposals/${proposal.id}/select`),
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                if (sourcePromptId) {
                    setSourcePromptValue(sourcePromptId, previousPromptValue);
                }
                actionError.value = errors.lobby ?? 'We could not select that proposal.';
            },
            onFinish: () => {
                isSelectingProposal.value = false;
            },
        },
    );
}

function chooseRouletteChoice(choice: MultiplayerGeneratedChoiceInterface): void {
    if (!canChooseRouletteChoice.value || isSelectingProposal.value) {
        return;
    }

    actionError.value = null;
    tts.primeAudio();
    isSelectingProposal.value = true;
    const sourcePromptId = props.arbiter.source_prompt_id;
    const previousPromptValue = localPrompts.value.find((prompt) => sourcePromptId && String(prompt.id) === String(sourcePromptId))?.prompt ?? null;

    if (sourcePromptId) {
        setSourcePromptValue(sourcePromptId, choice.text);
    }

    router.post(
        storeRouletteChoice(props.lobby.room_code).url,
        { choice_index: choice.index },
        {
            preserveScroll: true,
            onError: (errors) => {
                if (sourcePromptId) {
                    setSourcePromptValue(sourcePromptId, previousPromptValue);
                }
                actionError.value = errors.lobby ?? 'We could not select that path.';
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

function leaveRoom(): void {
    if (isLeavingLobby.value) {
        return;
    }

    actionError.value = null;
    isLeavingLobby.value = true;
    handleProposalTyping(false);
    tts.dismiss();

    router.delete(showMultiplayerLobby(props.lobby.room_code).url, {
        onError: (errors) => {
            actionError.value = errors.lobby ?? 'We could not leave the room.';
        },
        onFinish: () => {
            isLeavingLobby.value = false;
        },
    });
}

function reloadArbiterState(): void {
    if (isReloadingArbiterState) {
        isArbiterStateReloadPending = true;

        return;
    }

    isReloadingArbiterState = true;
    router.reload({
        only: ['lobby', 'game', 'arbiter'],
        preserveScroll: true,
        onFinish: () => {
            isReloadingArbiterState = false;

            if (isArbiterStateReloadPending) {
                isArbiterStateReloadPending = false;
                reloadArbiterState();
            }
        },
    });
}

function csrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return token ? decodeURIComponent(token) : '';
}

function sendLobbyHeartbeat(): void {
    const member = currentMember.value;

    if (isSendingLobbyHeartbeat || !member || member.left_at !== null) {
        return;
    }

    isSendingLobbyHeartbeat = true;

    void fetch(lobbyEndpoint('/heartbeat'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
    })
        .catch(() => {})
        .finally(() => {
            isSendingLobbyHeartbeat = false;
        });
}

function startLobbyHeartbeat(): void {
    sendLobbyHeartbeat();
    lobbyHeartbeatTimer = window.setInterval(sendLobbyHeartbeat, lobbyHeartbeatInterval);
}

function stopLobbyHeartbeat(): void {
    if (lobbyHeartbeatTimer === null) {
        return;
    }

    window.clearInterval(lobbyHeartbeatTimer);
    lobbyHeartbeatTimer = null;
}

function handleDocumentVisibilityChange(): void {
    if (document.visibilityState === 'visible') {
        sendLobbyHeartbeat();
    }
}

function startDecisionTimer(): void {
    decisionTimerNow.value = Date.now();
    decisionTimerInterval = window.setInterval(() => {
        decisionTimerNow.value = Date.now();
    }, 1_000);
}

function stopDecisionTimer(): void {
    if (decisionTimerInterval === null) {
        return;
    }

    window.clearInterval(decisionTimerInterval);
    decisionTimerInterval = null;
}

useEchoPresence<ArbiterStateSignal>(lobbyChannelName, '.multiplayer.arbiter.state.changed', (signal) => {
    if (signal.lobby_id !== props.lobby.id) {
        return;
    }

    reloadArbiterState();
});

useEchoPresence<MultiplayerLobbyMembershipSignal>(lobbyChannelName, '.multiplayer.lobby.member.left', (signal) => {
    if (signal.lobby.id !== props.lobby.id) {
        return;
    }

    reloadArbiterState();
});

useEchoPresence<MultiplayerLobbyMembershipSignal>(lobbyChannelName, '.multiplayer.lobby.member.joined', (signal) => {
    if (signal.lobby.id !== props.lobby.id) {
        return;
    }

    reloadArbiterState();
});

useEchoPresence<ArbiterNarrationSignal>(lobbyChannelName, '.multiplayer.arbiter.narration.streamed', (signal) => {
    if (signal.lobby_id !== props.lobby.id) {
        return;
    }

    if (signal.phase === 'ready') {
        actionError.value = null;
        if (signal.source_prompt_value) {
            setSourcePromptValue(signal.source_prompt_id, signal.source_prompt_value);
        }
        ensureStreamingPrompt(signal.source_prompt_id);

        return;
    }

    if (signal.phase === 'delta' && signal.text) {
        const isFirstVisibleDelta = !hasVisibleStreamedResponse.value;
        const promptId = `stream-${signal.source_prompt_id}`;

        appendNarrationDelta(signal.source_prompt_id, signal.text);

        if (isFirstVisibleDelta) {
            nextTick(() => {
                scrollToPromptStart(promptId, Math.min(320, window.innerHeight * 0.38));
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
});

onMounted(() => {
    syncMobile();
    window.matchMedia(MOBILE_MQ).addEventListener('change', syncMobile);
    multiplayerLobbyPresenceChannel.channel().listenForWhisper(typingWhisperEvent, handleTypingWhisper);
    multiplayerLobbyPresenceChannel.channel().listenForWhisper(chatWhisperEvent, handleChatWhisper);
    scrollChatToBottom();
    startLobbyHeartbeat();
    startDecisionTimer();
    document.addEventListener('visibilitychange', handleDocumentVisibilityChange);

    if (isGameplayPaused.value && pausedMembers.value.length === 0) {
        reloadArbiterState();
    }
});

onUnmounted(() => {
    window.matchMedia(MOBILE_MQ).removeEventListener('change', syncMobile);
    multiplayerLobbyPresenceChannel.channel().stopListeningForWhisper(typingWhisperEvent, handleTypingWhisper);
    multiplayerLobbyPresenceChannel.channel().stopListeningForWhisper(chatWhisperEvent, handleChatWhisper);
    clearTypingIndicators();
    stopLobbyHeartbeat();
    stopDecisionTimer();
    document.removeEventListener('visibilitychange', handleDocumentVisibilityChange);
    tts.dismiss();
});
</script>

<template>
    <Head :title="isRouletteMode ? 'Roulette' : 'Arbiter'" />

    <main class="arbiter-page">
        <div
            v-if="gameplayBackgroundImage"
            class="arbiter-scene"
            :style="{ backgroundImage: `url(${gameplayBackgroundImage})` }"
            aria-hidden="true"
        />

        <BaseButton severity="header-glass" :icon-only="true" class="arbiter-back" title="Go back" @click="goBack">
            <img :src="backIcon" alt="" class="h-6 w-[15px]" aria-hidden="true" />
        </BaseButton>

        <div class="arbiter-shell">
            <header class="arbiter-topbar">
                <div v-if="tts.isActive.value && !isMobile" class="arbiter-media-player" aria-label="Narration controls">
                    <GameplayMediaPlayer />
                </div>

                <div class="arbiter-mobile-panel-controls" role="group" aria-label="Room panels">
                    <BaseButton
                        severity="header-glass"
                        :icon-only="true"
                        class="arbiter-mobile-panel-button"
                        :class="{ 'gameplay-header-glass--active': activeMobilePanel === 'players' }"
                        :title="activeMobilePanel === 'players' ? 'Close Players' : 'Open Players'"
                        :aria-expanded="activeMobilePanel === 'players'"
                        aria-controls="arbiter-players-drawer"
                        @click="togglePlayersPanel"
                    >
                        <img :src="playersIcon" alt="" class="size-5" aria-hidden="true" />
                    </BaseButton>
                    <BaseButton
                        severity="header-glass"
                        :icon-only="true"
                        class="arbiter-mobile-panel-button"
                        :class="{ 'gameplay-header-glass--active': activeMobilePanel === 'chat' }"
                        :title="activeMobilePanel === 'chat' ? 'Close Chat' : 'Open Chat'"
                        :aria-expanded="activeMobilePanel === 'chat'"
                        aria-controls="arbiter-chat-drawer"
                        @click="toggleChatPanel"
                    >
                        <img :src="chatIcon" alt="" class="size-5" aria-hidden="true" />
                    </BaseButton>
                </div>
            </header>

            <div class="arbiter-grid">
                <aside v-if="!isMobile" class="arbiter-rail arbiter-left-rail" aria-label="Players">
                    <div class="rail-tabs">
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Help">
                            <img :src="questionIcon" alt="" class="size-[25.2px]" aria-hidden="true" />
                        </BaseButton>
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Settings">
                            <img :src="settingsIcon" alt="" class="size-[25.2px]" aria-hidden="true" />
                        </BaseButton>
                        <BaseButton
                            severity="header-glass"
                            class="rail-tab"
                            :class="{ 'gameplay-header-glass--active': isPlayersPanelOpen }"
                            :title="isPlayersPanelOpen ? 'Hide Players' : 'Show Players'"
                            :aria-expanded="isPlayersPanelOpen"
                            aria-controls="arbiter-players-panel"
                            @click="togglePlayersPanel"
                        >
                            <span class="rail-tab-content">
                                <img :src="playersIcon" alt="" class="size-[25.2px]" aria-hidden="true" />
                                <span>Players</span>
                            </span>
                        </BaseButton>
                    </div>

                    <section v-show="isPlayersPanelOpen" id="arbiter-players-panel" class="arbiter-panel">
                        <div class="panel-title">
                            <span>Players</span><small>{{ members.length }}/{{ props.lobby.max_players }}</small>
                            <span v-if="pausedMembers.length" class="players-left-count">
                                {{ pausedMembers.length }} {{ pausedMembers.length === 1 ? 'player left' : 'players left' }}
                            </span>
                        </div>
                        <div class="player-list">
                            <article
                                v-for="player in players"
                                :key="player.id"
                                :class="[
                                    'player-card',
                                    {
                                        'player-card--left': player.hasLeft,
                                        'player-card--active': player.isActiveTurn,
                                        'player-card--up-next': player.isUpNext,
                                    },
                                ]"
                            >
                                <img class="player-avatar" :src="player.avatar" :alt="player.name + ' avatar'" />
                                <div class="player-details">
                                    <div class="player-name-row">
                                        <span class="player-name">{{ player.name }}</span>
                                        <span class="player-badges">
                                            <span v-if="player.hasLeft" class="player-left-badge">Left game</span>
                                            <span
                                                v-if="player.isCurrentUser"
                                                class="player-badge-icon player-badge-icon--you"
                                                role="img"
                                                aria-label="You"
                                                title="You"
                                            >
                                                <LucideUserRound class="size-3" :stroke-width="1.75" aria-hidden="true" />
                                            </span>
                                            <span
                                                v-if="player.isArbiter"
                                                class="player-badge-icon player-badge-icon--arbiter"
                                                role="img"
                                                :aria-label="turnRoleLabel"
                                                :title="turnRoleLabel"
                                            >
                                                <LucideStar class="size-3" :stroke-width="1.75" aria-hidden="true" />
                                            </span>
                                            <span v-if="player.host" class="player-host">
                                                <LucideCrown class="size-3" :stroke-width="1.75" />
                                                Host
                                            </span>
                                        </span>
                                    </div>
                                    <span :class="['player-state', 'player-state--' + player.tone, { 'player-state--typing': player.isTyping }]">
                                        <i />
                                        <span v-if="player.isTyping" class="player-typing-label">
                                            Typing<span class="typing-dots" aria-hidden="true"><span /><span /><span /></span>
                                        </span>
                                        <template v-else>{{ player.state }}</template>
                                    </span>
                                </div>
                                <span class="player-score">{{ player.role }}</span>
                            </article>
                        </div>

                        <div class="invite-card">
                            <div class="invite-label">
                                <img :src="playersIcon" alt="" class="size-5" aria-hidden="true" />
                                <span>Invite Players</span>
                            </div>
                            <div class="room-code-card">
                                <div>
                                    <span>Room Link</span><strong>{{ roomCode }}</strong>
                                </div>
                                <button type="button" :aria-label="copiedRoomCode ? 'Room code copied' : 'Copy room code'" @click="copyRoomCode">
                                    <img :src="copyIcon" alt="" aria-hidden="true" />
                                </button>
                            </div>
                            <span v-if="copiedRoomCode" class="copy-confirmation" role="status">Room code copied</span>
                        </div>
                        <button type="button" class="leave-room-button" :disabled="isLeavingLobby" @click="leaveRoom">
                            {{ isLeavingLobby ? 'Leaving…' : 'Leave Room' }}
                        </button>
                    </section>
                </aside>

                <section ref="storyColumn" class="arbiter-story-column" aria-labelledby="arbiter-story-title">
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

                        <p v-else-if="prompts.length === 0" class="story-history-empty">
                            {{ isGameplayPaused ? 'The story is paused until every player returns.' : 'Xen is preparing the opening narration…' }}
                        </p>
                    </div>

                    <section class="decision-section" aria-labelledby="decision-title">
                        <div class="decision-rule" aria-hidden="true" />
                        <div class="decision-heading">
                            <div>
                                <h2 id="decision-title">{{ decisionTitle }}</h2>
                                <p>{{ decisionDescription }}</p>
                            </div>
                            <span
                                v-if="!isRouletteMode || hasDecisionTimer"
                                :class="['decision-timer', { 'decision-timer--urgent': isDecisionTimerUrgent }]"
                                :aria-label="`${decisionSecondsRemaining} seconds decision window`"
                            >
                                {{ decisionSecondsRemaining }}<small>s</small>
                            </span>
                        </div>

                        <div v-if="isRouletteMode && isAwaitingRouletteChoice" class="proposal-choice-list">
                            <button
                                v-for="choice in generatedChoices"
                                :key="choice.index"
                                type="button"
                                class="proposal-choice-card"
                                :disabled="!canChooseRouletteChoice || isSelectingProposal"
                                @click="chooseRouletteChoice(choice)"
                            >
                                <span class="proposal-choice-radio" aria-hidden="true" />
                                <span>{{ choice.text }}</span>
                            </button>
                        </div>

                        <div v-else-if="canChooseProposal" class="proposal-choice-list">
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

                        <div v-else-if="!isRouletteMode && (isCollectingProposals || isAwaitingArbiter)" class="contributions">
                            <article
                                v-for="(member, index) in nonArbiterMembers"
                                :key="member.id"
                                class="contribution-card"
                                :class="{
                                    'contribution-card--active': member.user.id === currentUserId,
                                    'contribution-card--waiting': !proposalByMemberId.has(member.id),
                                    'contribution-card--selected': proposalByMemberId.get(member.id)?.is_selected,
                                    'contribution-card--away': member.left_at !== null,
                                }"
                            >
                                <img class="contribution-avatar" :src="memberAvatar(member, index + 1)" :alt="`${member.user.full_name} avatar`" />
                                <div class="contribution-content">
                                    <strong>{{ memberDisplayName(member) }}</strong>
                                    <span v-if="member.left_at !== null" class="typing-label">Rejoining…</span>
                                    <span v-else-if="proposalByMemberId.get(member.id)?.text" class="submitted-proposal">
                                        {{ proposalByMemberId.get(member.id)?.text }}
                                    </span>
                                    <span v-else-if="proposalByMemberId.has(member.id)" class="typing-label">Choice submitted</span>
                                    <span v-else-if="isMemberTyping(member)" class="typing-label typing-label--active">
                                        Typing<span class="typing-dots" aria-hidden="true"><span /><span /><span /></span>
                                    </span>
                                    <span v-else-if="member.user.id === currentUserId" class="proposal-prompt">What do you propose?</span>
                                    <span v-else class="typing-label">Thinking <span aria-hidden="true">•••</span></span>
                                </div>
                                <p v-if="member.user.id === currentUserId" class="contribution-tip">
                                    {{
                                        ownProposal
                                            ? 'Your proposal is waiting for the Arbiter.'
                                            : 'Share the choice point you want the Arbiter to consider.'
                                    }}
                                </p>
                            </article>
                        </div>
                        <div v-if="isSessionComplete && !isStoryComplete && !isGameplayPaused" class="session-transition">
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

                <aside v-if="!isMobile" class="arbiter-rail arbiter-right-rail" aria-label="Chat">
                    <div class="rail-tabs rail-tabs--right">
                        <BaseButton
                            severity="header-glass"
                            class="rail-tab"
                            :class="{ 'gameplay-header-glass--active': isChatPanelOpen }"
                            :title="isChatPanelOpen ? 'Hide Chat' : 'Show Chat'"
                            :aria-expanded="isChatPanelOpen"
                            aria-controls="arbiter-chat-panel"
                            @click="toggleChatPanel"
                        >
                            <span class="rail-tab-content">
                                <img :src="chatIcon" alt="" class="size-[25.2px]" aria-hidden="true" />
                                <span>Chat</span>
                            </span>
                        </BaseButton>
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Power">
                            <img :src="lightningIcon" alt="" class="size-[25px]" aria-hidden="true" />
                        </BaseButton>
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Game order">
                            <LucideList class="size-[25.2px] text-gray-300" :stroke-width="1.75" />
                        </BaseButton>
                        <BaseButton severity="header-glass" :icon-only="true" class="rail-icon-button" title="Notes">
                            <img :src="notesIcon" alt="" class="size-[22.68px]" aria-hidden="true" />
                        </BaseButton>
                    </div>

                    <section v-show="isChatPanelOpen" id="arbiter-chat-panel" class="arbiter-panel chat-panel">
                        <div ref="desktopChatWindow" class="chat-window" aria-live="polite">
                            <div class="chat-window-content">
                                <p v-if="messages.length === 0" class="chat-empty">No messages yet. Say hello to the room.</p>
                                <article v-for="message in messages" :key="message.id" class="chat-message">
                                    <div class="chat-message-meta">
                                        <strong :class="'chat-tone--' + message.tone">{{ message.name }}</strong>
                                        <time :datetime="message.createdAt ?? undefined">{{ message.time }}</time>
                                    </div>
                                    <p>{{ message.text }}</p>
                                </article>
                            </div>
                        </div>
                        <form class="chat-compose" @submit.prevent="submitChat">
                            <label class="sr-only" for="arbiter-chat-message">Type a message</label>
                            <input
                                id="arbiter-chat-message"
                                v-model="chatDraft"
                                type="text"
                                placeholder="Type a message..."
                                autocomplete="off"
                                maxlength="1000"
                                :disabled="!currentMember || currentMember.left_at !== null"
                            />
                            <button
                                type="submit"
                                :disabled="!chatDraft.trim() || !currentMember || currentMember.left_at !== null"
                                aria-label="Send message"
                            >
                                <img :src="sendIcon" alt="" class="rotate-90" aria-hidden="true" />
                            </button>
                        </form>
                    </section>
                </aside>
            </div>
        </div>

        <footer v-if="shouldShowCustomInput || isGameplayPaused || (tts.isActive.value && isMobile)" class="arbiter-proposal-dock flex-col">
            <section v-if="isGameplayPaused" class="gameplay-paused-notice flex items-center backdrop-blur-[2px]" role="status" aria-live="polite">
                <span class="gameplay-paused-eyebrow">Paused</span>
                <p class="flex-1">Waiting for {{ pausedMemberLabel }} to rejoin. You can still use the room controls or leave.</p>
            </section>
            <div v-if="tts.isActive.value && isMobile" class="arbiter-proposal-media-player" aria-label="Narration controls">
                <GameplayMediaPlayer />
            </div>
            <GameplayInput
                v-if="shouldShowCustomInput"
                class="arbiter-proposal-input"
                :disabled="!canSubmitCustomInput || isSubmittingProposal"
                :placeholder="customInputPlaceholder"
                @submit="submitCustomInput"
                @typing="handleProposalTyping"
            />
        </footer>
    </main>

    <GameplayBottomSheet :open="isMobile && activeMobilePanel === 'players'" title="Players" @close="closeMobilePanel">
        <section id="arbiter-players-drawer" class="arbiter-mobile-panel arbiter-mobile-players-panel">
            <div class="panel-title">
                <span>Players</span><small>{{ members.length }}/{{ props.lobby.max_players }}</small>
                <span v-if="pausedMembers.length" class="players-left-count">
                    {{ pausedMembers.length }} {{ pausedMembers.length === 1 ? 'player left' : 'players left' }}
                </span>
            </div>
            <div class="player-list">
                <article
                    v-for="player in players"
                    :key="player.id"
                    :class="[
                        'player-card',
                        {
                            'player-card--left': player.hasLeft,
                            'player-card--active': player.isActiveTurn,
                            'player-card--up-next': player.isUpNext,
                        },
                    ]"
                >
                    <img class="player-avatar" :src="player.avatar" :alt="player.name + ' avatar'" />
                    <div class="player-details">
                        <div class="player-name-row">
                            <span class="player-name">{{ player.name }}</span>
                            <span class="player-badges">
                                <span v-if="player.hasLeft" class="player-left-badge">Left game</span>
                                <span
                                    v-if="player.isCurrentUser"
                                    class="player-badge-icon player-badge-icon--you"
                                    role="img"
                                    aria-label="You"
                                    title="You"
                                >
                                    <LucideUserRound class="size-3" :stroke-width="1.75" aria-hidden="true" />
                                </span>
                                <span
                                    v-if="player.isArbiter"
                                    class="player-badge-icon player-badge-icon--arbiter"
                                    role="img"
                                    :aria-label="turnRoleLabel"
                                    :title="turnRoleLabel"
                                >
                                    <LucideStar class="size-3" :stroke-width="1.75" aria-hidden="true" />
                                </span>
                                <span v-if="player.host" class="player-host">
                                    <LucideCrown class="size-3" :stroke-width="1.75" />
                                    Host
                                </span>
                            </span>
                        </div>
                        <span :class="['player-state', 'player-state--' + player.tone, { 'player-state--typing': player.isTyping }]">
                            <i />
                            <span v-if="player.isTyping" class="player-typing-label">
                                Typing<span class="typing-dots" aria-hidden="true"><span /><span /><span /></span>
                            </span>
                            <template v-else>{{ player.state }}</template>
                        </span>
                    </div>
                    <span class="player-score">{{ player.role }}</span>
                </article>
            </div>

            <div class="invite-card">
                <div class="invite-label">
                    <img :src="playersIcon" alt="" class="size-5" aria-hidden="true" />
                    <span>Invite Players</span>
                </div>
                <div class="room-code-card">
                    <div>
                        <span>Room Link</span><strong>{{ roomCode }}</strong>
                    </div>
                    <button type="button" :aria-label="copiedRoomCode ? 'Room code copied' : 'Copy room code'" @click="copyRoomCode">
                        <img :src="copyIcon" alt="" aria-hidden="true" />
                    </button>
                </div>
                <span v-if="copiedRoomCode" class="copy-confirmation" role="status">Room code copied</span>
            </div>
            <button type="button" class="leave-room-button" :disabled="isLeavingLobby" @click="leaveRoom">
                {{ isLeavingLobby ? 'Leaving…' : 'Leave Room' }}
            </button>
        </section>
    </GameplayBottomSheet>

    <GameplayBottomSheet :open="isMobile && activeMobilePanel === 'chat'" title="Chat" @close="closeMobilePanel">
        <section id="arbiter-chat-drawer" class="arbiter-mobile-panel arbiter-mobile-chat-panel">
            <div ref="mobileChatWindow" class="chat-window" aria-live="polite">
                <div class="chat-window-content">
                    <p v-if="messages.length === 0" class="chat-empty">No messages yet. Say hello to the room.</p>
                    <article v-for="message in messages" :key="message.id" class="chat-message">
                        <div class="chat-message-meta">
                            <strong :class="'chat-tone--' + message.tone">{{ message.name }}</strong>
                            <time :datetime="message.createdAt ?? undefined">{{ message.time }}</time>
                        </div>
                        <p>{{ message.text }}</p>
                    </article>
                </div>
            </div>
            <form class="chat-compose" @submit.prevent="submitChat">
                <label class="sr-only" for="arbiter-mobile-chat-message">Type a message</label>
                <input
                    id="arbiter-mobile-chat-message"
                    v-model="chatDraft"
                    type="text"
                    placeholder="Type a message..."
                    autocomplete="off"
                    maxlength="1000"
                    :disabled="!currentMember || currentMember.left_at !== null"
                />
                <button type="submit" :disabled="!chatDraft.trim() || !currentMember || currentMember.left_at !== null" aria-label="Send message">
                    <img :src="sendIcon" alt="" class="rotate-90" aria-hidden="true" />
                </button>
            </form>
        </section>
    </GameplayBottomSheet>
</template>

<style scoped>
:global(body) {
    margin: 0;
    background: #000;
}

.arbiter-page {
    position: relative;
    height: 100svh;
    overflow: hidden;
    background: #000;
    color: #fff;
    font-family: 'Inter', sans-serif;
}

.arbiter-scene {
    position: fixed;
    top: 4.75rem;
    left: 50%;
    width: min(47.1875rem, 66vw);
    height: min(36.5rem, 48vw);
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
    width: min(100% - 11.25rem, 78.75rem);
    height: 100%;
    min-height: 0;
    margin: 0 auto;
}

.arbiter-topbar {
    position: absolute;
    z-index: 4;
    top: 1.5625rem;
    right: 0;
    left: 0;
    display: flex;
    justify-content: center;
    pointer-events: none;
}

.arbiter-topbar > * {
    pointer-events: auto;
}

.arbiter-mobile-panel-controls {
    display: none;
    align-items: center;
    gap: 0.5rem;
}

.arbiter-mobile-panel-button {
    width: 2.8125rem !important;
    height: 2.8125rem !important;
}

.arbiter-back {
    position: fixed !important;
    z-index: 5;
    top: 1.5625rem;
    left: 1.25rem;
    width: 2.8125rem !important;
    height: 2.8125rem !important;
}

.gameplay-paused-notice {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 1.5rem 0 1rem;
    border: 1px solid rgba(255, 190, 88, 0.38);
    border-radius: 1000rem;
    padding: 0.5rem;
    background: rgba(61, 36, 16, 0.62);
}

.gameplay-paused-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid rgba(255, 190, 88, 0.38);
    border-radius: 9999px;
    padding: 0.34rem 0.7rem;
    color: #ffbe58;
    background: rgba(255, 190, 88, 0.1);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.gameplay-paused-eyebrow::before {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 1000rem;
    background: currentColor;
    content: '';
}

.gameplay-paused-notice p {
    margin: 0;
    color: #d2c5af;
    font-size: 0.78rem;
    line-height: 1.35;
}

.arbiter-media-player {
    width: min(37.125rem, calc(100vw - 15rem));
    margin-top: 2.1875rem;
}

.arbiter-media-player :deep(.player-bar) {
    width: 100%;
}

.arbiter-grid {
    display: grid;
    height: 100%;
    min-height: 0;
    grid-template-columns: 17.9375rem 38.5rem 17.9375rem;
    align-items: start;
    column-gap: 2.1875rem;
}

.arbiter-rail {
    position: sticky;
    top: 0;
    z-index: 3;
    display: flex;
    max-height: 100svh;
    min-width: 0;
    flex-direction: column;
    align-self: start;
    box-sizing: border-box;
    padding-top: 1.5625rem;
    padding-bottom: 0.75rem;
}

.rail-tabs {
    display: flex;
    min-height: 2.8125rem;
    align-items: center;
    gap: 0.625rem;
    margin-bottom: 1.25rem;
}

.rail-tabs--right {
    justify-content: flex-end;
}

.rail-icon-button {
    width: 2.8125rem !important;
    height: 2.8125rem !important;
    flex: 0 0 auto;
}

.rail-tab {
    width: auto !important;
    height: 2.8125rem !important;
    flex: 0 0 auto;
    border-radius: 9999px !important;
    padding: 0 1rem 0 0.45rem !important;
    color: #e4e4e4;
    font-size: 1rem;
    font-weight: 500;
}

.rail-tab.gameplay-header-glass--active {
    color: #ffbe58;
}

.rail-tab-content {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}

.arbiter-panel {
    border: 1px solid rgba(255, 255, 255, 0.035);
    border-radius: 0.875rem;
    padding: 1.25rem;
    background: rgba(15, 15, 15, 0.96);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
}

.arbiter-rail > .arbiter-panel {
    min-height: 0;
}

.arbiter-left-rail > .arbiter-panel {
    height: 35.875rem;
    max-height: calc(100svh - 6.375rem);
    overflow-y: auto;
    scrollbar-color: rgba(89, 193, 202, 0.35) transparent;
    scrollbar-width: thin;
}

.panel-title {
    display: flex;
    align-items: baseline;
    justify-content: flex-start;
    gap: 0.6rem;
    min-height: 2rem;
    margin-bottom: 0.625rem;
    color: #59c1ca;
    font-size: 1rem;
}

.panel-title small {
    color: #7e7e7e;
    font-size: 0.75rem;
}

.players-left-count {
    margin-left: auto;
    border: 1px solid rgba(255, 139, 122, 0.5);
    border-radius: 9999px;
    padding: 0.16rem 0.42rem;
    color: #ff9c8e;
    background: rgba(138, 44, 34, 0.24);
    font-size: 0.6rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.player-list {
    display: grid;
    gap: 0.875rem;
}

.player-card {
    display: grid;
    height: 5.4375rem;
    grid-template-columns: 2.5rem minmax(0, 1fr);
    gap: 0 0.375rem;
    border-radius: 0.5rem;
    padding: 0.5rem;
    background: #191919;
}

.player-card--active {
    box-shadow: inset 0 0 0 1px #ffbe58;
}

.player-card--up-next {
    box-shadow: inset 0 0 0 1px #58d9a1;
}

.player-card--left {
    position: relative;
    border: 1px dashed rgba(255, 139, 122, 0.72);
    background: linear-gradient(135deg, rgba(91, 32, 27, 0.72), #191919 72%);
}

.player-card--left::before {
    position: absolute;
    top: 0.5rem;
    bottom: 0.5rem;
    left: -1px;
    width: 0.2rem;
    border-radius: 9999px;
    background: #ff8b7a;
    box-shadow: 0 0 0.75rem rgba(255, 139, 122, 0.58);
    content: '';
}

.player-card--left .player-avatar {
    filter: grayscale(0.85);
    opacity: 0.7;
}

.player-avatar,
.contribution-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 9999px;
    object-fit: cover;
}

.player-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.8125rem;
}

.player-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.player-badges {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.3rem;
}

.player-host,
.player-state {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.player-badge-icon {
    display: inline-grid;
    width: 1rem;
    height: 1rem;
    place-items: center;
    border: 1px solid currentColor;
    border-radius: 9999px;
}

.player-badge-icon--you {
    color: #8eeaf0;
    background: rgba(89, 193, 202, 0.12);
}

.player-badge-icon--arbiter {
    color: #ffbe58;
    background: rgba(255, 190, 88, 0.12);
}

.player-badge-icon--arbiter :deep(svg) {
    fill: currentColor;
}

.player-host {
    color: #ffbe58;
    font-size: 0.625rem;
}

.player-left-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.22rem;
    border: 1px solid rgba(255, 139, 122, 0.55);
    border-radius: 9999px;
    padding: 0.1rem 0.34rem;
    color: #ff9c8e;
    background: rgba(138, 44, 34, 0.25);
    font-size: 0.56rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.player-left-badge::before {
    width: 0.28rem;
    height: 0.28rem;
    border-radius: 9999px;
    background: currentColor;
    content: '';
}

.player-state {
    margin-top: 0.25rem;
    font-size: 0.6875rem;
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

.player-state--left {
    color: #ff9c8e;
}

.player-state--typing {
    color: #8eeaf0;
}

.player-state--typing > i {
    display: inline-block;
    animation: typing-pulse 1.1s ease-in-out infinite;
}

.player-typing-label,
.typing-label--active {
    display: inline-flex;
    align-items: center;
    gap: 0.22rem;
}

.typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
}

.typing-dots > span {
    width: 0.22rem;
    height: 0.22rem;
    border-radius: 9999px;
    background: currentColor;
    animation: typing-dot-bounce 0.9s ease-in-out infinite;
}

.typing-dots > span:nth-child(2) {
    animation-delay: 0.12s;
}

.typing-dots > span:nth-child(3) {
    animation-delay: 0.24s;
}

@keyframes typing-pulse {
    50% {
        opacity: 0.4;
        transform: scale(0.75);
    }
}

@keyframes typing-dot-bounce {
    50% {
        opacity: 0.38;
        transform: translateY(-0.13rem);
    }
}

.player-score {
    grid-column: 1 / -1;
    margin-top: 0.5rem;
    border-top: 1px solid #2f2f2f;
    padding-top: 0.5rem;
    color: #8f8f8f;
    font-size: 0.75rem;
}

.invite-card {
    position: relative;
    display: flex;
    height: 7.0625rem;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 1.25rem;
    border: 1px dashed #5c5c5c;
    border-radius: 0.5rem;
    padding: 0.5rem 0.625rem 0.5rem 0.5rem;
}

.invite-label {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: #e4e4e4;
    min-height: 1.6875rem;
    padding-left: 0.3125rem;
    font-size: 1rem;
}

.room-code-card {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    overflow: hidden;
    border: 1px solid #555;
    border-radius: 0.5rem;
    min-height: 3.375rem;
    background: #191919;
}

.room-code-card > div {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    justify-content: center;
    padding: 0.375rem 0.5rem;
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
    width: 3.375rem;
    place-items: center;
    border: 0;
    border-left: 1px solid #373737;
    background: transparent;
}

.room-code-card button:hover {
    background: rgba(255, 255, 255, 0.06);
}

.room-code-card img {
    width: 2.61rem;
    height: 2.61rem;
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
    height: 2.5rem;
    margin-top: 1.875rem;
    border: 1px solid #fd6d6d;
    border-radius: 0.45rem;
    color: #fd6d6d;
    background: transparent;
    font-size: 0.8125rem;
}

.leave-room-button:hover {
    color: #fff;
    background: rgba(253, 109, 109, 0.14);
}

.leave-room-button:disabled {
    cursor: wait;
    opacity: 0.6;
}

.arbiter-story-column {
    align-self: stretch;
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 24.8125rem 1.25rem 10rem;
    scrollbar-color: rgba(89, 193, 202, 0.35) transparent;
    scrollbar-width: thin;
}

.story-heading {
    display: grid;
    gap: 1.25rem;
}

.story-heading h1 {
    width: min(100%, 25rem);
    margin: 0;
    margin-inline: auto;
    color: #fff;
    font-family: 'Inria Serif', serif;
    font-size: 2.5rem;
    font-weight: 700;
    line-height: normal;
    text-align: center;
    text-wrap: balance;
}

.story-history {
    display: grid;
    gap: 0.875rem;
    margin-top: 0;
}

.story-turn {
    scroll-margin-top: 7rem;
}

.story-history :deep(.narration-card) {
    color: #f0f0f0;
    font-size: 1rem;
    line-height: normal;
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

.decision-timer--urgent {
    border-color: #ffbe58;
    color: #ffbe58;
    animation: decision-timer-pulse 900ms ease-in-out infinite;
}

@keyframes decision-timer-pulse {
    0%,
    100% {
        box-shadow: 0 0 0 rgba(255, 190, 88, 0);
    }

    50% {
        box-shadow: 0 0 1rem rgba(255, 190, 88, 0.42);
    }
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
    box-shadow:
        0 0 0 1px rgba(89, 193, 202, 0.15),
        0 0 24px rgba(89, 193, 202, 0.1);
}

.proposal-choice-card--selected {
    border-color: #4bcbd3 !important;
    color: #fff;
    background: #61d9d5 !important;
    box-shadow:
        0 0 0 1px rgba(89, 193, 202, 0.38),
        0 0 28px rgba(89, 193, 202, 0.16);
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
    box-shadow:
        inset 0 0 0 4px #0f282b,
        inset 0 0 0 6px #59c1ca;
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
    transition:
        background 160ms ease,
        border-color 160ms ease,
        transform 160ms ease;
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

.contribution-card--away {
    border-style: dashed;
    border-color: rgba(255, 190, 88, 0.55);
    opacity: 0.72;
}

.typing-label {
    color: #d8d8d8;
    font-size: 0.9375rem;
}

.typing-label--active {
    color: #8eeaf0;
}

@media (prefers-reduced-motion: reduce) {
    .player-state--typing > i,
    .typing-dots > span {
        animation: none;
    }
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
    height: min(32.6875rem, calc(100svh - 6.375rem));
    min-height: 32.6875rem;
    flex-direction: column;
    padding: 1.25rem;
    overflow: hidden;
}

.chat-window {
    display: block;
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
    border: 1px solid #262626;
    border-radius: 0.5rem;
    padding: 0.875rem;
    background: #131313;
    scrollbar-color: rgba(89, 193, 202, 0.35) transparent;
    scrollbar-width: thin;
}

.chat-window-content {
    display: flex;
    min-height: 100%;
    flex-direction: column;
    justify-content: flex-end;
    gap: 0.625rem;
}

.chat-message {
    border-radius: 0.5rem;
    padding: 0.625rem;
    background: #232323;
}

.chat-message:nth-child(even) {
    background: #1a1a1a;
}

.chat-empty {
    margin: auto 0;
    color: #7e7e7e;
    font-size: 0.75rem;
    text-align: center;
}

.chat-message-meta {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
}

.chat-message strong {
    font-size: 0.8125rem;
    font-weight: 400;
}

.chat-message time {
    flex: 0 0 auto;
    color: #666;
    font-size: 0.625rem;
}

.chat-message p {
    margin: 0.625rem 0 0;
    color: #d4d4d4;
    font-size: 0.6875rem;
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
    height: 3.5rem;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.625rem;
    border: 1px solid #626262;
    border-radius: 0.5rem;
    padding: 0 0.625rem;
    background: #131313;
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
    width: 2rem;
    height: 2rem;
    place-items: center;
    border: 0;
    border-radius: 9999px;
    background: #777;
}

.chat-compose button:hover {
    background: #8f8f8f;
}

.chat-compose button:disabled {
    cursor: wait;
    opacity: 0.55;
}

.chat-compose img {
    width: 2rem;
    height: 2rem;
}

.chat-error {
    margin: 0.5rem 0 0;
    color: #fd8b8b;
    font-size: 0.6875rem;
}

.arbiter-mobile-panel {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.arbiter-mobile-chat-panel {
    height: min(31.75rem, calc(92svh - 7rem));
    min-height: 20rem;
}

.arbiter-mobile-chat-panel .chat-window {
    height: auto;
    flex: 1;
}

.arbiter-proposal-dock {
    width: 100%;
    max-width: 38.5rem;
    position: fixed;
    z-index: 10;
    left: 50%;
    bottom: 1.75rem;
    transform: translateX(-50%);
    display: flex;
    gap: 0.75rem;
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
    align-self: center;
    pointer-events: auto;
}

.arbiter-proposal-media-player {
    width: min(100%, 38.5rem);
    min-width: 0;
    align-self: center;
    pointer-events: auto;
}

.arbiter-proposal-media-player :deep(.player-bar) {
    width: 100%;
}

@media (max-width: 1360px) {
    .arbiter-shell {
        width: min(100% - 2.5rem, 78.75rem);
    }

    .arbiter-grid {
        grid-template-columns: 17.9375rem minmax(31rem, 1fr) 17.9375rem;
        column-gap: 1.5rem;
    }
}

@media (max-width: 1120px) {
    .arbiter-page {
        height: auto;
        min-height: 100svh;
        overflow: visible;
        overflow-x: clip;
        padding-bottom: 8.5rem;
    }

    .arbiter-shell {
        height: auto;
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
        left: 1.25rem;
    }

    .arbiter-grid {
        display: flex;
        height: auto;
        max-width: 40rem;
        flex-direction: column;
        gap: 2.5rem;
        margin: 0 auto;
    }

    .arbiter-story-column {
        order: 1;
        height: auto;
        overflow: visible;
        padding-top: 4.5rem;
        padding-bottom: 0;
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
    .gameplay-paused-notice {
        align-items: flex-start;
        flex-direction: column;
    }

    .arbiter-shell {
        width: min(100% - 2rem, 40rem);
    }

    .arbiter-topbar {
        min-height: 6rem;
        justify-content: flex-end;
    }

    .arbiter-back {
        left: 1.25rem;
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
        right: 0;
        bottom: 0;
        left: 0;
        width: auto;
        max-width: none;
        box-sizing: border-box;
        padding: 1rem;
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
        transform: none;
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

@media (max-width: 1120px) {
    .arbiter-topbar {
        display: grid;
        min-height: 6rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
        justify-content: initial;
        gap: 0.75rem;
    }

    .arbiter-mobile-panel-controls {
        display: flex;
        grid-column: 2;
        grid-row: 1;
    }

    .arbiter-media-player {
        width: min(31rem, 100%);
        grid-column: 1 / -1;
        grid-row: 2;
        margin-top: 0.75rem;
    }

    .arbiter-rail {
        display: none;
    }
}
</style>
