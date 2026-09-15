<script setup lang="ts">
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import { CompletionReceipt } from '@/types';
import { computed, ref } from 'vue';

const props = defineProps<{
    receipt: CompletionReceipt;
    keepsake?: boolean;
}>();

const emit = defineEmits<{
    continue: [];
    'begin-again': [];
    library: [];
}>();

const copied = ref(false);
const isStory = computed(() => props.receipt.mode === 'story');
const identity = computed(() => (isStory.value ? props.receipt.became : props.receipt.becoming) ?? null);
const remembers = computed(() =>
    isStory.value ? (props.receipt.record ?? []) : (props.receipt.world_remembers ?? []),
);
const settled = computed(() => props.receipt.what_settled ?? []);
const canShare = computed(() => Boolean(props.receipt.share_line));

const heading = computed(() =>
    isStory.value ? 'Story complete' : `Episode ${props.receipt.session_number} complete`,
);

async function shareLine(): Promise<void> {
    const line = props.receipt.share_line;
    if (!line) {
        return;
    }

    try {
        await navigator.clipboard.writeText(line);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 1800);
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <section class="mx-auto w-full max-w-2xl px-1 py-1 sm:px-2 sm:py-2">
        <article
            class="completion-card gameplay-narration-glass relative overflow-hidden rounded-2xl px-4 py-3.5 text-center sm:px-6 sm:py-5"
            :class="isStory ? 'completion-card--story' : 'completion-card--episode'"
        >
            <span class="completion-card__corner completion-card__corner--tl" aria-hidden="true" />
            <span class="completion-card__corner completion-card__corner--tr" aria-hidden="true" />
            <span class="completion-card__corner completion-card__corner--bl" aria-hidden="true" />
            <span class="completion-card__corner completion-card__corner--br" aria-hidden="true" />

            <div class="relative z-[1] flex flex-col items-center gap-2.5 sm:gap-3">
                <header class="flex w-full flex-col items-center gap-1">
                    <p v-if="receipt.story_title" class="text-xs tracking-[0.18em] text-gray-400 uppercase sm:text-sm">
                        {{ receipt.story_title }}
                    </p>
                    <GameplayOrnamentDivider :label="heading" color="#d4b483" />
                </header>

                <p
                    v-if="receipt.final_line"
                    class="max-w-xl text-base leading-snug font-light text-gray-100 italic sm:text-lg"
                >
                    {{ receipt.final_line }}
                </p>

                <div class="flex w-full max-w-xl flex-col gap-2.5">
                    <div v-if="settled.length" class="completion-card__panel">
                        <h2 class="text-[0.65rem] tracking-[0.2em] text-primary-300 uppercase">What settled</h2>
                        <ul class="flex flex-col gap-1 text-left text-sm leading-snug text-gray-300">
                            <li v-for="line in settled" :key="line">{{ line }}</li>
                        </ul>
                    </div>

                    <div v-if="remembers.length" class="completion-card__panel">
                        <h2 class="text-[0.65rem] tracking-[0.2em] text-primary-300 uppercase">
                            {{ isStory ? 'Your record' : 'The world remembers' }}
                        </h2>
                        <ul class="flex flex-col gap-1 text-left text-sm leading-snug text-gray-300">
                            <li v-for="line in remembers" :key="line">{{ line }}</li>
                        </ul>
                    </div>

                    <div v-if="identity" class="completion-card__panel">
                        <h2 class="text-[0.65rem] tracking-[0.2em] text-primary-300 uppercase">
                            {{ isStory ? 'You became' : 'You are becoming' }}
                        </h2>
                        <p class="text-base font-medium text-white sm:text-lg">{{ identity.identity }}</p>
                        <p v-if="identity.style_line" class="text-xs text-gray-400 italic sm:text-sm">
                            {{ identity.style_line }}
                        </p>
                    </div>

                    <div
                        v-if="(!isStory && (receipt.commitment || receipt.what_waits)) || (isStory && receipt.leaves_behind)"
                        class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"
                    >
                        <div v-if="!isStory && receipt.commitment" class="completion-card__panel h-full">
                            <h2 class="text-[0.65rem] tracking-[0.2em] text-primary-300 uppercase">Your commitment</h2>
                            <p class="text-sm leading-snug text-gray-200">{{ receipt.commitment }}</p>
                        </div>

                        <div v-if="!isStory && receipt.what_waits" class="completion-card__panel h-full">
                            <h2 class="text-[0.65rem] tracking-[0.2em] text-primary-300 uppercase">What waits</h2>
                            <p class="text-sm leading-snug text-gray-200">{{ receipt.what_waits.next_opens }}</p>
                            <p v-if="receipt.what_waits.unresolved_promise" class="text-xs text-gray-400 italic sm:text-sm">
                                {{ receipt.what_waits.unresolved_promise }}
                            </p>
                        </div>

                        <div
                            v-if="isStory && receipt.leaves_behind"
                            class="completion-card__panel sm:col-span-2"
                        >
                            <h2 class="text-[0.65rem] tracking-[0.2em] text-primary-300 uppercase">
                                What the story leaves behind
                            </h2>
                            <p class="text-sm leading-snug text-gray-200 italic">{{ receipt.leaves_behind }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="!keepsake" class="flex flex-col items-center gap-1.5">
                    <template v-if="isStory">
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <button
                                type="button"
                                class="rounded-full border border-primary-400/60 bg-primary-400/10 px-6 py-2 text-sm font-medium text-primary-300 transition hover:bg-primary-400/20 hover:text-primary-200"
                                @click="emit('library')"
                            >
                                Library
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-white/15 bg-white/5 px-6 py-2 text-sm font-medium text-gray-300 transition hover:border-white/30 hover:text-white"
                                @click="emit('begin-again')"
                            >
                                Begin again
                            </button>
                        </div>
                        <button
                            v-if="canShare"
                            type="button"
                            class="text-xs tracking-[0.16em] text-gray-500 uppercase transition hover:text-primary-300"
                            @click="shareLine"
                        >
                            {{ copied ? 'Copied' : 'Share your ending' }}
                        </button>
                    </template>
                    <template v-else>
                        <button
                            type="button"
                            class="rounded-full border border-primary-400/60 bg-primary-400/10 px-6 py-2 text-sm font-medium text-primary-300 transition hover:bg-primary-400/20 hover:text-primary-200"
                            @click="emit('continue')"
                        >
                            Continue
                        </button>
                        <button
                            v-if="canShare"
                            type="button"
                            class="text-xs tracking-[0.16em] text-gray-500 uppercase transition hover:text-primary-300"
                            @click="shareLine"
                        >
                            {{ copied ? 'Copied' : 'Share' }}
                        </button>
                    </template>
                </div>
                <button
                    v-else-if="canShare"
                    type="button"
                    class="text-xs tracking-[0.16em] text-gray-500 uppercase transition hover:text-primary-300"
                    @click="shareLine"
                >
                    {{ copied ? 'Copied' : 'Copy share line' }}
                </button>
            </div>
        </article>
    </section>
</template>

<style scoped>
.completion-card {
    border: 1px solid color-mix(in srgb, #d4b483 42%, transparent);
    box-shadow:
        inset 0 0 56px 4px var(--glass-tint, transparent),
        inset 1px 1px 0.5px -1px rgba(255, 255, 255, 0.18),
        0 12px 40px rgba(0, 0, 0, 0.38);
}

.completion-card--story {
    border-color: color-mix(in srgb, #59c1ca 38%, #d4b483 28%);
}

.completion-card__corner {
    position: absolute;
    z-index: 2;
    width: 0.9rem;
    height: 0.9rem;
    border-color: #d4b483;
    border-style: solid;
    pointer-events: none;
}

.completion-card--story .completion-card__corner {
    border-color: #59c1ca;
}

.completion-card__corner--tl {
    top: 0.5rem;
    left: 0.5rem;
    border-width: 1.5px 0 0 1.5px;
}

.completion-card__corner--tr {
    top: 0.5rem;
    right: 0.5rem;
    border-width: 1.5px 1.5px 0 0;
}

.completion-card__corner--bl {
    bottom: 0.5rem;
    left: 0.5rem;
    border-width: 0 0 1.5px 1.5px;
}

.completion-card__corner--br {
    right: 0.5rem;
    bottom: 0.5rem;
    border-width: 0 1.5px 1.5px 0;
}

.completion-card__panel {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    border: 1px solid rgb(255 255 255 / 0.1);
    border-radius: 0.75rem;
    background: rgb(0 0 0 / 0.22);
    padding: 0.6rem 0.85rem;
}
</style>
