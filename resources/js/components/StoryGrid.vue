<script setup lang="ts">
import StoryCard, { type StoryCardCta } from '@/components/StoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
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
    return !isStoryPlayable(story);
}

const isDesktopHover = useDesktopStoryPreview();
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

</script>

<template>
    <div class="story-grid">
        <StoryCard
            v-for="story in stories"
            :key="story.id"
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
    </div>

    <StoryDetailsSheet v-if="!isDesktopHover" :story="sheetStory" @close="sheetStory = null" />
</template>

<style scoped>
.story-grid {
    display: grid;
    width: 100%;
    min-width: 0;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    align-items: start;
}

@media (min-width: 768px) {
    .story-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }
}

@media (min-width: 1024px) {
    .story-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (min-width: 1280px) {
    .story-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 1.125rem;
    }
}

@media (min-width: 1536px) {
    .story-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 1.25rem;
    }
}
</style>
