<script setup lang="ts">
import arrowRightIcon from '@/assets/multiplayer/arrow-right.svg';
import backChevronIcon from '@/assets/multiplayer/back-chevron.svg';
import caretDownIcon from '@/assets/multiplayer/caret-down.svg';
import copyIcon from '@/assets/multiplayer/copy.svg';
import crownIcon from '@/assets/multiplayer/crown.svg';
import dividerLineIcon from '@/assets/multiplayer/divider-line.svg';
import plusCircleIcon from '@/assets/multiplayer/plus-circle.svg';
import scalesIcon from '@/assets/multiplayer/scales.svg';
import sparkleIcon from '@/assets/multiplayer/sparkle.svg';
import starFourIcon from '@/assets/multiplayer/star-four.svg';
import treasureIslandBackdrop from '@/assets/top-moods/Treasure island 16_9 untitled.png';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { MultiplayerLobbyInterface } from '@/types';
import {
    destroy as destroyMultiplayerLobby,
    show as showMultiplayerLobby,
    start as startMultiplayerLobby,
    updateReadiness as updateMultiplayerLobbyReadiness,
    updateSettings as updateMultiplayerLobbySettings,
} from '@/wayfinder/actions/App/Http/Controllers/User/MultiplayerLobbyController';
import { multiplayer as multiplayerStoryRoute } from '@/wayfinder/routes/stories';
import { useEcho, useEchoPresence } from '@laravel/echo-vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { LucideCheck } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

defineOptions({ layout: null });

const props = defineProps<{
    lobby: MultiplayerLobbyInterface;
}>();

const lobbyHeartbeatInterval = 15_000;

type MultiplayerLobbyRealtimePayload = {
    lobby: Pick<MultiplayerLobbyInterface, 'id' | 'room_code' | 'mode' | 'status' | 'max_players' | 'settings' | 'members'>;
};

type MultiplayerLobbyDeletedPayload = {
    lobby_id: string;
};

type MultiplayerLobbyClosedPayload = {
    lobby_id: string;
    message: string;
};

type MultiplayerLobbyEventPayload = MultiplayerLobbyRealtimePayload | MultiplayerLobbyDeletedPayload | MultiplayerLobbyClosedPayload;

type LobbySettingKey = 'customInput' | 'voiceOver' | 'votingTime';

type LobbySettingOption = {
    key: LobbySettingKey;
    label: string;
    options: string[];
};

type LobbySettingsForm = Record<LobbySettingKey, string>;

const liveLobby = ref(props.lobby);
const lobbySettingsForm = reactive<LobbySettingsForm>({
    customInput: 'Off',
    voiceOver: 'On',
    votingTime: '20 Sec',
});

function syncLobbySettingsForm(): void {
    lobbySettingsForm.customInput = liveLobby.value.settings.custom_input_enabled ? 'On' : 'Off';
    lobbySettingsForm.voiceOver = (liveLobby.value.settings.voice_over_enabled ?? true) ? 'On' : 'Off';
    lobbySettingsForm.votingTime = `${liveLobby.value.settings.decision_window_seconds} Sec`;
}

watch(
    () => props.lobby,
    (lobby) => {
        liveLobby.value = lobby;
    },
);
watch(
    () => liveLobby.value.settings,
    () => {
        syncLobbySettingsForm();
    },
    { immediate: true },
);

const story = computed(() => liveLobby.value.story);
const storyCover = computed(() => resolveStoryCover(story.value.slug, story.value.cover) || treasureIslandBackdrop);
const roomCode = computed(() => liveLobby.value.room_code);
const inviteLink = computed(() => {
    if (liveLobby.value.invite_url) {
        return liveLobby.value.invite_url;
    }

    const path = multiplayerStoryRoute.url(story.value.slug, {
        query: { room: roomCode.value },
    });

    return typeof window === 'undefined' ? path : `${window.location.origin}${path}`;
});
const page = usePage();
const members = computed(() => liveLobby.value.members);
const waitingSlots = computed(() => Math.max(liveLobby.value.max_players - members.value.length, 0));
const currentUserId = computed(() => page.props.auth?.id ?? null);
const currentMember = computed(() => members.value.find((member) => member.user.id === currentUserId.value));
const isCurrentUserReady = computed(() => currentMember.value?.is_ready ?? false);
const readyMemberCount = computed(() => members.value.filter((member) => member.is_ready).length);
const isLobbyWaiting = computed(() => liveLobby.value.status === 'waiting');
const allMembersReady = computed(() => members.value.length >= 3 && members.value.every((member) => member.is_ready));
const canStartStory = computed(() => liveLobby.value.is_host && isLobbyWaiting.value && allMembersReady.value);
const canUpdateLobbySettings = computed(() => liveLobby.value.is_host && isLobbyWaiting.value);
const readinessMessage = computed(() => {
    if (!isLobbyWaiting.value) {
        return 'The story has started.';
    }

    if (members.value.length < 3) {
        const playersNeeded = 3 - members.value.length;

        return `Invite ${playersNeeded} more ${playersNeeded === 1 ? 'player' : 'players'} to begin.`;
    }

    return `${readyMemberCount.value}/${members.value.length} players ready`;
});
const copiedRoomCode = ref(false);
const leavingLobby = ref(false);
const redirectingAwayFromLobby = ref(false);
const updatingReadiness = ref(false);
const updatingLobbySettings = ref(false);
const startingStory = ref(false);
const readinessError = ref<string | null>(null);
const lobbySettingsError = ref<string | null>(null);
const startError = ref<string | null>(null);
let lobbyHeartbeatTimer: ReturnType<typeof window.setInterval> | null = null;
let isSendingLobbyHeartbeat = false;
const currentModeName = computed(() => (liveLobby.value.mode === 'arbiter' ? 'Arbiter' : 'Roulette'));
const playersCountLabel = computed(() => `${members.value.length}/${liveLobby.value.max_players}`);
const chaptersLabel = computed(() => `${story.value.chapters_count ?? 0} Chapters`);
const hostLabel = computed(() => {
    const fullName = liveLobby.value.host.full_name.trim();
    const firstName = fullName.split(/\s+/)[0] ?? fullName;
    const suffix = liveLobby.value.host.id === currentUserId.value ? '(you)' : '';

    return `${firstName}${suffix}`;
});
const displayedLobbySettings = computed<LobbySettingOption[]>(() => {
    if (liveLobby.value.mode === 'roulette') {
        return [
            { key: 'customInput', label: 'Custom Input', options: ['On', 'Off'] },
            { key: 'voiceOver', label: 'Voice Over', options: ['On', 'Off'] },
            { key: 'votingTime', label: 'Voting Time', options: ['10 Sec', '20 Sec', '30 Sec'] },
        ];
    }

    return [
        { key: 'voiceOver', label: 'Voice Over', options: ['On', 'Off'] },
        { key: 'votingTime', label: 'Voting Time', options: ['10 Sec', '20 Sec', '30 Sec'] },
    ];
});

const rules = computed(() => {
    if (liveLobby.value.mode === 'roulette') {
        return [
            {
                title: 'Xen presents the paths',
                description: 'Each turn, Xen generates the choices for the current player.',
            },
            {
                title: 'One player chooses',
                description: 'Only the current player chooses which path becomes canon.',
            },
            {
                title: 'The turn rotates',
                description: 'After the narration, the next player takes the next choice.',
            },
        ];
    }

    return [
        {
            title: 'The Arbiter decides',
            description: 'Everyone submits a choice. The current Arbiter selects the narration that becomes canon.',
        },
        {
            title: 'Everyone contributes',
            description: 'Choose a story point or write a custom action for each turn.',
        },
        {
            title: 'The Arbiter rotates',
            description: 'After a narration is chosen, the next player becomes the Arbiter.',
        },
    ];
});

useEchoPresence<MultiplayerLobbyEventPayload>(
    `multiplayer.lobby.${props.lobby.id}`,
    [
        '.multiplayer.lobby.created',
        '.multiplayer.lobby.member.joined',
        '.multiplayer.lobby.member.left',
        '.multiplayer.lobby.member.readiness.changed',
        '.multiplayer.lobby.settings.updated',
        '.multiplayer.lobby.started',
        '.multiplayer.lobby.deleted',
        '.multiplayer.lobby.closed',
    ],
    (payload) => {
        if ('message' in payload) {
            redirectAfterLobbyClosure(payload.lobby_id, payload.message);
            return;
        }

        if ('lobby_id' in payload) {
            redirectAfterLobbyDeletion(payload.lobby_id);
            return;
        }

        liveLobby.value = {
            ...liveLobby.value,
            ...payload.lobby,
        };

        if (payload.lobby.status === 'started') {
            router.visit(showMultiplayerLobby(payload.lobby.room_code).url, {
                preserveScroll: false,
            });
        }
    },
);

if (currentUserId.value !== null) {
    useEcho<MultiplayerLobbyDeletedPayload>(
        `App.Models.User.${currentUserId.value}`,
        '.multiplayer.lobby.deleted',
        (payload) => {
            redirectAfterLobbyDeletion(payload.lobby_id);
        },
    );

    useEcho<MultiplayerLobbyClosedPayload>(
        `App.Models.User.${currentUserId.value}`,
        '.multiplayer.lobby.closed',
        (payload) => {
            redirectAfterLobbyClosure(payload.lobby_id, payload.message);
        },
    );
}

function redirectAfterLobbyDeletion(lobbyId: string): void {
    if (lobbyId !== liveLobby.value.id || redirectingAwayFromLobby.value) {
        return;
    }

    redirectingAwayFromLobby.value = true;

    router.visit(multiplayerStoryRoute.url(story.value.slug), {
        replace: true,
    });
}

function redirectAfterLobbyClosure(lobbyId: string, message: string): void {
    if (lobbyId !== liveLobby.value.id || redirectingAwayFromLobby.value) {
        return;
    }

    redirectingAwayFromLobby.value = true;

    window.alert(message);

    router.visit(multiplayerStoryRoute.url(story.value.slug), {
        replace: true,
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

    if (isSendingLobbyHeartbeat || redirectingAwayFromLobby.value || !member || member.left_at !== null) {
        return;
    }

    isSendingLobbyHeartbeat = true;

    void fetch(`${showMultiplayerLobby(liveLobby.value.room_code).url}/heartbeat`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
    })
        .then((response) => {
            if (response.status === 410) {
                redirectAfterLobbyClosure(
                    liveLobby.value.id,
                    'This lobby has been closed. Rejoin with the room code to open it again.',
                );

                return;
            }

            if (response.status === 409 && !redirectingAwayFromLobby.value) {
                router.visit(showMultiplayerLobby(liveLobby.value.room_code).url, {
                    preserveScroll: true,
                });
            }
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

function goBack(): void {
    window.history.back();
}

async function copyInviteLink(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(inviteLink.value);
    copiedRoomCode.value = true;

    window.setTimeout(() => {
        copiedRoomCode.value = false;
    }, 1800);
}

function leaveLobby(): void {
    if (leavingLobby.value) return;

    const confirmationMessage = liveLobby.value.is_host
        ? 'Leave this lobby? The room will stay available for players to rejoin.'
        : 'Leave this lobby? You can rejoin while it is still accepting players.';

    if (!window.confirm(confirmationMessage)) return;

    leavingLobby.value = true;

    router.delete(destroyMultiplayerLobby(liveLobby.value.room_code).url, {
        preserveScroll: true,
        onFinish: () => {
            leavingLobby.value = false;
        },
    });
}

function toggleReadiness(): void {
    if (!currentMember.value || !isLobbyWaiting.value || updatingReadiness.value) {
        return;
    }

    readinessError.value = null;
    updatingReadiness.value = true;

    router.patch(
        updateMultiplayerLobbyReadiness(liveLobby.value.room_code).url,
        { is_ready: !isCurrentUserReady.value },
        {
            preserveScroll: true,
            onError: (errors) => {
                readinessError.value = errors.lobby ?? 'We could not update your ready status.';
            },
            onFinish: () => {
                updatingReadiness.value = false;
            },
        },
    );
}

function updateLobbySettings(): void {
    if (!canUpdateLobbySettings.value || updatingLobbySettings.value) {
        syncLobbySettingsForm();

        return;
    }

    lobbySettingsError.value = null;
    updatingLobbySettings.value = true;

    const settings = liveLobby.value.mode === 'roulette'
        ? {
            custom_input_enabled: lobbySettingsForm.customInput === 'On',
            voice_over_enabled: lobbySettingsForm.voiceOver === 'On',
            decision_window_seconds: Number.parseInt(lobbySettingsForm.votingTime, 10),
        }
        : {
            voice_over_enabled: lobbySettingsForm.voiceOver === 'On',
            decision_window_seconds: Number.parseInt(lobbySettingsForm.votingTime, 10),
        };

    router.patch(
        updateMultiplayerLobbySettings(liveLobby.value.room_code).url,
        { settings },
        {
            preserveScroll: true,
            onError: (errors) => {
                lobbySettingsError.value = errors['settings.decision_window_seconds']
                    ?? errors['settings.custom_input_enabled']
                    ?? errors['settings.voice_over_enabled']
                    ?? errors.lobby
                    ?? 'We could not update the lobby settings.';
                syncLobbySettingsForm();
            },
            onFinish: () => {
                updatingLobbySettings.value = false;
            },
        },
    );
}

function startStory(): void {
    if (!canStartStory.value || startingStory.value) {
        return;
    }

    startError.value = null;
    startingStory.value = true;

    router.post(
        startMultiplayerLobby(liveLobby.value.room_code).url,
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                startError.value = errors.lobby ?? 'We could not start the story.';
            },
            onFinish: () => {
                startingStory.value = false;
            },
        },
    );
}

onMounted(() => {
    startLobbyHeartbeat();
});

onUnmounted(() => {
    stopLobbyHeartbeat();
});
</script>

<template>
    <main class="relative min-h-svh overflow-x-hidden bg-black font-['Inter',sans-serif] text-white">
        <Head :title="`${story.title} Multiplayer Lobby`" />

        <div class="pointer-events-none absolute inset-x-0 top-0 mx-auto h-[36.5rem] w-full max-w-[47.2rem] opacity-[0.57]">
            <img :src="storyCover" alt="" class="absolute inset-0 size-full object-cover object-center" />
            <div
                class="absolute inset-0"
                style="
                    background-image:
                        linear-gradient(90deg, rgb(0, 0, 0) 1.8%, rgba(0, 0, 0, 0) 33.6%),
                        linear-gradient(270deg, rgb(0, 0, 0) 0%, rgba(0, 0, 0, 0) 33.2%),
                        linear-gradient(180deg, rgb(0, 0, 0) 6.2%, rgba(0, 0, 0, 0) 46.9%),
                        linear-gradient(180deg, rgba(0, 0, 0, 0) 38%, rgb(0, 0, 0) 67.7%);
                "
            />
        </div>

        <button
            type="button"
            class="absolute top-5 left-5 z-10 flex size-[45px] items-center justify-center rounded-[27px] border border-white/10 bg-white/[0.04] shadow-[0_4px_80px_rgba(0,0,0,0.2)] backdrop-blur-[3px] transition-colors hover:border-[#59c1ca]/60 hover:bg-[#0e1f22]/60 focus-visible:ring-2 focus-visible:ring-[#59c1ca] sm:left-[7.5%]"
            aria-label="Go back"
            @click="goBack"
        >
            <img :src="backChevronIcon" alt="" class="h-6 w-[15px]" />
        </button>

        <section
            class="relative z-[1] mx-auto grid min-h-svh w-full grid-cols-1 gap-8 px-5 pt-[8.8rem] pb-10 lg:px-16 xl:grid-cols-[287px_minmax(0,1fr)_287px] xl:items-start xl:gap-10 xl:px-[6.5rem]"
        >
            <aside class="order-2 w-full rounded-[14px] bg-[#0f0f0f] p-5 xl:order-1 xl:w-[287px]">
                <div class="flex w-full flex-col items-start gap-[17px]">
                    <div class="flex w-full flex-col items-start">
                        <h2 class="flex h-8 items-center text-base leading-[33px] text-[#59c1ca]">Current Mode</h2>
                        <div class="flex w-full flex-col items-center justify-center rounded-lg bg-[#191919] p-1.5">
                            <div class="flex h-[59px] w-full items-center gap-2">
                                <div
                                    class="flex size-[50px] shrink-0 items-center justify-center overflow-clip rounded-full border-[0.4px] border-[#ffbe58]"
                                >
                                    <img :src="scalesIcon" alt="" class="size-[30px]" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm leading-[18px] text-[#ffbe58]">{{ currentModeName }}</p>
                                    <p class="text-xs leading-4 font-light tracking-[0.5px] text-white">
                                        Everyone contributes, one player decides.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <img :src="dividerLineIcon" alt="" class="h-px w-full" />

                    <div
                        class="flex w-full flex-col items-start gap-3 rounded-[5px] border-[0.5px] border-dashed border-[#5c5c5c] py-2 pr-2.5 pl-2"
                    >
                        <div class="flex h-[27px] items-center gap-1 pl-[5px] pr-2.5">
                            <img :src="starFourIcon" alt="" class="size-5" />
                            <p class="text-base leading-[33px] text-white">Invite Players</p>
                        </div>
                        <div
                            class="flex w-full flex-col items-start justify-center rounded-[5px] border-[0.5px] border-solid border-[#5c5c5c] bg-[#191919] py-2 pr-2.5 pl-2"
                        >
                            <p class="text-sm leading-[33px] text-white">Room Link</p>
                            <div class="flex w-full items-center justify-between">
                                <p class="text-[13px] text-[#59c1ca]">{{ roomCode }}</p>
                                <button
                                    type="button"
                                    class="flex size-[29px] shrink-0 items-center justify-center overflow-clip"
                                    :aria-label="copiedRoomCode ? 'Invite link copied' : 'Copy invite link'"
                                    @click="copyInviteLink"
                                >
                                    <LucideCheck v-if="copiedRoomCode" class="size-4 text-[#58d9a1]" :stroke-width="1.75" />
                                    <img v-else :src="copyIcon" alt="" class="size-[29px]" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <img :src="dividerLineIcon" alt="" class="h-px w-full" />

                    <div class="flex w-full flex-col items-start gap-2.5">
                        <h2 class="flex h-8 w-full items-center text-base leading-[33px] text-[#59c1ca]">Room Settings</h2>
                        <div class="flex w-full flex-col items-start gap-3.5 px-3.5">
                            <div class="grid w-full grid-cols-[minmax(0,1fr)_100px] items-center gap-x-2 gap-y-3">
                                <p class="text-[13px] leading-4 font-light tracking-[0.5px] text-[#838383]">Players</p>
                                <p class="text-right text-sm leading-[33px] text-white">{{ playersCountLabel }}</p>
                                <p class="text-[13px] leading-4 font-light tracking-[0.5px] text-[#838383]">Host</p>
                                <p class="truncate text-right text-sm leading-[33px] text-white">{{ hostLabel }}</p>
                                <template v-for="setting in displayedLobbySettings" :key="setting.label">
                                    <p class="text-[13px] leading-4 font-light tracking-[0.5px] text-[#838383]">{{ setting.label }}</p>
                                    <div
                                        v-if="canUpdateLobbySettings"
                                        class="relative flex h-6 w-[100px] shrink-0 items-center"
                                    >
                                        <select
                                            v-model="lobbySettingsForm[setting.key]"
                                            class="h-full w-full appearance-none rounded-[5px] border-[0.5px] border-[#303030] bg-[#1b1b1b] px-1 pr-6 text-center text-sm leading-none text-white outline-none focus:border-[#59c1ca] disabled:cursor-wait disabled:opacity-60"
                                            :aria-label="`Set ${setting.label}`"
                                            :disabled="updatingLobbySettings"
                                            @change="updateLobbySettings"
                                        >
                                            <option v-for="option in setting.options" :key="option" :value="option">{{ option }}</option>
                                        </select>
                                        <img :src="caretDownIcon" alt="" class="pointer-events-none absolute right-0.5 size-[18px] shrink-0" />
                                    </div>
                                    <div
                                        v-else
                                        class="flex h-6 w-[100px] shrink-0 items-center justify-between rounded-[5px] border-[0.5px] border-[#303030] bg-[#1b1b1b] px-0.5"
                                    >
                                        <span class="w-full text-center text-sm leading-none text-white">{{ lobbySettingsForm[setting.key] }}</span>
                                    </div>
                                </template>
                                <p v-if="canUpdateLobbySettings" class="col-span-2 text-[11px] leading-4 text-[#838383]">
                                    {{ updatingLobbySettings ? 'Saving lobby settings...' : 'Changes save immediately.' }}
                                </p>
                                <p v-if="lobbySettingsError" class="col-span-2 text-xs leading-4 text-[#fd6d6d]">{{ lobbySettingsError }}</p>
                            </div>
                            <button
                                type="button"
                                class="flex h-8 w-full items-center justify-center rounded-[5px] border-[0.5px] border-[#fd6d6d] text-[13px] text-[#fd6d6d] disabled:cursor-wait disabled:opacity-60"
                                :disabled="leavingLobby"
                                @click="leaveLobby"
                            >
                                {{ leavingLobby ? 'Leaving Lobby...' : 'Leave Lobby' }}
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="order-1 flex flex-col items-center gap-8 text-center xl:order-2">
                <div class="flex flex-col items-center gap-2.5">
                    <p class="text-sm font-medium text-[#ffbe58] sm:text-lg">MULTIPLAYER LOBBY</p>
                    <h1 class="font-['Inria_Serif',serif] text-[2rem] leading-tight sm:text-4xl">The adventure is about to begin</h1>
                    <p class="text-xs text-[#8f8f8f] sm:text-sm">Wait for your friends to join and get ready to make choices together</p>
                </div>

                <div class="grid w-full grid-cols-2 gap-2.5 sm:grid-cols-4">
                    <article
                        v-for="member in members"
                        :key="member.id"
                        class="relative flex h-[13.7rem] flex-col items-center justify-end overflow-hidden rounded-xl border-[0.5px] p-2.5"
                        :class="member.role === 'host' ? 'border-[#ffbe58]' : 'border-[#303030]'"
                    >
                        <img :src="member.user.avatar" :alt="member.user.full_name" class="absolute inset-0 size-full object-cover opacity-80" />
                        <div class="absolute inset-0 bg-linear-to-t from-black via-black/40 to-black/20" />
                        <div
                            class="absolute top-2 left-2 z-[1] inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px]"
                            :class="member.is_ready ? 'border-[#58d9a1]/50 bg-[#103126]/90 text-[#58d9a1]' : 'border-white/15 bg-black/50 text-[#c5c5c5]'"
                        >
                            <LucideCheck v-if="member.is_ready" class="size-3" :stroke-width="2" />
                            {{ member.is_ready ? 'Ready' : 'Not ready' }}
                        </div>
                        <div class="relative z-[1] flex flex-col items-center gap-1">
                            <div v-if="member.role === 'host'" class="flex items-center gap-1 text-[10px] text-[#ffbe58]">
                                <img :src="crownIcon" alt="" class="size-3.5" />Host
                            </div>
                            <p class="text-sm font-semibold text-[#59c1ca]">
                                {{ member.user.full_name }}<span v-if="member.user.id === currentUserId"> (You)</span>
                            </p>
                            <p class="text-xs text-white">{{ member.role === 'host' ? 'Lobby host' : 'Player' }}</p>
                        </div>
                    </article>
                    <div
                        v-for="slot in waitingSlots"
                        :key="slot"
                        class="flex h-[13.7rem] flex-col items-center justify-center gap-3 rounded-xl border-[0.5px] border-dashed border-[#5c5c5c] bg-[#191919] text-[#8f8f8f] hover:border-[#59c1ca]"
                    >
                        <img :src="plusCircleIcon" alt="" class="size-6" />
                        <span class="text-xs">Waiting for player</span>
                    </div>
                </div>

                <div v-if="isLobbyWaiting" class="flex w-full flex-col items-center gap-3">
                    <button
                        type="button"
                        class="flex h-10 items-center justify-center gap-2 rounded-[10px] border px-4 text-sm transition-colors disabled:cursor-wait disabled:opacity-60"
                        :class="isCurrentUserReady ? 'border-[#58d9a1]/60 bg-[#103126] text-[#58d9a1] hover:bg-[#164533]' : 'border-[#59c1ca]/70 bg-[#0e1f22] text-[#59c1ca] hover:bg-[#163f46]'"
                        :disabled="!currentMember || updatingReadiness"
                        @click="toggleReadiness"
                    >
                        <LucideCheck class="size-4" :stroke-width="1.75" />
                        {{ updatingReadiness ? 'Updating...' : isCurrentUserReady ? 'Ready — click to undo' : 'Ready to Play' }}
                    </button>
                    <button
                        v-if="liveLobby.is_host"
                        type="button"
                        class="flex h-[50px] items-center justify-center gap-2.5 rounded-[10px] border-[0.5px] px-4 text-base transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                        :class="canStartStory ? 'border-[#59c1ca] bg-[#0e1f22] text-white hover:bg-[#163f46]' : 'border-[#59c1ca] bg-[#0e1f22] text-white'"
                        :disabled="!canStartStory || startingStory"
                        @click="startStory"
                    >
                        <span class="flex size-9 items-center justify-center rounded-full border border-[#59c1ca] text-[10px] text-white"
                            ><span class="text-[#59c1ca]">{{ readyMemberCount }}/</span>{{ members.length }}</span
                        >
                        {{ startingStory ? 'Starting Story...' : 'Start Story' }}
                        <img :src="arrowRightIcon" alt="" class="size-[18px]" />
                    </button>
                    <p v-else class="text-xs tracking-wide text-[#838383]">
                        {{ allMembersReady ? 'Everyone is ready. Waiting for the host to start.' : 'Waiting for every player to get ready.' }}
                    </p>
                    <p class="text-xs tracking-wide text-[#838383]">{{ readinessMessage }}</p>
                    <p v-if="readinessError || startError" class="text-xs text-[#fd6d6d]">{{ readinessError || startError }}</p>
                    <p class="flex items-center gap-1.5 text-xs tracking-wide text-[#838383]">
                        <img :src="sparkleIcon" alt="" class="size-4" /><span
                            ><strong class="font-normal text-[#d8d8d8]">Tip</strong>: Share the room code to invite players.</span
                        >
                    </p>
                </div>
                <div v-else class="flex flex-col items-center gap-2 rounded-[10px] border border-[#58d9a1]/50 bg-[#103126]/60 px-5 py-4">
                    <div class="flex items-center gap-2 text-sm text-[#58d9a1]">
                        <LucideCheck class="size-4" :stroke-width="1.75" /> Story started
                    </div>
                    <p class="text-xs text-[#c5c5c5]">The lobby is locked while the group plays.</p>
                </div>
            </section>

            <aside class="order-3 w-full rounded-[14px] bg-[#0f0f0f] p-5 xl:w-[287px]">
                <div class="flex w-full flex-col items-start gap-[17px]">
                    <div class="flex w-full flex-col items-start">
                        <h2 class="flex h-8 items-center text-base leading-[33px] text-[#59c1ca]">Current Story</h2>
                        <div class="flex w-full flex-col items-center justify-center rounded-lg bg-[#191919] p-1.5">
                            <div class="flex h-[59px] w-full items-center gap-2">
                                <img :src="storyCover" alt="" class="size-[50px] shrink-0 rounded-[5px] object-cover" />
                                <div class="min-w-0 flex-1 text-left">
                                    <p class="truncate text-sm leading-[18px] text-white">{{ story.title }}</p>
                                    <p class="text-xs leading-4 font-light tracking-[0.5px] text-[#59c1ca]">{{ chaptersLabel }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <img :src="dividerLineIcon" alt="" class="h-px w-full" />

                    <div class="flex w-full flex-col items-start gap-2.5">
                        <h2 class="flex h-8 w-full items-center text-base leading-[33px] text-[#59c1ca]">Rules</h2>
                        <div class="flex w-full flex-col items-start gap-3.5 px-3.5 text-left">
                            <div v-for="rule in rules" :key="rule.title" class="flex w-full flex-col gap-1">
                                <p class="text-[13px] leading-4 text-white">{{ rule.title }}</p>
                                <p class="text-xs leading-4 font-light tracking-[0.5px] text-[#838383]">{{ rule.description }}</p>
                            </div>
                            <button
                                type="button"
                                class="flex h-8 w-full items-center justify-center rounded-[5px] border-[0.5px] border-[#5c5c5c] text-[13px] text-[#838383]"
                            >
                                View Rules
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </main>
</template>
