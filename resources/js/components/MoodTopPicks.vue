<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import { MOOD_TOP_PICK_SLUGS } from '@/data/moodStories';
import { canonicalMoodStorySlug } from '@/data/moodStories';
import type { MoodId } from '@/data/moodBanners';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { resolveStoryTopMoodCover } from '@/data/storyTopMoodCoverBySlug';
import { STORY_HOVER_META_BY_SLUG } from '@/data/storyCardHoverMeta';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    mood: MoodId;
    moodLabel: string;
    stories: StoryInterface[];
}>();

const sliderEl = ref<HTMLElement | null>(null);
const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const sectionTitle = computed(() => `Top ${props.moodLabel} Picks`);

const topPickStories = computed((): StoryInterface[] => {
    const bySlug = new Map(props.stories.map((story) => [story.slug, story]));
    return MOOD_TOP_PICK_SLUGS[props.mood]
        .map((slug) => {
            const direct = bySlug.get(slug);
            if (direct) return direct;
            for (const story of props.stories) {
                if (canonicalMoodStorySlug(story.slug) === slug) return story;
            }
            return undefined;
        })
        .filter((story): story is StoryInterface => story != null);
});

function themesForStory(story: StoryInterface): string[] {
    const meta = STORY_HOVER_META_BY_SLUG[story.slug];
    if (meta?.themes.length) return meta.themes;
    if (story.category?.title) return [story.category.title];
    return [];
}

function categoryForStory(story: StoryInterface): string {
    return story.category?.title ?? 'Story';
}

function ratingForStory(story: StoryInterface): string {
    return story.rating?.label ?? 'Everyone';
}

function isPlayable(story: StoryInterface): boolean {
    return story.status?.value === StoryStatusEnum.PUBLISHED && isStoryPlayable(story);
}

function coverForStory(story: StoryInterface): string {
    return resolveStoryTopMoodCover(story.slug, story.cover);
}

function scrollSlider(direction: -1 | 1): void {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.new-story-card');
    const gap = 10;
    const step = card ? card.offsetWidth + gap : 310;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(story: StoryInterface): StorySheetData {
    return {
        id: story.id,
        title: story.title,
        cover: coverForStory(story),
        themes: themesForStory(story),
        category: categoryForStory(story),
        rating: ratingForStory(story),
        isComingSoon: !isPlayable(story),
        teaser: story.teaser,
        slug: story.slug,
        cta: isPlayable(story) ? 'play' : 'coming-soon',
    };
}

function openSheet(story: StoryInterface): void {
    if (isPlayable(story)) return;
    sheetStory.value = toSheetData(story);
}
</script>

<template>
    <section class="mood-top-picks" :aria-label="sectionTitle">
        <div class="container">
            <div class="container-content mood-page-section-header-gap">
                <SectionHeader :title="sectionTitle" />

                <div class="story-slider-viewport story-slider-viewport--mood-picks relative overflow-visible">
                    <div class="story-slider-row">
                        <button
                            type="button"
                            class="story-slider-arrow"
                            aria-label="Scroll top picks left"
                            @click="scrollSlider(-1)"
                        >
                            <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                                <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <div class="story-slider-wrap">
                            <div ref="sliderEl" class="story-slider overflow-x-auto">
                                <div class="story-slider-track">
                                    <component
                                        v-for="story in topPickStories"
                                        :key="story.id"
                                        :is="isPlayable(story) && story.slug ? Link : 'button'"
                                        :href="isPlayable(story) && story.slug ? storyShow(story.slug).url : undefined"
                                        type="button"
                                        class="new-story-card flex h-[13.625rem] w-[min(18.75rem,78vw)] shrink-0 flex-col items-center justify-center gap-[0.375rem] rounded-lg border-[0.5px] border-[#373737] bg-[#0f0f0f] px-1 pt-1 pb-2 text-left outline-none transition-colors hover:border-primary/50 md:w-[18.75rem]"
                                        @click="!isPlayable(story) && openSheet(story)"
                                    >
                                        <span class="relative min-h-0 w-full flex-1 overflow-hidden rounded-[0.46875rem]">
                                            <img
                                                :src="coverForStory(story)"
                                                :alt="story.title"
                                                class="absolute inset-0 size-full object-cover"
                                            />
                                        </span>
                                        <span class="flex w-full items-end justify-between gap-2 px-1">
                                            <span class="flex min-w-0 flex-1 flex-col gap-[0.1875rem]">
                                                <span class="truncate text-[0.8125rem] font-semibold text-white">{{ story.title }}</span>
                                                <span class="flex items-center gap-3 text-[0.75rem] text-[#7e7e7e]">
                                                    <span v-for="theme in themesForStory(story)" :key="theme">
                                                        {{ theme }}
                                                    </span>
                                                </span>
                                            </span>
                                            <span
                                                v-if="isPlayable(story)"
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
                                                    <path d="M0.75 0.75L3.25 4L0.75 7.25" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" />
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

                        <button
                            type="button"
                            class="story-slider-arrow"
                            aria-label="Scroll top picks right"
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
/* Card is a fixed-size photo tile (NewStoriesBanners design), not the JS-measured
   `.story-card-slot` hover card, so pin the slider's height/gap vars directly —
   `!important` needed to beat `.mood-page-flow .story-slider-viewport` in mood-page.css. */
.story-slider-viewport--mood-picks {
    --story-slider-track-gap: 0.625rem !important;
    --story-slider-card-height: 13.625rem !important;
}
</style>
