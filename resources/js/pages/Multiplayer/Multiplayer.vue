<script setup lang="ts">
import arrowRightIcon from '@/assets/multiplayer/arrow-right.svg';
import backChevronIcon from '@/assets/multiplayer/back-chevron.svg';
import caretDownIcon from '@/assets/multiplayer/caret-down.svg';
import crownIcon from '@/assets/multiplayer/crown.svg';
import plusIcon from '@/assets/multiplayer/plus.svg';
import starFourIcon from '@/assets/multiplayer/star-four.svg';
import treasureIslandBackdrop from '@/assets/top-moods/Treasure island 16_9 untitled.png';
import OnOffToggle from '@/components/OnOffToggle.vue';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { StoryInterface } from '@/types';
import { join as joinMultiplayerLobby, store as storeMultiplayerLobby } from '@/wayfinder/actions/App/Http/Controllers/User/MultiplayerLobbyController';
import login from '@/wayfinder/routes/user/authentication/login';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { LucideCircleHelp, LucideGamepad2, LucideScale, LucideUsers, LucideVenetianMask } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: null });

const props = defineProps<{
    story: StoryInterface;
    inviteRoomCode?: string | null;
}>();

type LobbySettingKey = 'choices' | 'customInput' | 'voiceOver' | 'votingTime';

type LobbySettingOption = {
    key: LobbySettingKey;
    label: string;
    options: string[];
};

const showHostSetup = ref(false);
const showModeDetails = ref(false);
const selectedModeName = ref('Arbiter');
const lobbySettings = ref({
    choices: 'On',
    customInput: 'On',
    voiceOver: 'On',
    votingTime: '3 Min',
});
const lobbySettingOptions = computed<LobbySettingOption[]>(() => {
    const choicesSetting: LobbySettingOption = { key: 'choices', label: 'Choices', options: ['On', 'Off'] };
    const sharedSettings: LobbySettingOption[] = [
        { key: 'voiceOver', label: 'Voice Over', options: ['On', 'Off'] },
        { key: 'votingTime', label: 'Countdown', options: ['Off', '3 Min', '4 Min', '5 Min', '6 Min'] },
    ];

    if (selectedModeName.value === 'Roulette') {
        return [
            choicesSetting,
            ...(lobbySettings.value.choices === 'On'
                ? [{ key: 'customInput' as const, label: 'Custom Input', options: ['On', 'Off'] }]
                : []),
            ...sharedSettings,
        ];
    }

    return [choicesSetting, ...sharedSettings];
});
const lobbyCreationForm = useForm<{
    mode: 'arbiter' | 'roulette';
    max_players: number;
    settings: {
        decision_window_seconds: number;
        choices_enabled?: boolean;
        custom_input_enabled?: boolean;
        voice_over_enabled?: boolean;
    };
}>({
    mode: 'arbiter',
    max_players: 4,
    settings: {
        decision_window_seconds: 180,
    },
});
const joinLobbyForm = useForm<{
    room_code: string;
    email: string;
    first_name: string;
    last_name: string;
}>({
    room_code: props.inviteRoomCode ?? '',
    email: '',
    first_name: '',
    last_name: '',
});
const storyCover = computed(() => resolveStoryCover(props.story.slug, props.story.cover) || treasureIslandBackdrop);
const page = usePage();
const joiningPlayer = computed(() => page.props.auth);
const joiningPlayerName = computed(() => {
    const player = joiningPlayer.value;
    if (player === null) return '';

    return player.full_name?.trim() || [player.first_name, player.last_name].filter(Boolean).join(' ') || player.email;
});
const joiningPlayerUsername = computed(() => (joiningPlayer.value?.username ? `@${joiningPlayer.value.username}` : 'Guest player'));

type StoryMode = {
    name: string;
    description: string;
    headline: string;
    details: string;
    accent: string;
    selectedBackground: string;
    border: string;
    icon: typeof LucideScale;
    features: string[];
    available: boolean;
};

const storyModes: StoryMode[] = [
    {
        name: 'Arbiter',
        description: 'Everyone contributes. One player decides.',
        headline: 'Everyone contributes.\nOne player decides.',
        details: 'Take turns being the judge and choose the final action after everyone has had a chance to contribute.',
        accent: '#ffbe58',
        selectedBackground: '#221b0f',
        border: '#59c1ca',
        icon: LucideScale,
        features: ['2-4 Players', 'Turn Based', 'One judge at a time'],
        available: true,
    },
    {
        name: 'Roulette',
        description: 'one player decides, the story moves, and the turn changes.',
        headline: 'one player decides, the story moves, and the turn changes.',
        details: 'Roulette makes multiplayer immediately legible: one player decides, the story moves, and the turn changes.',
        accent: '#59c1ca',
        selectedBackground: '#102324',
        border: '#373737',
        icon: LucideVenetianMask,
        features: ['2-4 Players', 'Shared story', 'Each player a character'],
        available: true,
    },
    {
        name: 'Streamer',
        description: 'One story. Hundreds of voices.',
        headline: 'One story.\nHundreds of voices.',
        details: 'Turn a live broadcast into a shared story room: viewers propose the possibilities, and active players decide what becomes canon.',
        accent: '#cca0f3',
        selectedBackground: '#1a1420',
        border: '#373737',
        icon: LucideGamepad2,
        features: ['2-500 Players', 'Audience voting', 'Real-time story'],
        available: false,
    },
];

function goBack(): void {
    if (showModeDetails.value) {
        showModeDetails.value = false;
        return;
    }

    if (showHostSetup.value) {
        showHostSetup.value = false;
        return;
    }

    window.history.back();
}

function openModeDetails(): void {
    showModeDetails.value = true;
}

function createRoom(): void {
    if (page.props.auth === null) {
        router.visit(login.create().url);

        return;
    }

    selectedModeName.value = 'Arbiter';
    showModeDetails.value = false;
    showHostSetup.value = true;
}

function chooseMode(mode: StoryMode): void {
    if (!mode.available) {
        return;
    }

    selectedModeName.value = mode.name;
}

function isOnOffSetting(setting: LobbySettingOption): boolean {
    return setting.options.length === 2 && setting.options.every((option) => option === 'On' || option === 'Off');
}

function isModeSelected(mode: StoryMode): boolean {
    return mode.available && selectedModeName.value === mode.name;
}

function modeCardStyle(mode: StoryMode): Record<string, string> {
    if (!isModeSelected(mode)) {
        return {
            backgroundColor: '#000000',
            borderColor: '#373737',
        };
    }

    return {
        backgroundColor: mode.selectedBackground,
        borderColor: mode.accent,
    };
}

function modeActionStyle(mode: StoryMode): Record<string, string> {
    if (!mode.available) {
        return {
            backgroundColor: 'transparent',
            borderColor: '#8f8f8f',
            color: '#8f8f8f',
        };
    }

    if (isModeSelected(mode)) {
        return {
            backgroundColor: mode.accent,
            borderColor: '#000000',
            color: '#000000',
        };
    }

    return {
        backgroundColor: 'transparent',
        borderColor: mode.accent,
        color: '#ffffff',
    };
}

function decisionWindowSeconds(value: string): number {
    return value === 'Off' ? 0 : Number.parseInt(value, 10) * 60;
}

function createLobby(): void {
    const mode = selectedModeName.value === 'Roulette' ? 'roulette' : 'arbiter';

    lobbyCreationForm.mode = mode;
    lobbyCreationForm.max_players = 4;
    lobbyCreationForm.settings = {
        choices_enabled: lobbySettings.value.choices === 'On',
        voice_over_enabled: lobbySettings.value.voiceOver === 'On',
        decision_window_seconds: decisionWindowSeconds(lobbySettings.value.votingTime),
        ...(mode === 'roulette' ? { custom_input_enabled: lobbySettings.value.customInput === 'On' } : {}),
    };

    lobbyCreationForm.post(storeMultiplayerLobby(props.story.slug).url);
}

function joinRoom(): void {
    joinLobbyForm.room_code = joinLobbyForm.room_code.trim().toUpperCase();
    joinLobbyForm.post(joinMultiplayerLobby().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <main class="relative min-h-svh overflow-hidden bg-black font-['Inter',sans-serif] text-white">
        <Head :title="`${story.title} Multiplayer`" />

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
            v-if="!showHostSetup"
            class="relative z-[1] mx-auto flex min-h-svh w-full max-w-[43rem] flex-col items-center px-5 pt-[6.3rem] pb-10 sm:pt-[8.8rem]"
        >
            <div class="flex w-full flex-col items-center gap-2.5 text-center">
                <p class="text-sm font-medium tracking-tight text-[#ffbe58] sm:text-lg">MULTIPLAYER ADVENTURE</p>
                <div class="flex flex-col gap-2.5">
                    <h1 class="font-['Inria_Serif',serif] text-[2rem] leading-tight font-bold sm:text-4xl">How would you like to join?</h1>
                    <p class="text-xs text-[#8f8f8f] sm:text-sm">Create a new adventure as the host or join your friends using a room code.</p>
                </div>
            </div>

            <div
                class="mt-7 grid w-full grid-cols-1 items-stretch gap-4 sm:mt-8 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:gap-3.5"
            >
                <article
                    class="flex h-full min-h-[21.25rem] w-full flex-col justify-between gap-7 rounded-xl border-[0.5px] border-[#59c1ca] bg-black p-2.5"
                >
                    <div class="flex flex-col items-center gap-5 px-1.5 pt-1">
                        <div class="flex flex-col items-center gap-3.5 text-center">
                            <div class="flex size-[50px] items-center justify-center rounded-full border-[0.4px] border-[#0f0f0f]">
                                <img :src="crownIcon" alt="" class="size-[30px]" />
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <h2 class="text-lg">Be the host</h2>
                                <p class="text-xs text-[#59c1ca]">Create a new adventure and invite your friends.</p>
                            </div>
                        </div>

                        <ul class="flex w-full flex-col gap-1.5 text-xs leading-normal text-[#8f8f8f]">
                            <li class="flex gap-1.5"><span class="text-[#59c1ca]">•</span><span>You control the story settings.</span></li>
                            <li class="flex gap-1.5">
                                <span class="text-[#59c1ca]">•</span><span>You’ll create the room and get a room code.</span>
                            </li>
                            <li class="flex gap-1.5">
                                <span class="text-[#59c1ca]">!</span><span>The Host provides the Spinners needed to start this game.</span>
                            </li>
                        </ul>

                        <p class="flex w-full items-center gap-1.5 text-xs text-[#8f8f8f]">
                            <span class="text-[#ffbe58]">!</span>Spinner cost for this game: X
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex h-[50px] shrink-0 items-center justify-center gap-1.5 rounded-[10px] border-[0.5px] border-[#59c1ca] bg-[#0e1f22] text-base transition-colors hover:bg-[#163f46] focus-visible:ring-2 focus-visible:ring-[#59c1ca]"
                        @click="createRoom"
                    >
                        <img :src="plusIcon" alt="" class="size-6" />
                        Create Room
                    </button>
                </article>

                <div class="hidden h-full min-h-[21.25rem] flex-col items-center gap-2.5 sm:flex">
                    <span class="w-px flex-1 bg-white/20" />
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full border-[0.4px] border-[#8f8f8f] text-sm text-[#8f8f8f]"
                        >OR</span
                    >
                    <span class="w-px flex-1 bg-white/20" />
                </div>
                <div class="flex items-center justify-center sm:hidden">
                    <span class="rounded-full border-[0.4px] border-[#8f8f8f] px-3 py-1 text-xs text-[#8f8f8f]">OR</span>
                </div>

                <article
                    class="flex h-full min-h-[21.25rem] w-full flex-col justify-between gap-7 rounded-xl border-[0.5px] border-[#ffbe58] bg-black p-2.5"
                >
                    <div class="flex flex-col items-center gap-5 px-1.5 pt-1">
                        <div class="flex flex-col items-center gap-3.5 text-center">
                            <div class="flex size-[50px] items-center justify-center rounded-full border-[0.4px] border-[#fdd24f]">
                                <img :src="starFourIcon" alt="" class="size-[30px]" />
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <h2 class="text-lg">Join as a player</h2>
                                <p class="text-xs text-[#ffbe58]">Enter a room code to join your friends.</p>
                            </div>
                        </div>

                        <div class="flex w-full flex-col gap-1.5">
                            <label for="room-code" class="text-xs text-white">Enter room code</label>
                            <input
                                id="room-code"
                                v-model="joinLobbyForm.room_code"
                                type="text"
                                autocomplete="off"
                                class="h-[34px] w-full rounded-md border-[0.5px] border-[#8f8f8f] bg-[#101010] px-2 text-sm outline-none focus:border-[#ffbe58] focus:ring-1 focus:ring-[#ffbe58]"
                            />
                            <p class="flex items-start gap-1 text-[11px] leading-normal text-[#8f8f8f]">
                                <LucideCircleHelp class="mt-px size-3.5 shrink-0" :stroke-width="1.5" />Don’t have a code? Ask your host for the room
                                code.
                            </p>
                            <p v-if="joinLobbyForm.errors.room_code" class="text-[11px] leading-normal text-[#fd6d6d]" role="alert">
                                {{ joinLobbyForm.errors.room_code }}
                            </p>
                        </div>

                        <div
                            v-if="joiningPlayer"
                            class="w-full rounded-lg border border-[#ffbe58]/35 bg-[#191919] p-3"
                        >
                            <p class="text-[10px] font-medium tracking-[0.12em] text-[#8f8f8f] uppercase">Joining as</p>
                            <div class="mt-2 flex items-center gap-2.5">
                                <img :src="joiningPlayer.avatar" :alt="joiningPlayerName" class="size-9 rounded-full object-cover" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-white">{{ joiningPlayerName }}</p>
                                    <p class="truncate text-xs text-[#ffbe58]">{{ joiningPlayerUsername }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="w-full rounded-lg border border-[#ffbe58]/35 bg-[#191919] p-3">
                            <div class="flex flex-col gap-1">
                                <p class="text-xs font-medium text-white">Play as a guest</p>
                                <p class="text-[11px] leading-normal text-[#8f8f8f]">We’ll save a guest player for this room. You can complete your profile later.</p>
                            </div>
                            <div class="mt-3 flex flex-col gap-2">
                                <div>
                                    <label for="guest-email" class="text-[11px] text-white">Email</label>
                                    <input
                                        id="guest-email"
                                        v-model="joinLobbyForm.email"
                                        type="email"
                                        autocomplete="email"
                                        class="mt-1 h-[34px] w-full rounded-md border-[0.5px] border-[#5c5c5c] bg-[#101010] px-2 text-sm outline-none focus:border-[#ffbe58] focus:ring-1 focus:ring-[#ffbe58]"
                                    />
                                    <p v-if="joinLobbyForm.errors.email" class="mt-1 text-[11px] leading-normal text-[#fd6d6d]" role="alert">
                                        {{ joinLobbyForm.errors.email }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="guest-first-name" class="text-[11px] text-white">First name</label>
                                        <input
                                            id="guest-first-name"
                                            v-model="joinLobbyForm.first_name"
                                            type="text"
                                            autocomplete="given-name"
                                            class="mt-1 h-[34px] w-full rounded-md border-[0.5px] border-[#5c5c5c] bg-[#101010] px-2 text-sm outline-none focus:border-[#ffbe58] focus:ring-1 focus:ring-[#ffbe58]"
                                        />
                                        <p v-if="joinLobbyForm.errors.first_name" class="mt-1 text-[11px] leading-normal text-[#fd6d6d]" role="alert">
                                            {{ joinLobbyForm.errors.first_name }}
                                        </p>
                                    </div>
                                    <div>
                                        <label for="guest-last-name" class="text-[11px] text-white">Last name</label>
                                        <input
                                            id="guest-last-name"
                                            v-model="joinLobbyForm.last_name"
                                            type="text"
                                            autocomplete="family-name"
                                            class="mt-1 h-[34px] w-full rounded-md border-[0.5px] border-[#5c5c5c] bg-[#101010] px-2 text-sm outline-none focus:border-[#ffbe58] focus:ring-1 focus:ring-[#ffbe58]"
                                        />
                                        <p v-if="joinLobbyForm.errors.last_name" class="mt-1 text-[11px] leading-normal text-[#fd6d6d]" role="alert">
                                            {{ joinLobbyForm.errors.last_name }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex h-[50px] shrink-0 items-center justify-center gap-2.5 rounded-[10px] border-[0.5px] border-[#ffbe58] text-base transition-colors hover:bg-[#ffbe58]/10 focus-visible:ring-2 focus-visible:ring-[#ffbe58] disabled:cursor-wait disabled:opacity-60"
                        :disabled="joinLobbyForm.processing"
                        @click="joinRoom"
                    >
                        {{ joinLobbyForm.processing ? 'Joining Room...' : 'Join Room' }}
                        <img :src="arrowRightIcon" alt="" class="size-[18px]" />
                    </button>
                </article>
            </div>

            <div class="mt-8 hidden items-center gap-2 text-xs text-[#8f8f8f] sm:flex">
                <LucideUsers class="size-4" :stroke-width="1.5" />
                <span>Invite your friends to shape the story together.</span>
            </div>
        </section>

        <section
            v-else-if="showModeDetails"
            class="relative z-[1] mx-auto flex min-h-svh w-full max-w-[82rem] flex-col items-center px-5 pt-[6.3rem] pb-10 sm:pt-[8.8rem]"
        >
            <div class="flex w-full max-w-[36rem] flex-col items-center gap-6 text-center">
                <p class="text-sm font-medium tracking-tight text-[#ffbe58] sm:text-lg">MULTIPLAYER ADVENTURE</p>
                <div class="flex flex-col gap-2.5">
                    <h1 class="font-['Inria_Serif',serif] text-[2rem] leading-tight font-bold sm:text-4xl">Choose your story mode</h1>
                    <p class="text-xs text-[#8f8f8f] sm:text-sm">Different ways to play. One unforgettable story.</p>
                </div>
            </div>

            <div class="mt-6 grid w-full grid-cols-1 items-stretch gap-2.5 sm:grid-cols-3">
                <article
                    v-for="mode in storyModes"
                    :key="mode.name"
                    class="flex flex-col items-center gap-5 rounded-xl border-[0.5px] bg-black p-2.5 text-center"
                    :style="{ borderColor: isModeSelected(mode) ? mode.accent : '#373737' }"
                >
                    <div class="flex w-full flex-col items-center gap-5">
                        <div class="flex w-full flex-col items-center gap-5">
                            <span class="flex flex-col items-center gap-1.5">
                                <span
                                    class="flex size-[50px] items-center justify-center overflow-clip rounded-full border-[0.4px]"
                                    :style="{ borderColor: mode.accent, color: mode.accent }"
                                >
                                    <component :is="mode.icon" class="size-[30px]" :stroke-width="1.4" />
                                </span>
                                <span class="text-xl leading-none" :style="{ color: mode.accent }">{{ mode.name }}</span>
                            </span>
                            <span class="h-px w-full bg-white/20" />
                        </div>
                        <div class="flex w-full flex-col items-center gap-2.5">
                            <p class="min-h-[2.3rem] text-base leading-normal font-light whitespace-pre-line text-white">{{ mode.headline }}</p>
                            <p class="min-h-[3.375rem] text-xs leading-normal font-light text-[#8f8f8f]">{{ mode.details }}</p>
                        </div>
                    </div>
                    <div class="flex w-full items-stretch justify-center gap-1.5">
                        <template v-for="(feature, index) in mode.features" :key="feature">
                            <span v-if="index > 0" class="w-px shrink-0 self-stretch bg-white/20" />
                            <span class="flex min-w-0 flex-1 items-start gap-1.5 text-left text-xs leading-normal text-[#8f8f8f]">
                                <span class="mt-1.5 size-1 shrink-0 rounded-full" :style="{ backgroundColor: mode.accent }" />
                                <span>{{ feature }}</span>
                            </span>
                        </template>
                    </div>
                    <button
                        type="button"
                        class="flex h-10 w-full items-center justify-center rounded-[5px] border-[0.5px] text-sm font-medium focus-visible:ring-2 focus-visible:ring-[#59c1ca] focus-visible:outline-none disabled:cursor-not-allowed"
                        :style="modeActionStyle(mode)"
                        :disabled="!mode.available"
                        @click="chooseMode(mode)"
                    >
                        {{ mode.available ? 'Choose Mode' : 'Coming Soon' }}
                    </button>
                </article>
            </div>
        </section>

        <section
            v-else
            class="relative z-[1] mx-auto flex min-h-svh w-full max-w-[42rem] flex-col items-center px-5 pt-[6.3rem] pb-10 sm:pt-[8.8rem]"
        >
            <div class="flex w-full flex-col items-center gap-2.5 text-center">
                <p class="text-sm font-medium tracking-tight text-[#ffbe58] sm:text-lg">MULTIPLAYER LOBBY</p>
                <div class="flex flex-col gap-2.5">
                    <h1 class="font-['Inria_Serif',serif] text-[2rem] leading-tight font-bold sm:text-4xl">Configure your adventure</h1>
                    <p class="text-xs text-[#8f8f8f] sm:text-sm">You’re the host. Set up your game and invite your friends.</p>
                </div>
            </div>

            <div class="mt-10 flex w-full flex-col gap-5 rounded-[14px]">
                <div class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base">1.Choose your story mode</h2>
                        <button
                            type="button"
                            class="text-sm font-medium text-[#59c1ca] focus-visible:ring-2 focus-visible:ring-[#59c1ca] focus-visible:outline-none"
                            @click="openModeDetails"
                        >
                            View Details
                        </button>
                    </div>
                    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                        <button
                            v-for="mode in storyModes"
                            :key="mode.name"
                            type="button"
                            class="relative flex w-full flex-col items-center justify-end gap-2.5 rounded-xl border-[0.5px] p-2.5 text-center transition-colors focus-visible:ring-2 focus-visible:ring-[#59c1ca]"
                            :class="mode.available ? 'cursor-pointer' : 'cursor-not-allowed'"
                            :style="modeCardStyle(mode)"
                            :disabled="!mode.available"
                            :aria-pressed="mode.available ? isModeSelected(mode) : undefined"
                            :aria-disabled="!mode.available"
                            @click="chooseMode(mode)"
                        >
                            <span class="flex w-full flex-col items-center gap-1.5">
                                <span
                                    class="flex size-[50px] items-center justify-center overflow-clip rounded-full border-[0.4px]"
                                    :style="{ borderColor: mode.accent, color: mode.accent }"
                                >
                                    <component :is="mode.icon" class="size-[30px]" :stroke-width="1.4" />
                                </span>
                                <span class="text-xl leading-none" :style="{ color: mode.accent }">{{ mode.name }}</span>
                            </span>
                            <span
                                class="min-h-[3.375rem] text-xs leading-normal font-light"
                                :class="isModeSelected(mode) ? 'text-white' : 'text-[#8f8f8f]'"
                            >
                                {{ mode.description }}
                            </span>
                            <span
                                class="flex h-10 w-full items-center justify-center rounded-[5px] border-[0.5px] text-sm font-medium"
                                :style="modeActionStyle(mode)"
                            >
                                {{ mode.available ? 'Choose Mode' : 'Coming Soon' }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="h-px w-full bg-white/20" />

                <div class="flex flex-col gap-2.5">
                    <h2 class="text-base">2. Lobby Settings</h2>
                    <div class="grid gap-3.5 sm:grid-cols-2">
                        <div
                            v-for="setting in lobbySettingOptions"
                            :key="setting.key"
                            class="flex h-[50px] items-center justify-between gap-2 rounded-lg bg-[#191919] px-2 py-1.5"
                        >
                            <span
                                class="text-sm tracking-[0.5px]"
                                :class="isOnOffSetting(setting) ? 'text-[#59c1ca]' : 'text-[#838383]'"
                            >
                                {{ setting.label }}
                            </span>
                            <OnOffToggle
                                v-if="isOnOffSetting(setting)"
                                v-model="lobbySettings[setting.key]"
                                :label="setting.label"
                            />
                            <span v-else class="relative flex h-[38px] w-[8rem] items-center">
                                <select
                                    v-model="lobbySettings[setting.key]"
                                    class="h-full w-full appearance-none rounded-[5px] border-[0.5px] border-[#303030] bg-[#1b1b1b] px-3 pr-8 text-center text-sm text-white outline-none focus:border-[#59c1ca]"
                                    :aria-label="setting.label"
                                >
                                    <option v-for="option in setting.options" :key="option" :value="option">{{ option }}</option>
                                </select>
                                <img :src="caretDownIcon" alt="" class="pointer-events-none absolute right-2 size-[18px]" />
                            </span>
                        </div>
                    </div>
                </div>

            </div>

            <button
                type="button"
                class="mt-10 flex h-[50px] w-[218px] items-center justify-center gap-2.5 rounded-[10px] border-[0.5px] border-black bg-[#59c1ca] text-base font-medium text-black transition-colors hover:bg-[#7bd5dc] focus-visible:ring-2 focus-visible:ring-[#59c1ca] disabled:cursor-wait disabled:opacity-60"
                :disabled="lobbyCreationForm.processing"
                @click="createLobby"
            >
                {{ lobbyCreationForm.processing ? 'Opening Lobby...' : 'Multiplayer Lobby' }}
                <img :src="arrowRightIcon" alt="" class="size-[18px] invert" />
            </button>
            <p
                v-if="lobbyCreationForm.errors.mode || lobbyCreationForm.errors.max_players || lobbyCreationForm.errors.settings"
                class="mt-3 text-center text-sm text-[#fd6d6d]"
                role="alert"
            >
                {{ lobbyCreationForm.errors.mode || lobbyCreationForm.errors.max_players || lobbyCreationForm.errors.settings }}
            </p>
        </section>
    </main>
</template>
