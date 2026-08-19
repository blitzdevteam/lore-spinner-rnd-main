<script setup lang="ts">
import arrowRightIcon from '@/assets/multiplayer/arrow-right.svg';
import backChevronIcon from '@/assets/multiplayer/back-chevron.svg';
import checkCircleIcon from '@/assets/multiplayer/check-circle.svg';
import crownIcon from '@/assets/multiplayer/crown.svg';
import playerBilly from '@/assets/multiplayer/player-billy.jpg';
import plusCircleIcon from '@/assets/multiplayer/plus-circle.svg';
import sparkleIcon from '@/assets/multiplayer/sparkle.svg';
import treasureIslandBackdrop from '@/assets/top-moods/Treasure island 16_9 untitled.png';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { StoryInterface } from '@/types';
import { Head } from '@inertiajs/vue3';
import { LucideChevronDown, LucideMessageSquare, LucidePlus, LucideUsers } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: null });

const props = defineProps<{
    story: StoryInterface;
}>();

const storyCover = computed(() => resolveStoryCover(props.story.slug, props.story.cover) || treasureIslandBackdrop);
const roomCode = 'TRS-7X2Q';
const showSettings = ref(true);
const lobbySettings = ref({
    customInput: 'On',
    votingTime: '20 Sec',
    powerUpsPerUser: '10',
});

const rules = [
    {
        title: 'Votes are secret',
        description: 'Everyone votes at the same time. The majority moves the story forward.',
    },
    {
        title: 'Your choices matter',
        description: 'Every decision shapes the world and your characters.',
    },
    {
        title: 'One world, many paths',
        description: 'Different choices, one shared storyline.',
    },
];

function goBack(): void {
    window.history.back();
}

async function copyRoomCode(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) return;

    await navigator.clipboard.writeText(roomCode);
}

function startStory(): void {
    // Story start will connect to the multiplayer runtime when it is available.
}
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
            class="relative z-[1] mx-auto grid min-h-svh w-full grid-cols-1 gap-8 px-5 pt-[8.8rem] pb-10 lg:px-16 xl:grid-cols-[minmax(0,1fr)_minmax(0,3fr)_minmax(0,1fr)] xl:items-start xl:gap-10 xl:px-[6.5rem]"
        >
            <aside class="order-2 rounded-[14px] bg-[#0f0f0f] p-4 xl:order-1">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base text-[#59c1ca]">Current Mode</h2>
                    </div>
                    <div class="flex items-center gap-2 rounded-lg bg-[#191919] p-2">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full border border-[#ffbe58] text-[#ffbe58]">⚖</div>
                        <div class="min-w-0">
                            <p class="text-xs text-[#ffbe58]">Judge</p>
                            <p class="text-[10px] leading-tight text-[#8f8f8f]">Everyone contributes, one player decides.</p>
                        </div>
                    </div>
                    <div class="border-t border-white/10 pt-3">
                        <button type="button" class="mb-2 flex items-center gap-2 text-sm text-white">
                            <LucideUsers class="size-4" :stroke-width="1.5" /> Invite Players
                        </button>
                        <div class="flex items-center justify-between rounded-md border border-[#373737] bg-[#191919] p-2">
                            <div>
                                <p class="text-[11px] text-white">Room Code</p>
                                <p class="text-xs text-[#59c1ca]">{{ roomCode }}</p>
                            </div>
                            <button
                                type="button"
                                class="flex size-8 items-center justify-center rounded bg-[#303030]"
                                aria-label="Copy room code"
                                @click="copyRoomCode"
                            >
                                <LucidePlus class="size-4 rotate-45" :stroke-width="1.5" />
                            </button>
                        </div>
                    </div>
                    <div class="border-t border-white/10 pt-3">
                        <button type="button" class="mb-2 text-left text-base text-[#59c1ca]" @click="showSettings = !showSettings">
                            Room Settings
                        </button>
                        <div v-if="showSettings" class="flex flex-col gap-2">
                            <label
                                v-for="setting in [
                                    { key: 'customInput', label: 'Custom Input', options: ['On', 'Off'] },
                                    { key: 'votingTime', label: 'Voting Time', options: ['10 Sec', '20 Sec', '30 Sec'] },
                                    { key: 'powerUpsPerUser', label: 'Power-ups Per User', options: ['5', '10', '15'] },
                                ]"
                                :key="setting.key"
                                class="flex items-center justify-between gap-2 text-[11px] text-[#838383]"
                            >
                                <span>{{ setting.label }}</span>
                                <span class="relative w-[5.5rem]">
                                    <select
                                        v-model="lobbySettings[setting.key as keyof typeof lobbySettings]"
                                        class="w-full appearance-none rounded bg-[#1b1b1b] px-2 py-1 text-right text-xs text-white outline-none"
                                    >
                                        <option v-for="option in setting.options" :key="option" :value="option">{{ option }}</option>
                                    </select>
                                    <LucideChevronDown class="pointer-events-none absolute top-1 right-1 size-3 text-[#838383]" :stroke-width="1.5" />
                                </span>
                            </label>
                        </div>
                    </div>
                    <button type="button" class="h-8 rounded border border-[#fd6d6d] text-xs text-[#fd6d6d]">Leave Lobby</button>
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
                        class="relative flex h-[13.7rem] flex-col items-center justify-end overflow-hidden rounded-xl border-[0.5px] border-[#59c1ca] p-2.5"
                    >
                        <img :src="playerBilly" alt="" class="absolute inset-0 size-full object-cover opacity-80" />
                        <div class="absolute inset-0 bg-linear-to-t from-black via-black/40 to-black/20" />
                        <div
                            class="absolute top-2 left-2 flex items-center gap-1 rounded-full bg-[#58d9a1]/15 px-2 py-0.5 text-[10px] text-[#58d9a1]"
                        >
                            <img :src="checkCircleIcon" alt="" class="size-3.5" />Ready
                        </div>
                        <div class="relative z-[1] flex flex-col items-center gap-1">
                            <div class="flex items-center gap-1 text-[10px] text-[#ffbe58]"><img :src="crownIcon" alt="" class="size-3.5" />Host</div>
                            <p class="text-sm font-semibold text-[#59c1ca]">Billy (You)</p>
                            <p class="text-xs text-white">The Scholar</p>
                        </div>
                    </article>
                    <button
                        v-for="slot in 3"
                        :key="slot"
                        type="button"
                        class="flex h-[13.7rem] flex-col items-center justify-center gap-3 rounded-xl border-[0.5px] border-dashed border-[#5c5c5c] bg-[#191919] text-[#8f8f8f] hover:border-[#59c1ca]"
                    >
                        <img :src="plusCircleIcon" alt="" class="size-6" />
                        <span class="text-xs">Waiting for player</span>
                    </button>
                </div>

                <div class="flex flex-col items-center gap-3">
                    <button
                        type="button"
                        class="flex h-[50px] items-center justify-center gap-2.5 rounded-[10px] border-[0.5px] border-[#59c1ca] bg-[#0e1f22] px-4 text-base"
                        @click="startStory"
                    >
                        <span class="flex size-9 items-center justify-center rounded-full border border-[#59c1ca] text-[10px] text-white"
                            ><span class="text-[#59c1ca]">1/</span>3</span
                        >
                        Start Story
                        <img :src="arrowRightIcon" alt="" class="size-[18px]" />
                    </button>
                    <p class="flex items-center gap-1.5 text-xs tracking-wide text-[#838383]">
                        <img :src="sparkleIcon" alt="" class="size-4" /><span
                            ><strong class="font-normal text-[#d8d8d8]">Tip</strong>: All Players ready will start the story together.</span
                        >
                    </p>
                </div>
            </section>

            <aside class="order-3 rounded-[14px] bg-[#0f0f0f] p-4">
                <h2 class="mb-3 text-base text-[#59c1ca]">Current Story</h2>
                <div class="flex gap-2 rounded-lg bg-[#191919] p-1.5">
                    <img :src="storyCover" alt="" class="size-14 rounded-md object-cover" />
                    <div class="min-w-0 self-center text-left">
                        <p class="truncate text-xs text-white">{{ story.title }}</p>
                        <p class="text-[11px] text-[#59c1ca]">Episode 3 of 12</p>
                    </div>
                </div>
                <div class="my-4 border-t border-white/10" />
                <h2 class="mb-3 text-base text-[#59c1ca]">Rules</h2>
                <div class="flex flex-col gap-3 text-left">
                    <div v-for="rule in rules" :key="rule.title">
                        <p class="text-xs text-white">{{ rule.title }}</p>
                        <p class="mt-1 text-[11px] leading-tight text-[#838383]">{{ rule.description }}</p>
                    </div>
                </div>
                <button type="button" class="mt-4 h-8 w-full rounded border border-[#5c5c5c] text-xs text-[#838383]">View Rules</button>
            </aside>
        </section>

        <button
            type="button"
            class="fixed right-5 bottom-5 z-20 flex size-14 items-center justify-center rounded-full border border-[#373737] bg-[#191919] text-[#59c1ca] shadow-[0_4px_24px_rgba(0,0,0,0.35)] transition-colors hover:border-[#59c1ca] hover:bg-[#0e1f22] focus-visible:ring-2 focus-visible:ring-[#59c1ca]"
            aria-label="Open lobby chat"
        >
            <LucideMessageSquare class="size-6" :stroke-width="1.5" />
        </button>
    </main>
</template>
