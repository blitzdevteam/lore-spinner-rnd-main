<script setup lang="ts">
import storyPlay from '@/assets/worlds/story-play.svg';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { StoryInterface } from '@/types';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    story: StoryInterface;
    playable?: boolean;
}>();

const href = computed(() => (props.playable ? storyShow(props.story.slug).url : undefined));
const themes = computed(() => getStoryDescriptorThemes(props.story.slug).slice(0, 3));
const cover = computed(() => resolveStoryCover(props.story.slug, props.story.cover));
</script>

<template>
    <component
        :is="href ? Link : 'article'"
        :href="href"
        class="world-available-story flex h-[13.625rem] w-[18.75rem] shrink-0 flex-col items-center gap-1.5 rounded-lg border-[0.5px] border-[#373737] bg-[#0f0f0f] px-1 pt-1 pb-2 no-underline"
    >
        <div class="h-[10.375rem] w-[18.25rem] overflow-hidden rounded-[5px]">
            <img
                v-if="cover"
                :src="cover"
                :alt="story.title"
                class="size-full object-cover"
            />
            <div v-else class="flex size-full items-end bg-[#1a1a1a] p-3 text-xs text-[#8f8f8f]">
                {{ story.title }}
            </div>
        </div>

        <div class="flex w-[17.75rem] items-end justify-between">
            <div class="flex min-w-0 flex-col gap-[3px]">
                <p class="truncate text-[0.8125rem] font-semibold text-white">{{ story.title }}</p>
                <p v-if="themes.length" class="flex gap-x-3 text-xs text-[#7e7e7e]">
                    <span v-for="theme in themes" :key="theme">{{ theme }}</span>
                </p>
            </div>
            <span class="flex size-[19px] shrink-0 items-center justify-center overflow-clip" aria-hidden="true">
                <img :src="storyPlay" alt="" width="19" height="19" class="size-full" />
            </span>
        </div>
    </component>
</template>
