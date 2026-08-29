<script setup lang="ts">
import ComingSoonBanners from '@/components/ComingSoonBanners.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import WorldAvailableStoryCard from '@/components/WorldAvailableStoryCard.vue';
import WorldCard from '@/components/WorldCard.vue';
import WorldsSearchField from '@/components/WorldsSearchField.vue';
import WorldShowHero from '@/components/WorldShowHero.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import HomeLayout from '@/layouts/HomeLayout.vue';
import type { ComingSoonStoryInterface, WorldInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { index as worldsIndex } from '@/wayfinder/routes/worlds';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        world: WorldInterface;
        otherWorlds?: WorldInterface[];
        worldsCount?: number;
        comingSoonStories?: ComingSoonStoryInterface[];
    }>(),
    {
        otherWorlds: () => [],
        worldsCount: 0,
        comingSoonStories: () => [],
    },
);

const searchQuery = ref('');
const stories = computed(() => props.world.stories ?? []);
const visibleStories = computed(() => {
    const needle = searchQuery.value.trim().toLowerCase();

    if (needle === '') {
        return stories.value;
    }

    return stories.value.filter((story) => story.title.toLowerCase().includes(needle));
});
const availableTitle = computed(() => `Available stories ( ${visibleStories.value.length} )`);
const otherWorldsCount = computed(() => props.worldsCount || props.otherWorlds.length);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded } = useStoryCardExpand(isDesktopHover);

function worldCardId(id: number): string {
    return String(id);
}

function isPlayable(slug: string): boolean {
    const story = stories.value.find((item) => item.slug === slug);

    return Boolean(story?.status?.value === StoryStatusEnum.PUBLISHED && isStoryPlayable(slug));
}
</script>

<template>
    <HomeLayout>
        <Head :title="world.title" />

        <main class="bg-black pb-12 text-white md:pb-[3.75rem]">
            <div class="container pt-6 md:pt-10">
                <div class="container-content flex min-w-0 flex-col gap-10">
                    <WorldsSearchField v-model="searchQuery" />

                    <WorldShowHero :world="world" />

                    <section class="flex min-w-0 flex-col gap-5 md:gap-[1.125rem]">
                        <SectionHeader :title="availableTitle" />

                        <div v-if="visibleStories.length" class="flex gap-2.5 overflow-x-auto pb-1">
                            <WorldAvailableStoryCard
                                v-for="story in visibleStories"
                                :key="story.id"
                                :story="story"
                                :playable="isPlayable(story.slug)"
                            />
                        </div>
                        <p v-else-if="searchQuery.trim()" class="text-[#8f8f8f]">No stories match your search.</p>
                        <p v-else class="text-[#8f8f8f]">This world has no published stories yet.</p>
                    </section>
                </div>
            </div>

            <ComingSoonBanners :stories="comingSoonStories" :show-view-all="false" subtitle="" />

            <section v-if="otherWorlds.length" class="home-section-y overflow-visible">
                <div class="container">
                    <div class="container-content flex min-w-0 flex-col gap-5 md:gap-[1.125rem]">
                        <SectionHeader title="Explore other worlds" :href="worldsIndex().url" :count="otherWorldsCount" />

                        <div class="grid grid-cols-1 items-start gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="otherWorld in otherWorlds"
                                :key="otherWorld.id"
                                class="min-w-0"
                                @mouseenter="onCardEnter(worldCardId(otherWorld.id))"
                                @mouseleave="onCardLeave()"
                            >
                                <WorldCard :world="otherWorld" :expanded="isExpanded(worldCardId(otherWorld.id))" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </HomeLayout>
</template>
