<script setup lang="ts">
import crownIcon from '@/assets/multiplayer/crown.svg';
import treasureIslandBackdrop from '@/assets/top-moods/Treasure island 16_9 untitled.png';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { MultiplayerLobbyCardInterface } from '@/types';
import { LucideChevronRight } from 'lucide-vue-next';

defineProps<{
    rooms: MultiplayerLobbyCardInterface[];
}>();

function roomCover(room: MultiplayerLobbyCardInterface): string {
    return resolveStoryCover(room.story.slug, room.story.cover) || treasureIslandBackdrop;
}

function playersLabel(room: MultiplayerLobbyCardInterface): string {
    return `${room.players_count} ${room.players_count === 1 ? 'Player' : 'Players'}`;
}

function roomMode(room: MultiplayerLobbyCardInterface): string {
    return room.mode === 'arbiter' ? 'Arbiter Mode' : 'Roulette Mode';
}
</script>

<template>
    <section class="relative z-10 pt-8 pb-12 md:pt-[3.75rem] md:pb-[3.75rem]">
        <div class="container">
            <div class="container-content">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold tracking-wide sm:text-xl">MULTIPLAYER ({{ rooms.length }})</h2>
                    <button type="button" class="flex items-center gap-1 text-sm text-[#59c1ca] transition-colors hover:text-[#8fe2e7]">
                        View All
                        <LucideChevronRight class="size-4" :stroke-width="1.5" />
                    </button>
                </div>

                <div v-if="rooms.length" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <article
                        v-for="room in rooms"
                        :key="room.id"
                        class="group relative rounded-lg border border-[#373737] bg-[#0f0f0f] p-1 transition-colors hover:border-[#59c1ca]/70"
                    >
                        <div class="relative overflow-hidden rounded-[5px] bg-[#191919]">
                            <img :src="roomCover(room)" :alt="room.story.title" class="h-[15rem] w-full object-cover sm:h-[17.625rem]" />
                            <div class="absolute inset-0 bg-linear-to-t from-black/50 via-transparent to-transparent" />
                            <div
                                class="absolute top-1 left-1 flex h-[22px] min-w-[70px] items-center justify-center rounded-[27px] bg-white/[0.04] px-2 text-[12px] font-semibold text-[#fdd24f] backdrop-blur-[3px]"
                            >
                                {{ playersLabel(room) }}
                            </div>
                            <div
                                v-if="room.is_host"
                                class="absolute top-2 right-2 flex size-[22px] items-center justify-center rounded-[27px] bg-white/[0.04] backdrop-blur-[3px]"
                            >
                                <img :src="crownIcon" alt="" class="size-4" />
                            </div>
                        </div>

                        <div class="flex flex-col items-start gap-0.5 pt-1">
                            <h3 class="w-full truncate text-[13px] leading-normal font-semibold text-white">{{ room.story.title }}</h3>
                            <p class="flex h-[14px] items-center gap-1.5 text-xs leading-normal text-[#7e7e7e]">
                                <span class="size-[5px] rounded-full bg-[#fdd24f]" />
                                {{ roomMode(room) }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="mt-1 flex h-7 w-full items-center justify-center rounded-[5px] border-[0.5px] border-[#ffbe58] text-[13px] text-white transition-colors hover:bg-[#ffbe58]/10"
                        >
                            Join Room
                        </button>
                    </article>
                </div>

                <div v-else class="mt-4 rounded-lg border border-dashed border-[#373737] bg-[#0f0f0f] px-6 py-10 text-center text-sm text-[#7e7e7e]">
                    No multiplayer rooms are waiting for players right now.
                </div>
            </div>
        </div>
    </section>
</template>
