<script setup lang="ts">
import closeIcon from '@/assets/story-play/play-mode-close.svg';
import { nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    confirming?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();

const closeButton = ref<HTMLButtonElement | null>(null);

function close(): void {
    if (props.confirming) {
        return;
    }

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
        <Transition name="restart-confirm">
            <div
                v-if="open"
                class="fixed inset-0 z-[350] flex items-center justify-center bg-black/65 p-4 backdrop-blur-[6px]"
                role="dialog"
                aria-modal="true"
                aria-labelledby="restart-story-title"
                @click="close"
            >
                <div class="flex w-full max-w-[348px] flex-col items-center gap-5" @click.stop>
                    <button
                        ref="closeButton"
                        type="button"
                        class="flex size-[30px] items-center justify-center rounded-full"
                        aria-label="Close"
                        :disabled="confirming"
                        @click="close"
                    >
                        <img :src="closeIcon" alt="" width="18" height="18" />
                    </button>

                    <div class="w-full rounded-[30px] bg-[#0d0d0d] p-[15px]">
                        <div class="flex flex-col items-center gap-[30px]">
                            <p
                                id="restart-story-title"
                                class="w-full text-center font-['Inter',sans-serif] text-[14px] leading-[26px] font-normal text-white"
                            >
                                Are you sure you want to restart this story?
                            </p>

                            <div class="flex w-full flex-col gap-2">
                                <button
                                    type="button"
                                    class="flex h-[39px] w-full items-center justify-center rounded-[8px] border-[0.5px] border-[#59c1ca] bg-[#0e1f22] font-['Inter',sans-serif] text-[16px] leading-none font-normal text-white disabled:opacity-60"
                                    :disabled="confirming"
                                    @click="emit('confirm')"
                                >
                                    Yes
                                </button>
                                <button
                                    type="button"
                                    class="flex h-[39px] w-full items-center justify-center rounded-[8px] font-['Inter',sans-serif] text-[16px] leading-none font-normal text-[#7e7e7e] disabled:opacity-60"
                                    :disabled="confirming"
                                    @click="close"
                                >
                                    No
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
.restart-confirm-enter-active,
.restart-confirm-leave-active {
    transition: opacity 180ms ease;
}

.restart-confirm-enter-from,
.restart-confirm-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .restart-confirm-enter-active,
    .restart-confirm-leave-active {
        transition: none;
    }
}
</style>
