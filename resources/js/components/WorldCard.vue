<script setup lang="ts">
import WorldStoriesModal from '@/components/WorldStoriesModal.vue';
import type { StoryInterface, WorldInterface } from '@/types';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    world: WorldInterface;
    expanded?: boolean;
    dimmed?: boolean;
}>();

const stories = computed(() => props.world.stories ?? []);
const storyCount = computed(() => props.world.stories_count ?? stories.value.length);
const storyCountLabel = computed(() => (storyCount.value === 1 ? '1 Story' : `${storyCount.value} Stories`));
const tags = computed(() => (props.world.tags ?? []).slice(0, 3));
const previewStories = computed(() => stories.value.slice(0, 2));
const avatarStories = computed(() => stories.value.slice(0, 3));
const brokenCovers = ref(new Set<number>());
const modalOpen = ref(false);

function openWorldModal(): void {
    modalOpen.value = true;
}

function markCoverBroken(storyId: number): void {
    brokenCovers.value = new Set(brokenCovers.value).add(storyId);
}

function hasCover(story: { id: number; cover?: string | null }): boolean {
    return Boolean(story.cover) && !brokenCovers.value.has(story.id);
}

function storyHref(story: StoryInterface): string {
    return storyShow(story.slug).url;
}
</script>

<template>
    <article
        class="world-card relative h-[18.125rem] w-full shrink-0"
        :class="{
            'world-card--expanded': expanded,
            'world-card--dimmed': dimmed,
        }"
    >
        <div class="world-card__shell">
            <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true">
                <img
                    v-if="world.background"
                    :src="world.background"
                    :alt="world.title"
                    class="world-card__image absolute inset-0 size-full object-cover"
                />
                <div class="world-card__shade world-card__shade--rest absolute inset-0" />
                <div class="world-card__shade world-card__shade--hover absolute inset-0" />
            </div>

            <div class="world-card__border" aria-hidden="true">
                <span class="world-card__border-spin" />
            </div>

            <div class="world-card__body">
                <div class="flex w-full flex-col gap-2.5">
                    <div class="flex h-[4.625rem] w-full flex-col justify-center">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex flex-col gap-1">
                                <button
                                    type="button"
                                    class="world-card__title-btn"
                                    :aria-expanded="modalOpen"
                                    aria-haspopup="dialog"
                                    @click.stop="openWorldModal"
                                >
                                    <h3 class="font-inria-serif text-[1.125rem] leading-normal font-bold text-white">
                                        {{ world.title }}
                                    </h3>
                                </button>
                                <p class="text-[0.8125rem] leading-normal font-light text-white">
                                    {{ world.tagline }}
                                </p>
                            </div>
                            <p v-if="tags.length" class="flex flex-wrap gap-x-3 text-[0.75rem] leading-normal font-light text-[#d8d8d8]">
                                <span v-for="tag in tags" :key="tag">{{ tag }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="h-0 w-full border-t border-white/20" />
                </div>

                <div v-if="previewStories.length" class="world-card__stories">
                    <div class="world-card__stories-inner">
                        <div class="flex h-[7.625rem] w-full items-center overflow-hidden">
                            <div class="flex items-center gap-2.5">
                                <Link
                                    v-for="story in previewStories"
                                    :key="story.id"
                                    :href="storyHref(story)"
                                    class="world-card__story-link pointer-events-auto flex shrink-0 items-center overflow-hidden rounded border-[0.25px] border-[#373737] bg-[#0f0f0f] p-[2.5px] no-underline"
                                    :aria-label="`Open ${story.title}`"
                                    @click.stop
                                >
                                    <img
                                        v-if="hasCover(story)"
                                        :src="story.cover"
                                        :alt="story.title"
                                        class="h-[7.3125rem] w-[4.875rem] rounded-[2.5px] object-cover"
                                        @error="markCoverBroken(story.id)"
                                    />
                                    <span
                                        v-else
                                        class="flex h-[7.3125rem] w-[4.875rem] items-end overflow-hidden rounded-[2.5px] bg-[#1a1a1a] p-1.5 text-[0.625rem] leading-tight text-[#8f8f8f]"
                                    >
                                        {{ story.title }}
                                    </span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="world-card__footer">
                    <div class="world-card__meta" :aria-hidden="expanded ? true : undefined">
                        <div v-if="avatarStories.length" class="flex items-center pr-2">
                            <Link
                                v-for="(story, index) in avatarStories"
                                :key="story.id"
                                :href="storyHref(story)"
                                class="pointer-events-auto relative size-[2.34375rem] overflow-hidden rounded-full border border-black/40 no-underline"
                                :class="index > 0 ? '-ml-4' : ''"
                                :style="{ zIndex: avatarStories.length - index }"
                                :aria-label="`Open ${story.title}`"
                                @click.stop
                            >
                                <img
                                    v-if="hasCover(story)"
                                    :src="story.cover"
                                    :alt="story.title"
                                    class="size-full object-cover opacity-85"
                                    @error="markCoverBroken(story.id)"
                                />
                                <span v-else class="block size-full bg-[#2a2a2a]" />
                            </Link>
                        </div>
                        <p class="text-[0.75rem] leading-[1.125rem] font-light text-[#8f8f8f]">
                            {{ storyCountLabel }}
                        </p>
                    </div>

                    <span class="world-card__cta-fill" aria-hidden="true" />

                    <button
                        type="button"
                        class="world-card__cta"
                        :aria-expanded="modalOpen"
                        aria-haspopup="dialog"
                        @click.stop="openWorldModal"
                    >
                        <span class="world-card__cta-label">Enter World</span>
                    </button>
                </div>
            </div>
        </div>

        <WorldStoriesModal :world="modalOpen ? world : null" @close="modalOpen = false" />
    </article>
</template>

<style scoped>
.world-card {
    --world-ease: cubic-bezier(0.22, 1, 0.36, 1);
    --world-duration: 480ms;
    z-index: 1;
    box-sizing: border-box;
    overflow: hidden;
    transform-origin: 50% 50%;
    transition:
        opacity var(--world-duration) ease,
        filter 320ms ease;
}

.world-card__shell {
    position: relative;
    display: flex;
    height: 100%;
    width: 100%;
    flex-direction: column;
    overflow: hidden;
    border-radius: 0.75rem;
    border: 0.5px solid #191919;
    padding: 0.75rem 17px;
    box-sizing: border-box;
    transition:
        border-color 280ms ease,
        box-shadow 280ms ease;
}

.world-card--expanded {
    z-index: 4;
}

.world-card--expanded .world-card__shell {
    border-color: color-mix(in srgb, var(--color-primary) 38%, #191919);
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--color-primary) 18%, transparent),
        inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.world-card--dimmed {
    z-index: 0;
    opacity: 0.78;
    filter: brightness(0.88);
}

.world-card__image {
    transform: scale(1);
    transition: transform 700ms var(--world-ease);
}

.world-card--expanded .world-card__image {
    transform: scale(1);
}

.world-card__shade {
    transition: opacity 280ms ease;
}

.world-card__shade--rest {
    background-image:
        linear-gradient(to top, rgb(0, 0, 0) 14.31%, rgba(0, 0, 0, 0) 68.276%),
        linear-gradient(90deg, rgba(0, 0, 0, 0.05), rgba(0, 0, 0, 0.05));
}

.world-card__shade--hover {
    opacity: 0;
    background-image:
        linear-gradient(to top, rgb(0, 0, 0) 11.552%, rgba(0, 0, 0, 0) 100%),
        linear-gradient(90deg, rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4));
}

.world-card--expanded .world-card__shade--rest {
    opacity: 0;
}

.world-card--expanded .world-card__shade--hover {
    opacity: 1;
}

.world-card__border {
    pointer-events: none;
    position: absolute;
    inset: 0;
    z-index: 3;
    overflow: hidden;
    border-radius: inherit;
    padding: 1px;
    opacity: 0;
    transition: opacity 280ms ease;
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
}

.world-card--expanded .world-card__border {
    opacity: 0;
}

.world-card__border-spin {
    position: absolute;
    inset: -70%;
    background: conic-gradient(
        from 0deg,
        transparent 0deg,
        transparent 250deg,
        color-mix(in srgb, var(--color-primary) 0%, transparent) 280deg,
        var(--color-primary) 318deg,
        #f4feff 336deg,
        var(--color-primary) 348deg,
        transparent 360deg
    );
}

.world-card--expanded .world-card__border-spin {
    animation: world-card-border-flow 2.4s linear infinite;
}

.world-card__body {
    position: relative;
    z-index: 1;
    display: flex;
    height: 16.625rem;
    width: 100%;
    flex-direction: column;
    justify-content: flex-end;
}

.world-card__stories {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(4px);
    pointer-events: none;
    transition:
        grid-template-rows var(--world-duration) var(--world-ease),
        opacity 280ms ease,
        transform var(--world-duration) var(--world-ease);
}

.world-card--expanded .world-card__stories {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.world-card__stories-inner {
    min-height: 0;
    overflow: hidden;
}

.world-card--expanded .world-card__stories-inner {
    padding-bottom: 1.25rem;
}

.world-card__body > :first-child {
    margin-bottom: 1.25rem;
    transition: margin-bottom var(--world-duration) var(--world-ease);
}

.world-card--expanded .world-card__body > :first-child {
    margin-bottom: 0.625rem;
}

.world-card__footer {
    position: relative;
    display: flex;
    height: 2.5rem;
    align-items: center;
    transition: height var(--world-duration) var(--world-ease);
}

.world-card--expanded .world-card__footer {
    height: 1.875rem;
}

.world-card__meta {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.25rem;
    transition:
        opacity 280ms ease,
        transform var(--world-duration) var(--world-ease);
}

.world-card--expanded .world-card__meta {
    opacity: 0;
    transform: translateX(-8px);
    pointer-events: none;
}

.world-card__cta-fill {
    pointer-events: none;
    position: absolute;
    inset-inline: 0;
    top: 50%;
    z-index: 1;
    height: 1.875rem;
    border-radius: 5px;
    background: var(--color-primary);
    opacity: 0;
    transform: translateY(-50%);
    clip-path: inset(0 50% 0 50% round 5px);
    transition:
        clip-path var(--world-duration) var(--world-ease),
        opacity 180ms ease;
}

.world-card--expanded .world-card__cta-fill {
    opacity: 1;
    clip-path: inset(0 0 0 0 round 5px);
}

.world-card__title-btn {
    pointer-events: auto;
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
    text-align: left;
    appearance: none;
    transition:
        opacity 180ms ease,
        transform 120ms ease;
}

.world-card__title-btn:hover h3 {
    color: var(--color-primary);
}

.world-card__title-btn:active {
    opacity: 0.88;
}

.world-card__title-btn:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
    border-radius: 2px;
}

.world-card__story-link {
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease,
        opacity 150ms ease;
}

.world-card__story-link:hover {
    border-color: color-mix(in srgb, var(--color-primary) 35%, #373737);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.world-card__story-link:active {
    opacity: 0.9;
}

.world-card__cta:active {
    opacity: 0.92;
}

.world-card__cta:active::before {
    background: color-mix(in srgb, var(--color-primary) 18%, #0e1f22);
}

.world-card__meta :deep(a) {
    transition: opacity 150ms ease;
}

.world-card__meta :deep(a:hover) {
    opacity: 0.95;
    z-index: 10;
}

.world-card__meta :deep(a:active) {
    opacity: 0.82;
}

.world-card__cta {
    position: absolute;
    inset-inline: 0;
    top: 50%;
    z-index: 2;
    display: flex;
    height: 1.875rem;
    align-items: center;
    overflow: hidden;
    border: 0;
    background: transparent;
    padding: 0;
    cursor: pointer;
    appearance: none;
    font: inherit;
    font-size: 0.8125rem;
    font-weight: 400;
    color: #fff;
    text-decoration: none;
    transform: translateY(-50%);
}

.world-card__cta::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 6.875rem;
    border-radius: 5px;
    border: 0.5px solid var(--color-primary);
    background: #0e1f22;
    transition: opacity 180ms ease;
}

.world-card--expanded .world-card__cta::before {
    opacity: 0;
}

.world-card__cta-label {
    position: absolute;
    left: calc(100% - 6.875rem);
    z-index: 1;
    width: 6.875rem;
    text-align: center;
    transition:
        left var(--world-duration) var(--world-ease),
        width var(--world-duration) var(--world-ease),
        color 280ms ease,
        font-weight 280ms ease;
}

.world-card--expanded .world-card__cta-label {
    left: 0;
    width: 100%;
    font-weight: 500;
    color: #000;
}

.world-card__cta:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}

@keyframes world-card-border-flow {
    to {
        transform: rotate(360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .world-card,
    .world-card__shell,
    .world-card__image,
    .world-card__shade,
    .world-card__border,
    .world-card__stories,
    .world-card__meta,
    .world-card__cta,
    .world-card__cta::before,
    .world-card__cta-label,
    .world-card__cta-fill,
    .world-card__footer,
    .world-card__body > :first-child {
        transition: none;
        animation: none;
    }

    .world-card--expanded .world-card__border-spin {
        animation: none;
    }
}
</style>
