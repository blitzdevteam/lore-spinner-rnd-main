<script setup lang="ts">
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { findStoryBySlug, HOMEPAGE_NEW_STORIES } from '@/data/homepageFeaturedStories';
import { resolveNewStoryCover } from '@/data/newStoriesCoverBySlug';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import type { StoryInterface } from '@/types';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { computed, ref } from 'vue';

interface NewStoryCard {
    id: number;
    title: string;
    cover: string | null;
    category: string;
    rating: string;
    playable: boolean;
    slug: string;
    teaser: string;
}

const props = withDefaults(
    defineProps<{
        /** Published stories, newest first (homepage passes a recent slice). */
        stories?: StoryInterface[];
    }>(),
    {
        stories: () => [],
    },
);

const stories = computed<NewStoryCard[]>(() =>
    HOMEPAGE_NEW_STORIES.map((featured, index) => {
        const story = findStoryBySlug(props.stories, featured.slug);

        return {
            id: story?.id ?? index + 1,
            title: featured.title,
            cover: resolveNewStoryCover(featured.slug, story?.cover),
            category: story?.category?.title ?? 'Story',
            rating: story?.rating?.label ?? 'Everyone',
            playable: story ? isStoryPlayable(story) : false,
            slug: featured.slug,
            teaser: story?.teaser?.trim() || featured.teaser,
        };
    }),
);

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

function toSheetData(story: NewStoryCard): StorySheetData {
    return {
        id: story.id,
        title: story.title,
        cover: story.cover ?? '',
        themes: getStoryDescriptorThemes(story.slug),
        category: story.category,
        rating: story.rating,
        isComingSoon: !story.playable,
        teaser: story.teaser,
        slug: story.slug,
        cta: story.playable ? 'play' : 'coming-soon',
    };
}

function openSheet(story: NewStoryCard) {
    if (story.playable) return;
    sheetStory.value = toSheetData(story);
}
</script>

<template>
    <section v-if="stories.length > 0" class="home-section-y overflow-visible">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader title="New Stories" subtitle="New branches, hidden paths, and fresh story worlds." :href="storiesIndex().url" />

                <div class="story-slider-viewport story-slider-viewport--portrait relative overflow-visible">
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
                                        v-for="story in stories"
                                        :key="story.id"
                                        :expanded="isExpanded(String(story.id))"
                                        :dimmed="isDimmed(String(story.id))"
                                        :desktop-expand="isDesktopHover"
                                        :class="!story.playable && 'cursor-pointer'"
                                        @mouseenter="isDesktopHover && onCardEnter(String(story.id))"
                                        @mouseleave="isDesktopHover && onCardLeave()"
                                        @click="!story.playable && openSheet(story)"
                                    >
                                        <HomePortraitStoryCard
                                            :title="story.title"
                                            :cover="story.cover ?? ''"
                                            :themes="getStoryDescriptorThemes(story.slug)"
                                            :teaser="story.teaser"
                                            :playable="story.playable"
                                            :slug="story.slug"
                                            :focused="isDesktopHover && isExpanded(String(story.id))"
                                            :is-desktop-hover="isDesktopHover"
                                            @preview="openSheet(story)"
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

<style scoped>
/* Same portrait carousel sizing Featured Worlds used before the format swap. */
.story-slider-viewport--portrait {
    --story-portrait-card-width: min(11.475rem, 75vw);
}

@media (min-width: 768px) {
    .story-slider-viewport--portrait {
        --story-portrait-card-width: 11.475rem;
    }
}

@media (min-width: 1280px) {
    .story-slider-viewport--portrait {
        --story-portrait-card-width: 166px;
    }
}

@media (min-width: 1440px) {
    .story-slider-viewport--portrait {
        --story-portrait-card-width: 11.527778vw;
        --story-slider-track-gap: 1.111111vw;
    }
}

:deep(.hp-card__cta--active),
:deep(.hp-card__cta--disabled) {
    padding: 0.55rem 0.6875rem 0.55rem 0.55rem;
    border-radius: 0.34rem;
    font-size: 0.9rem;
}
</style>
