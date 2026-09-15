<script setup lang="ts">
import { X } from 'lucide-vue-next';
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
            skipButton.value?.focus();

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
                class="fixed inset-0 z-[400] flex items-center justify-center p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="first-story-tutorial-title"
                aria-describedby="first-story-tutorial-description"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-default bg-black/85 backdrop-blur-md"
                    aria-label="Skip tutorial"
                    tabindex="-1"
                    @click="skip"
                />

                <section
                    class="first-story-tutorial-panel relative z-10 flex max-h-[calc(100svh-2rem)] w-full max-w-4xl flex-col overflow-y-auto rounded-2xl border border-primary-500/25 bg-[#0b1011] shadow-[0_25px_120px_rgba(0,0,0,0.82)] sm:max-h-[calc(100svh-3rem)]"
                    @click.stop
                >
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 z-10 h-px bg-linear-to-r from-transparent via-primary-500 to-transparent"
                        aria-hidden="true"
                    />

                    <button
                        ref="skipButton"
                        type="button"
                        class="absolute top-3 right-3 z-20 flex size-9 items-center justify-center rounded-full border border-white/15 bg-black/55 text-white/80 backdrop-blur-sm transition hover:border-primary-300/60 hover:bg-primary-950/80 hover:text-white focus:ring-2 focus:ring-primary-300/70 focus:outline-none"
                        aria-label="Skip tutorial"
                        @click="skip"
                    >
                        <X class="size-4" :stroke-width="2" />
                    </button>

                    <div class="flex flex-col gap-2 px-5 pt-7 pb-5 sm:px-8 sm:pt-8 sm:pb-6">
                        <p class="font-inter text-[0.6875rem] font-medium tracking-[0.24em] text-primary-300/80 uppercase">
                            First story
                        </p>
                        <h2 id="first-story-tutorial-title" class="font-marcellus-sc pr-10 text-2xl leading-tight text-white sm:text-3xl">
                            Welcome to LoreSpinner
                        </h2>
                        <p id="first-story-tutorial-description" class="max-w-2xl text-sm leading-relaxed text-white/60 sm:text-[0.9375rem]">
                            Watch this quick guide to see how your choices shape the story before you begin.
                        </p>
                    </div>

                    <div class="relative mx-5 overflow-hidden rounded-xl border border-white/12 bg-black sm:mx-8">
                        <video
                            ref="videoElement"
                            class="block aspect-video w-full bg-black object-contain"
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

                    <div class="flex flex-col gap-4 px-5 pt-5 pb-5 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:pt-6 sm:pb-7">
                        <p class="max-w-md text-xs leading-relaxed text-white/45 sm:text-sm">
                            You can skip this guide at any time and start playing immediately.
                        </p>
                        <button
                            type="button"
                            class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg border border-primary-500 bg-primary-950 px-5 font-inter text-sm font-medium text-white transition hover:border-primary-300 hover:bg-primary-800 focus:ring-2 focus:ring-primary-300/70 focus:outline-none active:bg-primary-900"
                            @click="skip"
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
