<script setup lang="ts">
import type { PromptInterface } from '@/types';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        prompts: PromptInterface[];
        episodeNumber: number;
        titleId: string;
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const turnSummary = computed(() => {
    const count = props.prompts.length;

    return `${count} turn${count === 1 ? '' : 's'} played this episode...`;
});

function stripHtml(html: string): string {
    const input = typeof html === 'string' ? html : '';

    if (typeof document === 'undefined') {
        return input.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    const element = document.createElement('div');
    element.innerHTML = input;

    return (element.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function excerpt(text: string, max = 120): string {
    const plain = stripHtml(text);

    if (plain.length <= max) {
        return plain;
    }

    return `${plain.slice(0, max).trim()}…`;
}
</script>

<template>
    <section :class="['multiplayer-journal', { 'multiplayer-journal--compact': compact }]" :aria-labelledby="titleId">
        <header class="multiplayer-journal__summary">
            <h2 :id="titleId">Episode <span>{{ episodeNumber }}</span></h2>
            <p>{{ turnSummary }}</p>
        </header>

        <div v-if="prompts.length" class="multiplayer-journal__timeline">
            <article v-for="(prompt, index) in prompts" :key="prompt.id" class="multiplayer-journal__item">
                <div class="multiplayer-journal__rail" aria-hidden="true">
                    <span class="multiplayer-journal__number">{{ index + 1 }}</span>
                    <span v-if="index < prompts.length - 1" class="multiplayer-journal__line" />
                </div>

                <div class="multiplayer-journal__card">
                    <p v-if="index === 0" class="multiplayer-journal__phase">Opening</p>
                    <p v-else-if="!prompt.prompt && index === prompts.length - 1" class="multiplayer-journal__phase">Now</p>
                    <p
                        class="multiplayer-journal__narration"
                        :class="{ 'multiplayer-journal__narration--after-phase': index === 0 || (!prompt.prompt && index === prompts.length - 1) }"
                    >
                        {{ excerpt(prompt.response) }}
                    </p>
                    <p v-if="prompt.prompt" class="multiplayer-journal__choice">
                        You. {{ prompt.prompt === '__continue__' ? 'Continued' : prompt.prompt }}
                    </p>
                </div>
            </article>
        </div>

        <p v-else class="multiplayer-journal__empty">Your story events will appear here as you play.</p>
    </section>
</template>

<style scoped>
.multiplayer-journal {
    border-radius: 1.75rem;
    padding: clamp(1rem, 4vw, 2rem);
    background: #111;
}

.multiplayer-journal__summary {
    border: 1px solid #424242;
    border-radius: 1rem;
    padding: clamp(1rem, 3.5vw, 1.25rem);
    background: #111;
}

.multiplayer-journal__summary h2 {
    margin: 0;
    color: #f3f3f3;
    font-size: clamp(1.25rem, 4.5vw, 1.55rem);
    font-weight: 650;
    line-height: 1.2;
}

.multiplayer-journal__summary h2 span {
    color: #55ced9;
}

.multiplayer-journal__summary p {
    margin: 1rem 0 0;
    color: #d0d0d0;
    font-size: clamp(0.95rem, 3.5vw, 1.2rem);
    line-height: 1.25;
}

.multiplayer-journal__timeline {
    display: grid;
    gap: 1rem;
    margin-top: 1.25rem;
}

.multiplayer-journal__item {
    display: grid;
    grid-template-columns: 3.25rem minmax(0, 1fr);
    gap: 1rem;
    min-width: 0;
}

.multiplayer-journal__rail {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
}

.multiplayer-journal__number {
    display: grid;
    width: 3.25rem;
    height: 3.25rem;
    flex: 0 0 auto;
    place-items: center;
    border: 1px solid #55ced9;
    border-radius: 9999px;
    color: #55ced9;
    font-size: 1.15rem;
    line-height: 1;
}

.multiplayer-journal__line {
    width: 1px;
    min-height: 3rem;
    flex: 1 1 auto;
    margin-top: 0.75rem;
    background: linear-gradient(to bottom, #55ced9 0%, rgba(85, 206, 217, 0.08) 100%);
}

.multiplayer-journal__card {
    min-width: 0;
    border: 1px solid #424242;
    border-radius: 1.25rem;
    padding: clamp(0.875rem, 3.5vw, 1.2rem);
    background: #111;
}

.multiplayer-journal__phase {
    margin: 0;
    color: #5d5d5d;
    font-size: clamp(0.95rem, 3.5vw, 1.15rem);
    line-height: 1.2;
}

.multiplayer-journal__narration {
    margin: 0;
    color: #d8d8d8;
    font-size: clamp(0.95rem, 3.5vw, 1.15rem);
    line-height: 1.18;
    text-align: justify;
}

.multiplayer-journal__narration--after-phase {
    margin-top: 1rem;
}

.multiplayer-journal__choice {
    margin: 0.75rem 0 0;
    border-top: 1px solid #242424;
    padding-top: 0.75rem;
    color: #55ced9;
    font-size: clamp(0.95rem, 3.5vw, 1.15rem);
    font-weight: 500;
    line-height: 1.18;
}

.multiplayer-journal__empty {
    margin: 1.25rem 0 0;
    color: #a7a7a7;
    font-size: 1rem;
    line-height: 1.45;
    text-align: center;
}

.multiplayer-journal--compact {
    border-radius: 0.875rem;
    padding: 1rem;
}

.multiplayer-journal--compact .multiplayer-journal__summary {
    border-radius: 0.75rem;
    padding: 1rem;
}

.multiplayer-journal--compact .multiplayer-journal__summary h2 {
    font-size: 1.15rem;
}

.multiplayer-journal--compact .multiplayer-journal__summary p,
.multiplayer-journal--compact .multiplayer-journal__phase,
.multiplayer-journal--compact .multiplayer-journal__narration,
.multiplayer-journal--compact .multiplayer-journal__choice {
    font-size: 0.875rem;
}

.multiplayer-journal--compact .multiplayer-journal__summary p {
    margin-top: 0.625rem;
}

.multiplayer-journal--compact .multiplayer-journal__item {
    grid-template-columns: 2.25rem minmax(0, 1fr);
    gap: 0.75rem;
}

.multiplayer-journal--compact .multiplayer-journal__number {
    width: 2.25rem;
    height: 2.25rem;
    font-size: 0.875rem;
}

.multiplayer-journal--compact .multiplayer-journal__card {
    border-radius: 0.875rem;
    padding: 0.875rem;
}

.multiplayer-journal--compact .multiplayer-journal__narration--after-phase {
    margin-top: 0.625rem;
}

@media (max-width: 440px) {
    .multiplayer-journal {
        border-radius: 1.25rem;
        padding: 1rem;
    }

    .multiplayer-journal__item {
        grid-template-columns: 3.25rem minmax(0, 1fr);
        gap: 0.75rem;
    }

    .multiplayer-journal__number {
        width: 3.25rem;
        height: 3.25rem;
        font-size: 1.15rem;
    }
}
</style>
