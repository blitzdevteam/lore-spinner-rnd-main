<script setup lang="ts">
import sherlockCover from '@/assets/featured/sherlock-speckled-band.jpg';
import wizardozCover from '@/assets/featured/wizardoz.png';
import mainLogo from '@/assets/logo/main-logo.png';
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { ComingSoonStoryInterface } from '@/types';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface FeaturedGame {
    id: string | number;
    title: string;
    cover: string;
    playable: boolean;
    slug?: string;
    teaser: string;
}

const props = withDefaults(
    defineProps<{
        comingSoonStories?: ComingSoonStoryInterface[];
    }>(),
    {
        comingSoonStories: () => [],
    },
);

const playableGames: FeaturedGame[] = [
    {
        id: 'the-adventure-of-the-speckled-band',
        title: 'Sherlock Holmes: The Speckled Band',
        cover: sherlockCover,
        playable: true,
        slug: 'the-adventure-of-the-speckled-band',
        teaser: 'In a locked room with only a ventilator connecting it to her sister\'s chamber, a woman dies under mysterious circumstances. Now, the same sinister threat looms over her twin.',
    },
    {
        id: 'the-wonderful-wizard-of-oz',
        title: 'The Wonderful Wizard of Oz',
        cover: wizardozCover,
        playable: true,
        slug: 'the-wonderful-wizard-of-oz',
        teaser: 'Swept away to a magical land, Dorothy must journey across emerald cities, enchanted forests, and treacherous mountains to find her way home.',
    },
];

const games = computed<FeaturedGame[]>(() => [
    ...playableGames,
    ...props.comingSoonStories.map((story) => ({
        id: story.id,
        title: story.title,
        cover: story.cover,
        playable: false,
        slug: story.slug ?? undefined,
        teaser: story.teaser ?? '',
    })),
]);

const sliderEl = ref<HTMLElement | null>(null);

function scrollSlider(direction: -1 | 1) {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.new-story-card');
    const gap = 10;
    const step = card ? card.offsetWidth + gap : 310;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}

const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(game: FeaturedGame): StorySheetData {
    return {
        id: game.id,
        title: game.title,
        cover: game.cover,
        themes: getStoryDescriptorThemes(game.slug ?? String(game.id)),
        isComingSoon: !game.playable,
        teaser: game.teaser,
        slug: game.slug,
        cta: game.playable ? 'play' : 'coming-soon',
    };
}

function openSheet(game: FeaturedGame) {
    if (game.playable) return;
    sheetStory.value = toSheetData(game);
}

const failedCoverIds = ref<Set<string>>(new Set());

function showCover(game: FeaturedGame): boolean {
    return Boolean(game.cover) && !failedCoverIds.value.has(String(game.id));
}

function onCoverError(game: FeaturedGame): void {
    failedCoverIds.value = new Set([...failedCoverIds.value, String(game.id)]);
}

function storyHref(game: FeaturedGame): string | undefined {
    return game.playable && game.slug ? storyShow(game.slug).url : undefined;
}
</script>

<template>
    <section class="home-section-y overflow-visible">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Featured Worlds"
                    subtitle="Curated story worlds built for choice, consequence, and return."
                    :href="storiesIndex().url"
                />

                <div class="story-slider-viewport story-slider-viewport--new-stories relative overflow-visible">
                    <div class="story-slider-row">
                        <button type="button" class="story-slider-arrow" aria-label="Scroll left" @click="scrollSlider(-1)">
                            <svg
                                viewBox="0 0 8 14"
                                width="8"
                                height="14"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                aria-hidden="true"
                                class="rotate-180"
                            >
                                <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <div class="story-slider-wrap">
                            <div ref="sliderEl" class="story-slider overflow-x-auto">
                                <div class="story-slider-track">
                                    <component
                                        v-for="game in games"
                                        :key="String(game.id)"
                                        :is="game.playable ? Link : 'button'"
                                        :href="storyHref(game)"
                                        type="button"
                                        class="new-story-card flex h-[13.625rem] w-[min(18.75rem,78vw)] shrink-0 flex-col items-center justify-center gap-[0.375rem] rounded-lg border-[0.5px] border-[#373737] bg-[#0f0f0f] px-1 pt-1 pb-2 text-left outline-none transition-colors hover:border-primary/50 md:w-[18.75rem]"
                                        @click="!game.playable && openSheet(game)"
                                    >
                                        <span class="relative min-h-0 w-full flex-1 overflow-hidden rounded-[0.46875rem] bg-[#0c0c0c]">
                                            <img
                                                v-if="showCover(game)"
                                                :src="game.cover"
                                                :alt="game.title"
                                                class="absolute inset-0 size-full object-cover"
                                                decoding="async"
                                                @error="onCoverError(game)"
                                            />
                                            <span
                                                v-else
                                                class="new-story-cover-fallback absolute inset-0 flex flex-col items-center justify-center gap-2 px-3"
                                                aria-hidden="true"
                                            >
                                                <span class="new-story-cover-fallback__title">{{ game.title }}</span>
                                                <img :src="mainLogo" alt="" class="new-story-cover-fallback__logo" />
                                            </span>
                                        </span>
                                        <span class="flex w-full items-end justify-between gap-2 px-1">
                                            <span class="flex min-w-0 flex-1 flex-col gap-[0.1875rem]">
                                                <span class="truncate text-[0.8125rem] font-semibold text-white">{{ game.title }}</span>
                                                <span class="flex items-center gap-3 text-[0.75rem] text-[#7e7e7e]">
                                                    <span
                                                        v-for="theme in getStoryDescriptorThemes(game.slug ?? String(game.id))"
                                                        :key="theme"
                                                    >
                                                        {{ theme }}
                                                    </span>
                                                </span>
                                            </span>
                                            <span
                                                v-if="game.playable"
                                                class="flex h-6 w-[60px] shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full bg-[#0e1f22]"
                                            >
                                                <span class="font-['Inter',sans-serif] text-[12px] font-normal leading-none text-[#59c1ca]">Play</span>
                                                <svg
                                                    class="size-[0.5rem] shrink-0 text-[#59c1ca]"
                                                    viewBox="0 0 4 8"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        d="M0.75 0.75L3.25 4L0.75 7.25"
                                                        stroke="currentColor"
                                                        stroke-width="1.25"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </span>
                                            <span
                                                v-else
                                                class="relative flex h-6 w-[60px] shrink-0 items-center justify-center overflow-hidden rounded-full"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    class="pointer-events-none absolute inset-0 rounded-[inherit] bg-white/[0.04]"
                                                />
                                                <span
                                                    aria-hidden="true"
                                                    class="pointer-events-none absolute inset-0 rounded-[inherit] bg-[#1e1e1e] mix-blend-plus-lighter"
                                                />
                                                <span class="relative z-[1] font-['Inter',sans-serif] text-[12px] font-normal leading-none text-white">
                                                    Soon
                                                </span>
                                            </span>
                                        </span>
                                    </component>
                                </div>
                            </div>
                            <div
                                class="story-slider-edge-fade story-slider-edge-fade--left"
                                :class="{ 'is-visible': leftShadowVisible }"
                                aria-hidden="true"
                            />
                            <div
                                class="story-slider-edge-fade story-slider-edge-fade--right"
                                :class="{ 'is-visible': rightShadowVisible }"
                                aria-hidden="true"
                            />
                        </div>

                        <button type="button" class="story-slider-arrow" aria-label="Scroll right" @click="scrollSlider(1)">
                            <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <StoryDetailsSheet :story="sheetStory" @close="sheetStory = null" />
</template>

<style scoped>
.story-slider-viewport--new-stories {
    --story-slider-track-gap: 0.625rem;
    --story-slider-card-height: 13.625rem;
}

.new-story-cover-fallback {
    background: linear-gradient(165deg, #1a1a1f 0%, #0d0d10 45%, #141418 100%);
}

.new-story-cover-fallback__title {
    max-width: 100%;
    font-size: clamp(0.75rem, 2.4vw, 0.9375rem);
    font-weight: 700;
    line-height: 1.35;
    text-align: center;
    color: rgba(255, 255, 255, 0.88);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.new-story-cover-fallback__logo {
    width: auto;
    height: 0.875rem;
    opacity: 0.55;
    object-fit: contain;
}
</style>
