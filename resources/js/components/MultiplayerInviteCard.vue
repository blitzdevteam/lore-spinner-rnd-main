<script setup lang="ts">
import copyIcon from '@/assets/multiplayer/copy.svg';
import starFourIcon from '@/assets/multiplayer/star-four.svg';
import { LucideCheck } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

const props = defineProps<{
    inviteUrl: string;
    roomCode: string;
}>();

const copiedField = ref<'link' | 'code' | null>(null);
let copiedResetTimer: ReturnType<typeof window.setTimeout> | null = null;

const displayInviteUrl = computed(() => props.inviteUrl.replace(/^https?:\/\//, ''));

async function copyValue(field: 'link' | 'code', value: string): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(value);
    copiedField.value = field;

    if (copiedResetTimer !== null) {
        window.clearTimeout(copiedResetTimer);
    }

    copiedResetTimer = window.setTimeout(() => {
        copiedField.value = null;
        copiedResetTimer = null;
    }, 1800);
}

onUnmounted(() => {
    if (copiedResetTimer !== null) {
        window.clearTimeout(copiedResetTimer);
    }
});
</script>

<template>
    <div
        class="flex w-full flex-col items-start justify-center gap-2.5 rounded-[5px] border-[0.5px] border-dashed border-[#5c5c5c] bg-[#191919] py-2 pr-2.5 pl-2"
    >
        <div class="flex h-[27px] w-full items-center gap-1 pl-[5px] pr-2.5">
            <img :src="starFourIcon" alt="" class="size-5 shrink-0" width="20" height="20" />
            <p class="text-sm leading-[33px] text-white">Invite Players</p>
        </div>
        <div
            class="flex w-full flex-col items-start justify-center gap-1 rounded-[5px] border-[0.5px] border-solid border-[#5c5c5c] bg-[#191919] py-2 pr-2.5 pl-2"
        >
            <div class="flex h-12 w-full flex-col items-start">
                <div class="flex h-[13px] w-full items-center pr-2.5">
                    <p class="text-xs leading-[33px] text-white">Room Link</p>
                </div>
                <div class="flex w-full items-center justify-between gap-1">
                    <p class="min-w-0 truncate text-xs text-[#59c1ca]">{{ displayInviteUrl }}</p>
                    <button
                        type="button"
                        class="flex size-[29px] shrink-0 items-center justify-center overflow-clip"
                        :aria-label="copiedField === 'link' ? 'Room link copied' : 'Copy room link'"
                        @click="copyValue('link', inviteUrl)"
                    >
                        <LucideCheck v-if="copiedField === 'link'" class="size-4 text-[#58d9a1]" :stroke-width="1.75" />
                        <img v-else :src="copyIcon" alt="" class="size-[29px]" width="29" height="29" />
                    </button>
                </div>
            </div>
            <div class="flex h-12 w-full flex-col items-start">
                <div class="flex h-[14px] w-full items-center pr-2.5">
                    <p class="text-xs leading-[33px] text-white">Join Code</p>
                </div>
                <div class="flex w-full items-center justify-between gap-1">
                    <p class="min-w-0 truncate text-xs text-[#59c1ca]">{{ roomCode }}</p>
                    <button
                        type="button"
                        class="flex size-[29px] shrink-0 items-center justify-center overflow-clip"
                        :aria-label="copiedField === 'code' ? 'Join code copied' : 'Copy join code'"
                        @click="copyValue('code', roomCode)"
                    >
                        <LucideCheck v-if="copiedField === 'code'" class="size-4 text-[#58d9a1]" :stroke-width="1.75" />
                        <img v-else :src="copyIcon" alt="" class="size-[29px]" width="29" height="29" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
