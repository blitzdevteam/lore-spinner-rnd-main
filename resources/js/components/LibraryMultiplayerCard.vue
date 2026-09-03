<script setup lang="ts">
import crownIcon from '@/assets/multiplayer/crown.svg';
import treasureIslandBackdrop from '@/assets/top-moods/Treasure island 16_9 untitled.png';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { MultiplayerLobbyCardInterface } from '@/types';
import { join as joinLobby } from '@/wayfinder/actions/App/Http/Controllers/User/MultiplayerLobbyController';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    room: MultiplayerLobbyCardInterface;
}>();

const joinForm = useForm<{ room_code: string }>({ room_code: '' });
const isJoining = ref(false);

const cover = computed(
    () => resolveStoryCover(props.room.story.slug, props.room.story.cover) || treasureIslandBackdrop,
);

const playersLabel = computed(() => {
    const count = props.room.players_count;

    return `${count} ${count === 1 ? 'Player' : 'Players'}`;
});

const roomMode = computed(() => (props.room.mode === 'arbiter' ? 'Arbiter Mode' : 'Roulette Mode'));

function joinRoom(): void {
    if (isJoining.value || joinForm.processing) {
        return;
    }

    isJoining.value = true;
    joinForm.room_code = props.room.room_code;
    joinForm.post(joinLobby.url(), {
        preserveScroll: true,
        onFinish: () => {
            isJoining.value = false;
        },
    });
}
</script>

<template>
    <article class="group relative rounded-lg border border-[#373737] bg-[#0f0f0f] p-1 transition-colors hover:border-[#59c1ca]/70">
        <div class="relative overflow-hidden rounded-[5px] bg-[#191919]">
            <img :src="cover" :alt="room.story.title" class="h-[15rem] w-full object-cover sm:h-[17.625rem]" />
            <div class="absolute inset-0 bg-linear-to-t from-black/50 via-transparent to-transparent" />
            <div
                class="absolute top-1 left-1 flex h-[22px] min-w-[70px] items-center justify-center rounded-[27px] bg-white/[0.04] px-2 text-[12px] font-semibold text-[#fdd24f] backdrop-blur-[3px]"
            >
                {{ playersLabel }}
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
                {{ roomMode }}
            </p>
        </div>

        <button
            type="button"
            class="mt-1 flex h-7 w-full items-center justify-center rounded-[5px] border-[0.5px] border-[#ffbe58] text-[13px] text-white transition-colors hover:bg-[#ffbe58]/10 disabled:cursor-wait disabled:opacity-60"
            :disabled="isJoining || joinForm.processing"
            @click="joinRoom"
        >
            {{ isJoining || joinForm.processing ? 'Joining...' : 'Join Room' }}
        </button>
    </article>
</template>
