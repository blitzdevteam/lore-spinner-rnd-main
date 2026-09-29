<script setup lang="ts">
import closeIcon from '@/assets/story-play/play-mode-close.svg';
import groupIcon from '@/assets/story-play/play-mode-group.svg';
import soloIcon from '@/assets/story-play/play-mode-solo.svg';
import { nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
    solo: [];
    group: [];
}>();

const closeButton = ref<HTMLButtonElement | null>(null);

function close(): void {
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    }
}

function setModalState(isOpen: boolean): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (isOpen) {
        document.body.classList.add('overflow-hidden');
        window.addEventListener('keydown', onKeydown);

        void nextTick(() => {
            closeButton.value?.focus({ preventScroll: true });
        });

        return;
    }

    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
}

watch(
    () => props.open,
    (isOpen) => {
        setModalState(isOpen);
    },
    { immediate: true },
);

onUnmounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="play-mode">
            <div
                v-if="open"
                class="fixed inset-0 z-[350] flex items-center justify-center bg-black/65 p-4 backdrop-blur-[6px]"
                role="dialog"
                aria-modal="true"
                aria-labelledby="play-mode-title"
                @click="close"
            >
                <div class="flex w-full max-w-[438px] flex-col items-center gap-5" @click.stop>
                    <button
                        ref="closeButton"
                        type="button"
                        class="flex size-[30px] items-center justify-center rounded-full"
                        aria-label="Close"
                        @click="close"
                    >
                        <img :src="closeIcon" alt="" width="18" height="18" />
                    </button>

                    <div class="w-full rounded-[30px] bg-[#0d0d0d] p-[15px]">
                        <div class="flex flex-col items-center gap-[30px]">
                            <p
                                id="play-mode-title"
                                class="text-center font-['Inter',sans-serif] text-[14px] leading-none font-normal text-white"
                            >
                                How would you like to play?
                            </p>

                            <div class="flex w-full gap-[10px]">
                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 flex-col items-center justify-end rounded-[12px] border-[0.5px] border-[#373737] bg-[#0f0f0f] p-[10px] text-center"
                                    @click="emit('solo')"
                                >
                                    <span class="flex size-9 items-center justify-center rounded-full border border-[#59c1ca]">
                                        <img :src="soloIcon" alt="" width="22" height="22" />
                                    </span>
                                    <span class="mt-0 flex h-[29px] w-full items-center justify-center font-['Inter',sans-serif] text-[16px] leading-none font-normal text-[#59c1ca]">
                                        Solo Spin
                                    </span>
                                    <span class="font-['Inter',sans-serif] text-[12px] leading-none font-light text-[#8f8f8f]">
                                        Play on your own
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 flex-col items-center justify-end rounded-[12px] border-[0.5px] border-[#373737] bg-[#0f0f0f] p-[10px] text-center"
                                    @click="emit('group')"
                                >
                                    <span class="flex size-9 items-center justify-center rounded-full border border-[#ffbe58]">
                                        <img :src="groupIcon" alt="" width="22" height="22" />
                                    </span>
                                    <span class="mt-0 flex h-[29px] w-full items-center justify-center font-['Inter',sans-serif] text-[16px] leading-none font-normal text-[#ffbe58]">
                                        Group Spin
                                    </span>
                                    <span class="font-['Inter',sans-serif] text-[12px] leading-none font-light text-[#8f8f8f]">
                                        Play with friends
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.play-mode-enter-active,
.play-mode-leave-active {
    transition: opacity 180ms ease;
}

.play-mode-enter-from,
.play-mode-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .play-mode-enter-active,
    .play-mode-leave-active {
        transition: none;
    }
}
</style>
