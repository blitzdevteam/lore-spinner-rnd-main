<script setup lang="ts">
import StoryCard, { type StoryCardCta } from '@/components/StoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        stories: StoryInterface[];
        ctaByStoryId?: Record<number, StoryCardCta>;
        /** Mood label for card data attribute (e.g. analytics / styling). */
        moodLabel?: string;
    }>(),
    {
        ctaByStoryId: () => ({}),
        moodLabel: undefined,
    },
);

function ctaForStory(story: StoryInterface): StoryCardCta | undefined {
    return props.ctaByStoryId[story.id];
}

function isComingSoon(story: StoryInterface): boolean {
    if (story.status?.value !== StoryStatusEnum.PUBLISHED) return true;
    return !isStoryPlayable(story.slug);
}

const sliderEl = ref<HTMLElement | null>(null);
const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(story: StoryInterface): StorySheetData {
    const comingSoon = isComingSoon(story);
    const cta = ctaForStory(story);

    return {
        id: story.id,
        title: story.title,
        cover: story.cover ?? '',
        themes: getStoryDescriptorThemes(story.slug),
        category: story.category?.title,
        rating: story.rating?.label,
        isComingSoon: comingSoon,
        teaser: story.teaser,
        slug: story.slug,
        cta: cta ?? (comingSoon ? 'coming-soon' : 'play'),
    };
}

function openSheet(story: StoryInterface): void {
    sheetStory.value = toSheetData(story);
}

function scrollSlider(direction: -1 | 1): void {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 16;
    const step = card ? card.offsetWidth + gap : 232;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}
</script>

<template>
    <div class="story-slider-viewport story-slider-viewport--portrait relative overflow-visible">
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
                            v-for="story in stories"
                            :key="story.id"
                            :expanded="isExpanded(String(story.id))"
                            :dimmed="isDimmed(String(story.id))"
                            :desktop-expand="isDesktopHover"
                            @mouseenter="isDesktopHover && onCardEnter(String(story.id))"
                            @mouseleave="isDesktopHover && onCardLeave()"
                        >
                            <StoryCard
                                :title="story.title"
                                :cover-image="story.cover"
                                :slug="story.slug"
                                :status="story.status?.value"
                                :mood="moodLabel"
                                :is-coming-soon="isComingSoon(story)"
                                :cta="ctaForStory(story)"
                                :teaser="story.teaser"
                                :themes="getStoryDescriptorThemes(story.slug)"
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

    <StoryDetailsSheet v-if="!isDesktopHover" :story="sheetStory" @close="sheetStory = null" />
</template>
