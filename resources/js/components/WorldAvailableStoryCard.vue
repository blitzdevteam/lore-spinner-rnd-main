<script setup lang="ts">
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { resolveStoryTopMoodCover } from '@/data/storyTopMoodCoverBySlug';
import type { StoryInterface } from '@/types';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    story: StoryInterface;
    playable?: boolean;
}>();

const href = computed(() => (props.playable ? storyShow(props.story.slug).url : undefined));
const themes = computed(() => {
    const descriptors = getStoryDescriptorThemes(props.story.slug);

    if (descriptors.length) {
        return descriptors.slice(0, 3);
    }

    return props.story.category?.title ? [props.story.category.title] : [];
});
const cover = computed(() => resolveStoryTopMoodCover(props.story.slug, props.story.cover));
const imageFailed = ref(false);

watch(cover, () => {
    imageFailed.value = false;
});

const showImage = computed(() => Boolean(cover.value) && !imageFailed.value);
</script>

<template>
    <component
        :is="href ? Link : 'article'"
        :href="href"
        class="new-story-card flex h-[13.625rem] w-[min(18.75rem,78vw)] shrink-0 flex-col items-center justify-center gap-[0.375rem] rounded-lg border-[0.5px] border-[#373737] bg-[#0f0f0f] px-1 pt-1 pb-2 text-left no-underline outline-none transition-colors hover:border-primary/50 md:w-[18.75rem]"
    >
        <span class="relative min-h-0 w-full flex-1 overflow-hidden rounded-[0.46875rem]">
            <img
                v-if="showImage"
                :src="cover"
                :alt="story.title"
                class="absolute inset-0 size-full object-cover"
                @error="imageFailed = true"
            />
            <span v-else class="flex size-full items-end bg-[#1a1a1a] p-3 text-xs text-[#8f8f8f]">
                {{ story.title }}
            </span>
        </span>
        <span class="flex w-full items-end justify-between gap-2 px-1">
            <span class="flex min-w-0 flex-1 flex-col gap-[0.1875rem]">
                <span class="truncate text-[0.8125rem] font-semibold text-white">{{ story.title }}</span>
                <span v-if="themes.length" class="flex items-center gap-3 text-[0.75rem] text-[#7e7e7e]">
                    <span v-for="theme in themes" :key="theme">{{ theme }}</span>
                </span>
            </span>
            <span
                v-if="playable"
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
                <span aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-[inherit] bg-white/[0.04]" />
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
</template>
