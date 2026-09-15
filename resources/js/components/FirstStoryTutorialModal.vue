<script setup lang="ts">
import { nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    videoSrc: string;
}>();

const emit = defineEmits<{
    skip: [];
}>();

const videoElement = ref<HTMLVideoElement | null>(null);
const skipButton = ref<HTMLButtonElement | null>(null);

function skip(): void {
    emit('skip');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        skip();
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
            skipButton.value?.focus({ preventScroll: true });

            const playback = videoElement.value?.play();
            playback?.catch(() => {
                // Native controls remain available when autoplay is blocked.
            });
        });

        return;
    }

    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
    videoElement.value?.pause();
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
    videoElement.value?.pause();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="first-story-tutorial">
            <div
                v-if="open"
                class="fixed inset-0 z-[400] flex items-center justify-center bg-black/35 p-4 backdrop-blur-xl sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="first-story-tutorial-title"
                aria-describedby="first-story-tutorial-description"
                @click="skip"
            >
                <section
                    class="first-story-tutorial-panel relative z-10 flex max-h-[calc(100svh-2rem)] w-full max-w-4xl flex-col overflow-y-auto rounded-2xl bg-black/20 shadow-[0_25px_120px_rgba(0,0,0,0.72)] backdrop-blur-2xl sm:max-h-[calc(100svh-3rem)]"
                >
                    <div class="flex flex-col items-center gap-2 px-5 pt-7 pb-5 text-center sm:px-8 sm:pt-8 sm:pb-6">
                        <h2 id="first-story-tutorial-title" class="font-marcellus-sc text-2xl leading-tight text-white sm:text-3xl">
                            Welcome to LoreSpinner
                        </h2>
                        <p id="first-story-tutorial-description" class="max-w-2xl text-center text-sm leading-relaxed text-white/60 sm:text-[0.9375rem]">
                            Watch this quick guide to see how your choices shape the story before you begin.
                        </p>
                    </div>

                    <div class="relative mx-5 overflow-hidden rounded-xl bg-black/10 sm:mx-8" @click.stop>
                        <video
                            ref="videoElement"
                            class="block aspect-video w-full bg-transparent object-contain"
                            :src="videoSrc"
                            controls
                            autoplay
                            playsinline
                            preload="auto"
                            @ended="skip"
                        >
                            Your browser does not support embedded videos.
                        </video>
                    </div>

                    <div class="px-5 pt-5 pb-5 sm:px-8 sm:pt-6 sm:pb-7">
                        <button
                            ref="skipButton"
                            type="button"
                            class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-primary-500 px-5 font-inter text-sm font-medium text-[#061214] transition hover:bg-primary-300 focus:ring-2 focus:ring-primary-200/70 focus:outline-none active:bg-primary-400"
                            @click.stop="skip"
                        >
                            Skip tutorial
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.first-story-tutorial-enter-active,
.first-story-tutorial-leave-active {
    transition: opacity 220ms ease;
}

.first-story-tutorial-enter-from,
.first-story-tutorial-leave-to {
    opacity: 0;
}

.first-story-tutorial-panel {
    transition: transform 220ms ease;
}

.first-story-tutorial-enter-from .first-story-tutorial-panel,
.first-story-tutorial-leave-to .first-story-tutorial-panel {
    transform: translateY(0.75rem) scale(0.985);
}

@media (prefers-reduced-motion: reduce) {
    .first-story-tutorial-enter-active,
    .first-story-tutorial-leave-active,
    .first-story-tutorial-panel {
        transition: none;
    }
}
</style>
