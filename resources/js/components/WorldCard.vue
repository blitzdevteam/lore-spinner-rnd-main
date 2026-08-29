<script setup lang="ts">
import type { WorldInterface } from '@/types';
import { show as worldShow } from '@/wayfinder/routes/worlds';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    world: WorldInterface;
    expanded?: boolean;
}>();

const stories = computed(() => props.world.stories ?? []);
const storyCount = computed(() => props.world.stories_count ?? stories.value.length);
const storyCountLabel = computed(() => (storyCount.value === 1 ? '1 Story' : `${storyCount.value} Stories`));
const tags = computed(() => (props.world.tags ?? []).slice(0, 3));
const previewStories = computed(() => stories.value.slice(0, 2));
const avatarStories = computed(() => stories.value.slice(0, 3));
const href = computed(() => worldShow(props.world.slug).url);
const brokenCovers = ref(new Set<number>());

function markCoverBroken(storyId: number): void {
    brokenCovers.value = new Set(brokenCovers.value).add(storyId);
}

function hasCover(story: { id: number; cover?: string | null }): boolean {
    return Boolean(story.cover) && !brokenCovers.value.has(story.id);
}
</script>

<template>
    <article
        class="world-card relative flex h-[18.125rem] w-full shrink-0 flex-col overflow-hidden rounded-xl border-[0.5px] px-[17px] py-3"
        :class="expanded ? 'world-card--expanded border-primary' : 'border-[#191919]'"
    >
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <img
                v-if="world.background"
                :src="world.background"
                :alt="world.title"
                class="absolute inset-0 size-full object-cover"
            />
            <div class="world-card__shade world-card__shade--rest absolute inset-0" />
            <div class="world-card__shade world-card__shade--hover absolute inset-0" />
        </div>

        <div class="relative z-[1] flex h-[16.625rem] w-full flex-col" :class="expanded ? 'justify-start' : 'justify-end'">
            <div v-if="expanded" class="flex w-full flex-col gap-5">
                <div class="flex w-full flex-col gap-2.5">
                    <div class="flex w-full flex-col gap-2.5">
                        <div class="flex h-[4.625rem] w-full flex-col justify-center">
                            <div class="flex flex-col gap-1.5">
                                <div class="flex flex-col gap-1">
                                    <h3 class="font-inria-serif text-[1.125rem] leading-normal font-bold text-white">
                                        {{ world.title }}
                                    </h3>
                                    <p class="text-[0.8125rem] leading-normal font-light text-white">
                                        {{ world.tagline }}
                                    </p>
                                </div>
                                <p v-if="tags.length" class="flex flex-wrap gap-x-3 text-[0.75rem] leading-normal font-light text-[#d8d8d8]">
                                    <span v-for="tag in tags" :key="tag">{{ tag }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="h-0 w-full border-t border-white/20" />
                    </div>

                    <div v-if="previewStories.length" class="flex h-[7.625rem] w-full items-center overflow-hidden">
                        <div class="flex items-center gap-2.5">
                            <div
                                v-for="story in previewStories"
                                :key="story.id"
                                class="flex shrink-0 items-center overflow-hidden rounded border-[0.25px] border-[#373737] bg-[#0f0f0f] p-[2.5px]"
                            >
                                <img
                                    v-if="hasCover(story)"
                                    :src="story.cover"
                                    :alt="story.title"
                                    class="h-[7.3125rem] w-[4.875rem] rounded-[2.5px] object-cover"
                                    @error="markCoverBroken(story.id)"
                                />
                                <span
                                    v-else
                                    class="flex h-[7.3125rem] w-[4.875rem] items-end overflow-hidden rounded-[2.5px] bg-[#1a1a1a] p-1.5 text-[0.625rem] leading-tight text-[#8f8f8f]"
                                >
                                    {{ story.title }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <Link
                    :href="href"
                    class="flex h-[1.875rem] w-full items-center justify-center rounded-[5px] border-[0.5px] border-primary bg-primary text-[0.8125rem] font-medium text-black"
                >
                    Enter world
                </Link>
            </div>

            <div v-else class="flex w-full flex-col justify-end gap-5">
                <div class="flex w-full flex-col gap-2.5">
                    <div class="flex h-[4.625rem] w-full flex-col justify-center">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex flex-col gap-1">
                                <h3 class="font-inria-serif text-[1.125rem] leading-normal font-bold text-white">
                                    {{ world.title }}
                                </h3>
                                <p class="text-[0.8125rem] leading-normal font-light text-white">
                                    {{ world.tagline }}
                                </p>
                            </div>
                            <p v-if="tags.length" class="flex flex-wrap gap-x-3 text-[0.75rem] leading-normal font-light text-[#d8d8d8]">
                                <span v-for="tag in tags" :key="tag">{{ tag }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="h-0 w-full border-t border-white/20" />
                </div>

                <div class="flex h-10 items-center justify-between gap-3">
                    <div class="flex items-center gap-1">
                        <div v-if="avatarStories.length" class="flex items-center pr-2">
                            <span
                                v-for="(story, index) in avatarStories"
                                :key="story.id"
                                class="relative size-[2.34375rem] overflow-hidden rounded-full border border-black/40"
                                :class="index > 0 ? '-ml-4' : ''"
                                :style="{ zIndex: avatarStories.length - index }"
                            >
                                <img
                                    v-if="hasCover(story)"
                                    :src="story.cover"
                                    :alt="story.title"
                                    class="size-full object-cover opacity-85"
                                    @error="markCoverBroken(story.id)"
                                />
                                <span v-else class="block size-full bg-[#2a2a2a]" />
                            </span>
                        </div>
                        <p class="text-[0.75rem] leading-[1.125rem] font-light text-[#8f8f8f]">
                            {{ storyCountLabel }}
                        </p>
                    </div>

                    <Link
                        :href="href"
                        class="flex h-[1.875rem] w-[6.875rem] shrink-0 items-center justify-center rounded-[5px] border-[0.5px] border-primary bg-[#0e1f22] text-[0.8125rem] font-normal text-white"
                    >
                        Enter World
                    </Link>
                </div>
            </div>
        </div>
    </article>
</template>

<style scoped>
.world-card {
    z-index: 1;
    box-sizing: border-box;
    transition: border-color 200ms ease;
}

.world-card--expanded {
    z-index: 4;
}

.world-card__shade {
    transition: opacity 220ms ease;
}

.world-card__shade--rest {
    background-image:
        linear-gradient(to top, rgb(0, 0, 0) 14.31%, rgba(0, 0, 0, 0) 68.276%),
        linear-gradient(90deg, rgba(0, 0, 0, 0.05), rgba(0, 0, 0, 0.05));
}

.world-card__shade--hover {
    opacity: 0;
    background-image:
        linear-gradient(to top, rgb(0, 0, 0) 11.552%, rgba(0, 0, 0, 0) 100%),
        linear-gradient(90deg, rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4));
}

.world-card--expanded .world-card__shade--rest {
    opacity: 0;
}

.world-card--expanded .world-card__shade--hover {
    opacity: 1;
}
</style>
