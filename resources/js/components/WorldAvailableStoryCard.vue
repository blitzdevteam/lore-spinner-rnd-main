<script setup lang="ts">
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
const teaser = computed(() => props.story.teaser?.trim() ?? '');
const ctaLabel = computed(() => (props.playable ? 'Play' : 'Coming Soon'));
</script>

<template>
    <component
        :is="href ? Link : 'article'"
        :href="href"
        class="world-available-story flex w-[18.75rem] shrink-0 flex-col items-center gap-1.5 rounded-lg border-[0.5px] border-[#373737] bg-[#0f0f0f] px-1 pt-1 pb-2 no-underline"
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

        <div class="flex w-[17.75rem] flex-col gap-2">
            <div class="flex min-w-0 flex-col gap-[3px]">
                <p class="truncate text-[0.8125rem] font-semibold text-white">{{ story.title }}</p>
                <p v-if="themes.length" class="flex gap-x-3 text-xs text-[#7e7e7e]">
                    <span v-for="theme in themes" :key="theme">{{ theme }}</span>
                </p>
                <p v-if="teaser" class="world-available-story__teaser">{{ teaser }}</p>
            </div>

            <span
                class="world-available-story__cta"
                :class="playable ? 'world-available-story__cta--play' : 'world-available-story__cta--soon'"
            >
                {{ ctaLabel }}
            </span>
        </div>
    </component>
</template>

<style scoped>
.world-available-story__teaser {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.52);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
}

.world-available-story__cta {
    display: flex;
    box-sizing: border-box;
    height: 2.125rem;
    width: 100%;
    align-items: center;
    justify-content: center;
    border-radius: 0.3125rem;
    font-size: 0.9rem;
    line-height: 1;
}

.world-available-story__cta--play {
    border: 0.5px solid #59c1ca;
    background: #0e1f22;
    color: #fff;
}

.world-available-story__cta--soon {
    border: 0.5px solid #8f8f8f;
    background: transparent;
    color: #8f8f8f;
}
</style>
