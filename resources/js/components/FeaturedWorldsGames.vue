<script setup lang="ts">
import sherlockCover from '@/assets/featured/sherlock.png';
import wizardozCover from '@/assets/featured/wizardoz.png';
import HomeBannerStoryCard from '@/components/HomeBannerStoryCard.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { ComingSoonStoryInterface } from '@/types';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
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
        title: 'The Adventure of the Speckled Band',
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

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 16;
    const step = card ? card.offsetWidth + gap : 232;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}

const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

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

                <div class="story-slider-viewport story-slider-viewport--banner relative overflow-visible">
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
                                    <StoryExpandableCard
                                        v-for="game in games"
                                        :key="String(game.id)"
                                        :expanded="isExpanded(String(game.id))"
                                        :dimmed="isDimmed(String(game.id))"
                                        :desktop-expand="isDesktopHover"
                                        :class="!game.playable && 'cursor-pointer'"
                                        @mouseenter="isDesktopHover && onCardEnter(String(game.id))"
                                        @mouseleave="isDesktopHover && onCardLeave()"
                                        @click="!game.playable && openSheet(game)"
                                    >
                                        <HomeBannerStoryCard
                                            :title="game.title"
                                            :cover="game.cover"
                                            :themes="getStoryDescriptorThemes(game.slug ?? String(game.id))"
                                            :teaser="game.teaser"
                                            category="World"
                                            rating=""
                                            :playable="game.playable"
                                            :slug="game.slug"
                                            :focused="isDesktopHover && isExpanded(String(game.id))"
                                            :is-desktop-hover="isDesktopHover"
                                            @preview="openSheet(game)"
                                        />
                                    </StoryExpandableCard>
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
