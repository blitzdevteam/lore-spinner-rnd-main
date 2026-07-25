<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import { LucidePlay } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    opening: string;
    storyTitle: string;
}>();

const emit = defineEmits<{
    begin: [];
}>();

const segments = computed(() => {
    const opening = typeof props.opening === 'string' ? props.opening : '';
    return opening
        .split(/<br\s*\/?>/gi)
        .map((s) => s.trim())
        .filter((s) => s.length > 0);
});

const visibleCount = ref(0);
const isComplete = ref(false);
const isBeginning = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

const revealNext = () => {
    if (visibleCount.value < segments.value.length) {
        visibleCount.value++;

        nextTick(() => {
            const container = document.querySelector('.opening-scroll-container');
            if (container) {
                container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
            }
        });

        const current = segments.value[visibleCount.value - 1] || '';
        const isEmpty = current === '';
        const delay = isEmpty ? 300 : Math.min(80 + current.length * 2, 250);
        timer = setTimeout(revealNext, delay);
    } else {
        setTimeout(() => {
            isComplete.value = true;
        }, 600);
    }
};

const skipToEnd = () => {
    if (timer) clearTimeout(timer);
    visibleCount.value = segments.value.length;
    setTimeout(() => {
        isComplete.value = true;
    }, 200);
};

const handleBegin = () => {
    if (isBeginning.value) return;
    isBeginning.value = true;
    emit('begin');
};

onMounted(() => {
    timer = setTimeout(revealNext, 800);
});

onUnmounted(() => {
    if (timer) clearTimeout(timer);
});
</script>

<template>
    <div class="relative flex h-svh flex-col items-center overflow-hidden bg-gray-950" @click="!isComplete && skipToEnd()">
        <BaseBackgroundGradient />

        <div class="opening-scroll-container relative z-10 flex-1 overflow-y-auto px-6 pt-24 pb-8 md:px-16" style="-ms-overflow-style: none; scrollbar-width: none;">
            <div class="mx-auto max-w-2xl md:max-w-4xl">
                <div class="opening-narration text-base font-light leading-relaxed text-white/95 md:text-lg">
                    <TransitionGroup name="line-reveal">
                        <span
                            v-for="(segment, index) in segments.slice(0, visibleCount)"
                            :key="index"
                            class="line-segment"
                        >
                            <span v-html="segment" />
                            <br v-if="index < visibleCount - 1" />
                        </span>
                    </TransitionGroup>

                    <span v-if="!isComplete" class="inline-block h-5 w-0.5 animate-pulse bg-primary-400 align-middle" />
                </div>
            </div>
        </div>

        <Transition name="begin-fade">
            <div v-if="isComplete" class="relative z-10 flex flex-col items-center gap-6 pb-16">
                <div class="h-px w-48 bg-gradient-to-r from-transparent via-primary-400/50 to-transparent" />

                <BaseButton
                    severity="primary"
                    class="h-14! rounded-full! px-10! text-lg!"
                    :processing="isBeginning"
                    @click.stop="handleBegin"
                >
                    <div class="flex items-center gap-3">
                        <LucidePlay class="size-5" fill="currentColor" />
                        <span class="font-semibold tracking-wide">Begin Your Journey</span>
                    </div>
                </BaseButton>

                <p class="text-sm text-gray-500">Press to enter {{ storyTitle }}</p>
            </div>
        </Transition>

        <div
            class="pointer-events-none absolute right-0 bottom-0 left-0 z-10 h-32 bg-gradient-to-t from-gray-950 to-transparent"
            :class="{ 'opacity-0': isComplete }"
        />
    </div>
</template>

<style scoped>
.opening-narration :deep(strong) {
    font-weight: 700;
    color: var(--color-primary);
}

.opening-narration :deep(em) {
    font-style: italic;
    color: var(--color-primary);
}

.line-reveal-enter-active {
    transition: all 0.5s ease-out;
}

.line-reveal-enter-from {
    opacity: 0;
    filter: blur(4px);
    transform: translateY(0.5rem);
}

.line-reveal-enter-to {
    opacity: 1;
    filter: blur(0);
    transform: translateY(0);
}

.begin-fade-enter-active {
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.begin-fade-enter-from {
    opacity: 0;
    transform: translateY(1.25rem);
}

.begin-fade-enter-to {
    opacity: 1;
    transform: translateY(0);
}

.line-segment {
    display: inline;
}

.opening-scroll-container::-webkit-scrollbar {
    display: none;
}
</style>
