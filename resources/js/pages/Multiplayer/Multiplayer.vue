<script setup lang="ts">
import backChevronIcon from '@/assets/multiplayer/back-chevron.svg';
import crownIcon from '@/assets/multiplayer/crown.svg';
import plusIcon from '@/assets/multiplayer/plus.svg';
import starFourIcon from '@/assets/multiplayer/star-four.svg';
import treasureIslandBackdrop from '@/assets/top-moods/Treasure island 16_9 untitled.png';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { StoryInterface } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    LucideArrowRight,
    LucideCheck,
    LucideChevronDown,
    LucideCircleHelp,
    LucideCopy,
    LucideGamepad2,
    LucideHandshake,
    LucideLink,
    LucideScale,
    LucideUsers,
    LucideVenetianMask,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: null });

const props = defineProps<{
    story: StoryInterface;
}>();

const roomCode = ref('');
const showModeSelection = ref(false);
const showSettings = ref(false);
const selectedModeName = ref('Judge');
const copiedRoomCode = ref(false);
const lobbySettings = ref({
    customInput: 'On',
    voiceOver: 'Off',
    powerUpsPerUser: '10',
    votingTime: '20 Sec',
});
const storyCover = computed(() => resolveStoryCover(props.story.slug, props.story.cover) || treasureIslandBackdrop);

type StoryMode = {
    name: string;
    description: string;
    details: string;
    accent: string;
    border: string;
    icon: typeof LucideScale;
    features: string[];
    available: boolean;
};

const storyModes: StoryMode[] = [
    {
        name: 'Judge',
        description: 'Everyone contributes. One player decides.',
        details: 'Take turns being the judge and choose the final action after everyone has had a chance to contribute.',
        accent: '#ffbe58',
        border: '#ffbe58',
        icon: LucideScale,
        features: ['2-4 Players', 'Turn Based', 'One judge at a time'],
        available: true,
    },
    {
        name: 'Role-playing',
        description: 'Everyone has a character. Everyone plays in their own part.',
        details: 'Make decisions for your own character while your actions become part of one shared story.',
        accent: '#59c1ca',
        border: '#373737',
        icon: LucideVenetianMask,
        features: ['2-4 Players', 'Shared story', 'Each player a character'],
        available: false,
    },
    {
        name: 'Parallel',
        description: 'One character. One team. One decision.',
        details: 'Work together, submit your choices, and let the group vote on what happens next.',
        accent: '#58d9a1',
        border: '#373737',
        icon: LucideHandshake,
        features: ['2-4 Players', 'Group voting', 'One shared character'],
        available: false,
    },
    {
        name: 'Streamer',
        description: 'One story. Hundreds of voices.',
        details: 'Let your audience vote on what happens next and experience the story together in real time.',
        accent: '#cca0f3',
        border: '#373737',
        icon: LucideGamepad2,
        features: ['2-4 Players', 'Audience voting', 'Real-time story'],
        available: false,
    },
];

function goBack(): void {
    if (showSettings.value) {
        showSettings.value = false;
        return;
    }

    if (showModeSelection.value) {
        showModeSelection.value = false;
        return;
    }

    window.history.back();
}

function createRoom(): void {
    showModeSelection.value = true;
}

function chooseMode(mode: StoryMode): void {
    if (!mode.available) return;

    selectedModeName.value = mode.name;
    showSettings.value = true;
}

async function copyRoomCode(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) return;

    await navigator.clipboard.writeText('TRS-7X2Q');
    copiedRoomCode.value = true;
    window.setTimeout(() => {
        copiedRoomCode.value = false;
    }, 1800);
}

async function inviteViaLink(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) return;

    await navigator.clipboard.writeText(window.location.href);
}

function openLobby(): void {
    router.visit(`/stories/${props.story.slug}/multiplayer/lobby`);
}

function joinRoom(): void {
    // Room joining is intentionally frontend-only until the multiplayer flow is connected.
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
            v-if="!showModeSelection"
            class="relative z-[1] mx-auto flex min-h-svh w-full max-w-[43rem] flex-col items-center px-5 pt-[6.3rem] pb-10 sm:pt-[8.8rem]"
        >
            <div class="flex w-full flex-col items-center gap-2.5 text-center">
                <p class="text-sm font-medium tracking-tight text-[#ffbe58] sm:text-lg">MULTIPLAYER ADVENTURE</p>
                <div class="flex flex-col gap-2.5">
                    <h1 class="font-['Inria_Serif',serif] text-[2rem] leading-tight font-bold sm:text-4xl">How would you like to join?</h1>
                    <p class="text-xs text-[#8f8f8f] sm:text-sm">Create a new adventure as the host or join your friends using a room code.</p>
                </div>
            </div>

            <div class="mt-7 flex w-full flex-col items-stretch gap-4 sm:mt-8 sm:flex-row sm:items-center sm:gap-3.5">
                <article class="flex min-h-[21.25rem] flex-1 flex-col rounded-xl border-[0.5px] border-[#59c1ca] bg-black p-2.5">
                    <div class="flex flex-1 flex-col items-center gap-5 px-1.5 pt-1">
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

                        <p class="mt-auto flex w-full items-center gap-1.5 text-xs text-[#8f8f8f]">
                            <span class="text-[#ffbe58]">!</span>Spinner cost for this game: X
                        </p>
                    </div>
                    <button
                        type="button"
                        class="mt-5 flex h-[50px] items-center justify-center gap-1.5 rounded-[10px] border-[0.5px] border-[#59c1ca] bg-[#0e1f22] text-base transition-colors hover:bg-[#163f46] focus-visible:ring-2 focus-visible:ring-[#59c1ca]"
                        @click="createRoom"
                    >
                        <img :src="plusIcon" alt="" class="size-6" />
                        Create Room
                    </button>
                </article>

                <div class="hidden shrink-0 flex-col items-center gap-2.5 sm:flex">
                    <span class="h-[7.5rem] w-px bg-white/20" />
                    <span class="flex size-10 items-center justify-center rounded-full border-[0.4px] border-[#8f8f8f] text-sm text-[#8f8f8f]"
                        >OR</span
                    >
                    <span class="h-[7.5rem] w-px bg-white/20" />
                </div>
                <div class="flex items-center justify-center sm:hidden">
                    <span class="rounded-full border-[0.4px] border-[#8f8f8f] px-3 py-1 text-xs text-[#8f8f8f]">OR</span>
                </div>

                <article class="flex min-h-[21.25rem] flex-1 flex-col rounded-xl border-[0.5px] border-[#ffbe58] bg-black p-2.5">
                    <div class="flex flex-1 flex-col items-center gap-5 px-1.5 pt-1">
                        <div class="flex flex-col items-center gap-3.5 text-center">
                            <div class="flex size-[50px] items-center justify-center rounded-full border-[0.4px] border-[#fdd24f]">
                                <img :src="starFourIcon" alt="" class="size-[30px]" />
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <h2 class="text-lg">Join as a player</h2>
                                <p class="text-xs text-[#ffbe58]">Enter a room code to join your friends.</p>
                            </div>
                        </div>

                        <div class="mt-5 flex w-full flex-col gap-1.5">
                            <label for="room-code" class="text-xs text-white">Enter room code</label>
                            <input
                                id="room-code"
                                v-model="roomCode"
                                type="text"
                                class="h-[34px] w-full rounded-md border-[0.5px] border-[#8f8f8f] bg-[#101010] px-2 text-sm outline-none focus:border-[#ffbe58] focus:ring-1 focus:ring-[#ffbe58]"
                            />
                            <p class="flex items-start gap-1 text-[11px] leading-normal text-[#8f8f8f]">
                                <LucideCircleHelp class="mt-px size-3.5 shrink-0" :stroke-width="1.5" />Don’t have a code? Ask your host for the room
                                code.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-5 flex h-[50px] items-center justify-center gap-2.5 rounded-[10px] border-[0.5px] border-[#ffbe58] text-base transition-colors hover:bg-[#ffbe58]/10 focus-visible:ring-2 focus-visible:ring-[#ffbe58]"
                        @click="joinRoom"
                    >
                        Join Room
                        <LucideArrowRight class="size-[18px]" :stroke-width="1.5" />
                    </button>
                </article>
            </div>

            <div class="mt-8 hidden items-center gap-2 text-xs text-[#8f8f8f] sm:flex">
                <LucideUsers class="size-4" :stroke-width="1.5" />
                <span>Invite your friends to shape the story together.</span>
            </div>
        </section>

        <section
            v-else-if="!showSettings"
            class="relative z-[1] mx-auto flex min-h-svh w-full max-w-[82rem] flex-col items-center px-5 pt-[6.3rem] pb-10 sm:pt-[8.8rem]"
        >
            <div class="flex w-full flex-col items-center gap-2.5 text-center">
                <p class="text-sm font-medium tracking-tight text-[#ffbe58] sm:text-lg">MULTIPLAYER ADVENTURE</p>
                <div class="flex flex-col gap-2.5">
                    <h1 class="font-['Inria_Serif',serif] text-[2rem] leading-tight font-bold sm:text-4xl">Choose your story mode</h1>
                    <p class="text-xs text-[#8f8f8f] sm:text-sm">Different ways to play. One unforgettable story.</p>
                </div>
            </div>

            <div class="mt-8 grid w-full grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="mode in storyModes"
                    :key="mode.name"
                    class="flex min-h-[16.5rem] flex-col rounded-xl border-[0.5px] bg-black p-2.5"
                    :style="{ borderColor: mode.border }"
                >
                    <div class="flex flex-1 flex-col items-center text-center">
                        <div class="flex flex-col items-center gap-1.5">
                            <div
                                class="flex size-[50px] items-center justify-center rounded-full border-[0.4px]"
                                :style="{ borderColor: mode.accent, color: mode.accent }"
                            >
                                <component :is="mode.icon" class="size-[30px]" :stroke-width="1.4" />
                            </div>
                            <h2 class="text-xl" :style="{ color: mode.accent }">{{ mode.name }}</h2>
                        </div>

                        <div class="my-3 h-px w-full bg-white/20" />

                        <p class="min-h-[2.5rem] text-sm leading-normal text-white">{{ mode.description }}</p>
                        <p class="mt-1 min-h-[3.5rem] text-xs leading-normal text-[#8f8f8f]">{{ mode.details }}</p>

                        <div class="mt-auto flex w-full justify-between gap-2 pt-4 text-left text-[11px] leading-tight text-[#8f8f8f]">
                            <span v-for="feature in mode.features" :key="feature" class="flex flex-1 items-start gap-1">
                                <span class="mt-1 size-1 shrink-0 rounded-full" :style="{ backgroundColor: mode.accent }" />
                                {{ feature }}
                            </span>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-4 flex h-10 w-full items-center justify-center rounded-[5px] border-[0.5px] text-sm transition-colors focus-visible:ring-2"
                        :class="mode.available ? 'hover:bg-white/[0.06]' : 'cursor-not-allowed text-[#8f8f8f]'"
                        :style="mode.available ? { borderColor: mode.accent, color: mode.accent } : { borderColor: '#8f8f8f' }"
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

            <div class="mt-8 flex w-full flex-col gap-6">
                <div class="flex flex-col gap-2.5">
                    <h2 class="text-base">1. Chosen Story Mode</h2>
                    <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                        <button
                            v-for="mode in storyModes"
                            :key="mode.name"
                            type="button"
                            class="relative flex min-h-[12.4rem] flex-col items-center justify-end rounded-xl border-[0.5px] p-2.5 text-center transition-colors focus-visible:ring-2 focus-visible:ring-[#59c1ca]"
                            :class="[
                                selectedModeName === mode.name ? 'bg-[#141819]' : 'bg-black',
                                mode.available ? 'cursor-pointer' : 'cursor-not-allowed opacity-80',
                            ]"
                            :style="{ borderColor: selectedModeName === mode.name ? '#59c1ca' : '#373737' }"
                            :disabled="!mode.available"
                            @click="chooseMode(mode)"
                        >
                            <span
                                v-if="selectedModeName === mode.name"
                                class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-[#59c1ca] text-black"
                            >
                                <LucideCheck class="size-4" :stroke-width="2.5" />
                            </span>
                            <span
                                class="flex size-[50px] items-center justify-center rounded-full border-[0.4px]"
                                :style="{ borderColor: mode.accent, color: mode.accent }"
                            >
                                <component :is="mode.icon" class="size-[30px]" :stroke-width="1.4" />
                            </span>
                            <span class="mt-1 text-xl" :style="{ color: mode.accent }">{{ mode.name }}</span>
                            <span class="mt-2 min-h-[3.75rem] text-xs leading-normal text-[#8f8f8f]">{{ mode.description }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-2.5">
                    <h2 class="text-base">2. Lobby Setting</h2>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label
                            v-for="setting in [
                                { key: 'customInput', label: 'Custom Input', options: ['On', 'Off'] },
                                { key: 'voiceOver', label: 'Voice Over', options: ['On', 'Off'] },
                                { key: 'powerUpsPerUser', label: 'Power-ups Per User', options: ['5', '10', '15'] },
                                { key: 'votingTime', label: 'Voting Time', options: ['10 Sec', '20 Sec', '30 Sec'] },
                            ]"
                            :key="setting.key"
                            class="flex h-[50px] items-center justify-between gap-2 rounded-lg bg-[#191919] px-2 py-1.5"
                        >
                            <span class="text-sm tracking-wide text-[#838383]">{{ setting.label }}</span>
                            <span class="relative flex h-[38px] w-[8rem] items-center">
                                <select
                                    v-model="lobbySettings[setting.key as keyof typeof lobbySettings]"
                                    class="h-full w-full appearance-none rounded-md border-[0.5px] border-[#303030] bg-[#1b1b1b] px-3 pr-8 text-center text-sm text-white outline-none focus:border-[#59c1ca]"
                                >
                                    <option v-for="option in setting.options" :key="option" :value="option">{{ option }}</option>
                                </select>
                                <LucideChevronDown class="pointer-events-none absolute right-2 size-4 text-[#838383]" :stroke-width="1.5" />
                            </span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-col gap-2.5">
                    <h2 class="text-base">3. Invite Your Friends</h2>
                    <div class="flex flex-col gap-2.5 sm:flex-row">
                        <div class="flex h-[50px] flex-1 items-center justify-between rounded-md border-[0.5px] border-[#5c5c5c] bg-[#191919] px-2">
                            <span class="text-base text-[#59c1ca]">TRS-7X2Q</span>
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-md bg-[#303030] text-white"
                                :aria-label="copiedRoomCode ? 'Room code copied' : 'Copy room code'"
                                @click="copyRoomCode"
                            >
                                <LucideCheck v-if="copiedRoomCode" class="size-5 text-[#58d9a1]" />
                                <LucideCopy v-else class="size-5" :stroke-width="1.5" />
                            </button>
                        </div>
                        <button
                            type="button"
                            class="flex h-[50px] flex-1 items-center justify-center gap-2.5 rounded-[10px] border-[0.5px] border-[#59c1ca] bg-[#0e1f22] text-base transition-colors hover:bg-[#163f46]"
                            @click="inviteViaLink"
                        >
                            <LucideLink class="size-6" :stroke-width="1.5" />
                            Invite Via Link
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="mx-auto flex h-[50px] items-center justify-center gap-2.5 rounded-[10px] border-[0.5px] border-black bg-[#59c1ca] px-5 text-base text-black transition-colors hover:bg-[#7bd5dc]"
                    @click="openLobby"
                >
                    Multiplayer Lobby
                    <LucideArrowRight class="size-[18px]" :stroke-width="1.5" />
                </button>
            </div>
        </section>
    </main>
</template>
