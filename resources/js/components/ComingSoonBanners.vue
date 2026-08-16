<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { ComingSoonStoryInterface } from '@/types';
import { computed, ref } from 'vue';

interface ComingSoonCard {
    id: string | number;
    title: string;
    cover: string;
    teaser: string | null;
    slug?: string;
    category?: string;
    rating?: string;
}

const props = withDefaults(
    defineProps<{
        stories?: ComingSoonStoryInterface[];
    }>(),
    {
        stories: () => [],
    },
);

const cards = computed<ComingSoonCard[]>(() =>
    props.stories.map((story) => ({
        id: story.id,
        title: story.title,
        cover: story.cover,
        teaser: story.teaser,
        slug: story.slug ?? undefined,
        category: story.category ?? undefined,
        rating: story.rating ?? undefined,
    })),
);

const sliderEl = ref<HTMLElement | null>(null);

function scrollSlider(direction: -1 | 1) {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 16;
    const step = card ? card.offsetWidth + gap : 214;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}

const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(card: ComingSoonCard): StorySheetData {
    return {
        id: card.id,
        title: card.title,
        cover: card.cover,
        themes: getStoryDescriptorThemes(card.slug ?? String(card.id)),
        category: card.category,
        rating: card.rating,
        isComingSoon: true,
        teaser: card.teaser,
    };
}

function openSheet(card: ComingSoonCard) {
    sheetStory.value = toSheetData(card);
}
</script>

<template>
    <section v-if="cards.length > 0" class="home-section-y overflow-visible !pb-2 md:!pb-2">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Coming Soon"
                    subtitle="New worlds are coming soon."
                    :href="storiesIndex().url"
                />

                <div class="story-slider-viewport story-slider-viewport--portrait coming-soon-slider-viewport relative overflow-visible">
                    <div class="story-slider-row">
                        <button
                            type="button"
                            class="story-slider-arrow"
                            aria-label="Scroll left"
                            @click="scrollSlider(-1)"
                        >
                            <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                                <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <div class="story-slider-wrap">
                            <div ref="sliderEl" class="story-slider overflow-x-auto">
                                <div class="story-slider-track">
                                    <StoryExpandableCard
                                        v-for="card in cards"
                                        :key="String(card.id)"
                                        :expanded="isExpanded(String(card.id))"
                                        :dimmed="isDimmed(String(card.id))"
                                        :desktop-expand="isDesktopHover"
                                        class="cursor-pointer"
                                        @mouseenter="isDesktopHover && onCardEnter(String(card.id))"
                                        @mouseleave="isDesktopHover && onCardLeave()"
                                        @click="openSheet(card)"
                                    >
                                        <HomePortraitStoryCard
                                            :title="card.title"
                                            :cover="card.cover"
                                            :themes="getStoryDescriptorThemes(card.slug ?? String(card.id))"
                                            :teaser="card.teaser"
                                            :playable="false"
                                            :show-cta="false"
                                            :focused="isDesktopHover && isExpanded(String(card.id))"
                                            :is-desktop-hover="isDesktopHover"
                                            @preview="openSheet(card)"
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

                        <button
                            type="button"
                            class="story-slider-arrow"
                            aria-label="Scroll right"
                            @click="scrollSlider(1)"
                        >
                            <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
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
/* Match the Featured Worlds portrait card size on responsive. */
.coming-soon-slider-viewport {
    --story-portrait-card-width: min(11.475rem, 75vw);
}

@media (min-width: 768px) {
    .coming-soon-slider-viewport {
        --story-portrait-card-width: 11.475rem;
    }
}

@media (min-width: 1280px) {
    .coming-soon-slider-viewport {
        --story-portrait-card-width: 166px;
    }
}
</style>
