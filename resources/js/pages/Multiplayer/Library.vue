<script setup lang="ts">
import treasureIslandBackdrop from '@/assets/top-moods/Treasure island 16_9 untitled.png';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { MultiplayerLobbyInterface } from '@/types';
import { join as joinLobby } from '@/wayfinder/actions/App/Http/Controllers/User/MultiplayerLobbyController';
import { useEcho } from '@laravel/echo-vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { LucideArrowLeft, LucidePlus, LucideUsers } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: null });

const props = withDefaults(
    defineProps<{
        rooms?: MultiplayerLobbyInterface[];
    }>(),
    {
        rooms: () => [],
    },
);

type MultiplayerLobbyLibraryEventPayload = {
    lobby?: { id: string };
    lobby_id?: string;
};

const rooms = computed(() => props.rooms);

const joinForm = useForm<{ room_code: string }>({ room_code: '' });
const activeRoomCode = ref<string | null>(null);
const activeLibraryTab = ref<'all' | 'single' | 'multiplayer'>('multiplayer');

useEcho<MultiplayerLobbyLibraryEventPayload>(
    'multiplayer.lobbies',
    [
        '.multiplayer.lobby.created',
        '.multiplayer.lobby.member.joined',
        '.multiplayer.lobby.member.left',
        '.multiplayer.lobby.started',
        '.multiplayer.lobby.deleted',
    ],
    () => {
        router.reload({
            only: ['rooms'],
            preserveScroll: true,
        });
    },
);

function joinRoom(room: MultiplayerLobbyInterface): void {
    activeRoomCode.value = room.room_code;
    joinForm.room_code = room.room_code;
    joinForm.post(joinLobby.url(), {
        preserveScroll: true,
        onFinish: () => {
            activeRoomCode.value = null;
        },
    });
}

function switchLibraryTab(tab: 'all' | 'single' | 'multiplayer'): void {
    activeLibraryTab.value = tab;

    if (tab === 'all') {
        router.visit('/stories');
    } else if (tab === 'single') {
        router.visit('/stories');
    }
}

function roomCover(room: MultiplayerLobbyInterface): string {
    return resolveStoryCover(room.story.slug, room.story.cover) || treasureIslandBackdrop;
}

function roomMode(room: MultiplayerLobbyInterface): string {
    return room.mode === 'arbiter' ? 'Arbiter Mode' : room.mode;
}

function playersLabel(room: MultiplayerLobbyInterface): string {
    const playerCount = room.members.length;

    return playerCount + ' ' + (playerCount === 1 ? 'Player' : 'Players');
}
</script>

<template>
    <main class="min-h-svh bg-black font-['Inter',sans-serif] text-white">
        <Head title="Multiplayer Rooms" />

        <header class="mx-auto flex w-full max-w-[90rem] items-center justify-between px-5 pt-6 sm:px-8 lg:px-16">
            <button
                type="button"
                class="flex size-10 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-white transition-colors hover:border-[#59c1ca] hover:bg-[#0e1f22] focus-visible:ring-2 focus-visible:ring-[#59c1ca]"
                aria-label="Go back"
                @click="window.history.back()"
            >
                <LucideArrowLeft class="size-5" :stroke-width="1.5" />
            </button>
            <div class="flex items-center gap-2 text-xs text-[#838383]">
                <LucideUsers class="size-4 text-[#59c1ca]" :stroke-width="1.5" />
                Open rooms
            </div>
        </header>

        <section class="mx-auto w-full max-w-[90rem] px-5 pt-8 pb-12 sm:px-8 lg:px-16">
            <div class="mb-8 flex justify-center">
                <div class="flex items-center gap-2.5 rounded-[150px] bg-[#0f0f0f] p-[5px]">
                    <button
                        v-for="tab in [
                            { key: 'all', label: 'All Stories' },
                            { key: 'single', label: 'Single Player' },
                            { key: 'multiplayer', label: 'Multiplayer' },
                        ]"
                        :key="tab.key"
                        type="button"
                        class="flex h-[35px] w-[122px] items-center justify-center rounded-[150px] px-2.5 py-2 text-[13px] transition-colors"
                        :class="activeLibraryTab === tab.key ? 'bg-[#232323] text-[#59c1ca]' : 'text-[#7e7e7e] hover:text-white'"
                        @click="switchLibraryTab(tab.key as 'all' | 'single' | 'multiplayer')"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>
            <div class="flex items-center">
                <h1 class="text-lg font-semibold tracking-wide sm:text-xl">MULTIPLAYER ({{ rooms.length }})</h1>
            </div>

            <div v-if="rooms.length" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <article
                    v-for="room in rooms"
                    :key="room.id"
                    class="group rounded-lg border-[0.5px] border-[#373737] bg-[#0f0f0f] p-1 transition-colors hover:border-[#59c1ca]/70"
                >
                    <div class="relative aspect-[0.72] overflow-hidden rounded-md bg-[#191919]">
                        <img
                            :src="roomCover(room)"
                            :alt="room.story.title"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                        />
                        <div class="absolute inset-0 bg-linear-to-t from-black/80 via-transparent to-black/10" />
                        <div
                            class="absolute top-2 left-2 flex items-center gap-1 rounded-md bg-[#fdd24f]/90 px-2 py-1 text-[11px] font-medium text-black"
                        >
                            <LucideUsers class="size-3.5" :stroke-width="2" />
                            {{ playersLabel(room) }}
                        </div>
                        <span
                            class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full border border-[#fdd24f]/70 bg-black/50 text-[#fdd24f]"
                        >
                            <LucidePlus class="size-3.5" :stroke-width="1.5" />
                        </span>
                    </div>

                    <div class="px-1 pt-1">
                        <h2 class="truncate text-sm font-medium">{{ room.story.title }}</h2>
                        <p class="mt-1 flex items-center gap-1 text-xs text-[#7e7e7e]">
                            <span class="size-1 rounded-full bg-[#fdd24f]" />{{ roomMode(room) }}
                        </p>
                        <button
                            type="button"
                            class="mt-2 flex h-7 w-full items-center justify-center rounded border-[0.5px] border-[#fdd24f] text-xs text-white transition-colors hover:bg-[#fdd24f]/10 disabled:cursor-wait disabled:opacity-60"
                            :disabled="activeRoomCode === room.room_code || joinForm.processing"
                            @click="joinRoom(room)"
                        >
                            {{ activeRoomCode === room.room_code ? 'Joining...' : 'Join Room' }}
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="mt-4 rounded-lg border border-dashed border-[#373737] bg-[#0f0f0f] px-6 py-12 text-center">
                <h2 class="text-base font-medium">No open rooms right now</h2>
                <p class="mt-2 text-sm text-[#838383]">Create a multiplayer room from a playable story and invite your friends to join.</p>
            </div>
        </section>
    </main>
</template>
