<script setup lang="ts">
import arrowIcon from '@/assets/story-play/play-mode-arrow.svg';
import closeIcon from '@/assets/story-play/play-mode-close.svg';
import groupIcon from '@/assets/story-play/play-mode-group.svg';
import soloIcon from '@/assets/story-play/play-mode-solo.svg';
import { nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    cover?: string | null;
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
                <div class="flex w-full max-w-[302px] flex-col items-center gap-5" @click.stop>
                    <button
                        ref="closeButton"
                        type="button"
                        class="flex size-[22.5px] items-center justify-center overflow-hidden rounded-[13.5px]"
                        aria-label="Close"
                        @click="close"
                    >
                        <img :src="closeIcon" alt="" />
                    </button>

                    <div class="w-full rounded-[12px] border-[0.5px] border-[#59c1ca] bg-black p-2.5">
                        <div class="flex flex-col items-center">
                            <div
                                v-if="cover"
                                class="relative mb-[-34px] h-[160px] w-full overflow-hidden rounded-[5px]"
                            >
                                <img :src="cover" alt="" class="absolute inset-0 size-full object-cover" />
                                <div class="play-mode-cover-fade pointer-events-none absolute inset-0 rounded-[5px]" />
                            </div>

                            <div class="relative flex w-full flex-col gap-3.5">
                                <p
                                    id="play-mode-title"
                                    class="flex h-[50px] items-center justify-center text-center font-inter text-[16px] leading-none font-normal text-white"
                                >
                                    How would you like to play?
                                </p>

                                <div class="flex w-full flex-col gap-3.5">
                                    <button
                                        type="button"
                                        class="flex h-14 w-full items-center justify-between rounded-[10px] border-[0.5px] border-[#373737] bg-black py-2 pr-2.5 pl-2 text-left transition-colors hover:bg-white/[0.04] focus-visible:outline focus-visible:outline-1 focus-visible:outline-white/40"
                                        @click="emit('solo')"
                                    >
                                        <span class="flex min-w-0 items-center gap-1.5">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full border-[0.4px] border-[#0f0f0f]">
                                                <img :src="soloIcon" alt="" />
                                            </span>
                                            <span class="flex min-w-0 flex-col items-start gap-1">
                                                <span class="font-inter text-[16px] leading-none font-normal text-[#ffbe58]">Solo Spin</span>
                                                <span class="font-inter text-[12px] leading-none font-light text-[#8f8f8f]">Play on your own</span>
                                            </span>
                                        </span>
                                        <img :src="arrowIcon" alt="" class="shrink-0" />
                                    </button>

                                    <button
                                        type="button"
                                        class="flex h-14 w-full items-center justify-between rounded-[10px] border-[0.5px] border-[#373737] bg-black py-2 pr-2.5 pl-2 text-left transition-colors hover:bg-white/[0.04] focus-visible:outline focus-visible:outline-1 focus-visible:outline-white/40"
                                        @click="emit('group')"
                                    >
                                        <span class="flex min-w-0 items-center gap-1.5">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full border-[0.4px] border-[#59c1ca]">
                                                <img :src="groupIcon" alt="" />
                                            </span>
                                            <span class="flex min-w-0 flex-col items-start gap-1">
                                                <span class="font-inter text-[16px] leading-none font-normal text-[#59c1ca]">Group Spin</span>
                                                <span class="font-inter text-[12px] leading-none font-light text-[#8f8f8f]">Play with friends</span>
                                            </span>
                                        </span>
                                        <img :src="arrowIcon" alt="" class="shrink-0" />
                                    </button>
                                </div>
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

.play-mode-cover-fade {
    background-image:
        linear-gradient(178.41deg, rgba(0, 0, 0, 0) 55.569%, rgb(0, 0, 0) 98.502%),
        linear-gradient(180deg, rgb(0, 0, 0) 5.9375%, rgba(0, 0, 0, 0) 100%);
}

@media (prefers-reduced-motion: reduce) {
    .play-mode-enter-active,
    .play-mode-leave-active {
        transition: none;
    }
}
</style>
