<script setup lang="ts">
import StoryCard from '@/components/StoryCard.vue';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';

const props = withDefaults(
    defineProps<{
        story: StoryInterface;
        showTitle?: boolean;
        showButton?: boolean;
        dimmed?: boolean;
    }>(),
    {
        showTitle: true,
        showButton: true,
        dimmed: false,
    },
);

const isComingSoon =
    props.story.status?.value !== StoryStatusEnum.PUBLISHED || !isStoryPlayable(props.story);
</script>

<template>
    <StoryCard
        :title="story.title"
        :cover-image="story.cover"
        :slug="story.slug"
        :status="story.status?.value"
        :genre="story.category?.title"
        :is-coming-soon="isComingSoon"
        :teaser="story.teaser"
        :dimmed="dimmed"
    />
</template>
