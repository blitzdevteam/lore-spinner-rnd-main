<script setup lang="ts">
import ComingSoonBanners from '@/components/ComingSoonBanners.vue';
import ContinueStories from '@/components/ContinueStories.vue';
import ExploreByMood from '@/components/ExploreByMood.vue';
import FeaturedWorldsGames from '@/components/FeaturedWorldsGames.vue';
import FrequentlyAskedQuestion from '@/components/FrequentlyAskedQuestion.vue';
import HeroBanner from '@/components/HeroBanner.vue';
import NewStoriesBanners from '@/components/NewStoriesBanners.vue';
import StepIntoWorlds from '@/components/StepIntoWorlds.vue';
import StoryChangesWithYou from '@/components/StoryChangesWithYou.vue';
import XenBanner from '@/components/XenBanner.vue';
import { filterVisibleLibraryStories } from '@/data/hiddenLibraryStorySlugs';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { ComingSoonStoryInterface, GameInterface, StoryInterface, WorldInterface } from '@/types';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        featuredStory?: StoryInterface | null;
        lastGame?: GameInterface | null;
        stories?: StoryInterface[];
        comingSoonStories?: ComingSoonStoryInterface[];
        worlds?: WorldInterface[];
        worldsCount?: number;
    }>(),
    {
        featuredStory: null,
        lastGame: null,
        stories: () => [],
        comingSoonStories: () => [],
        worlds: () => [],
        worldsCount: 0,
    },
);

const visibleStories = computed(() => filterVisibleLibraryStories(props.stories));
const heroComingSoonStories = computed(() => props.comingSoonStories.filter((story) => story.section === 'hero'));
const featuredWorldComingSoonStories = computed(() => props.comingSoonStories.filter((story) => story.section === 'featured-worlds'));
const newStoriesComingSoonStories = computed(() => props.comingSoonStories.filter((story) => story.section === 'new-stories'));
const comingSoonSectionStories = computed(() => props.comingSoonStories.filter((story) => story.section === 'coming-soon'));
</script>

<template>
    <HomeLayout>
        <!-- Hero -->
        <HeroBanner :stories="visibleStories" :coming-soon-stories="heroComingSoonStories" />

        <!-- Story Changes With You -->
        <StoryChangesWithYou />

        <!-- Continue Stories -->
        <ContinueStories v-if="lastGame" :game="lastGame" />

        <!-- Step into Worlds -->
        <StepIntoWorlds :worlds="worlds" :worlds-count="worldsCount" />

        <!-- Top Stories -->
        <FeaturedWorldsGames :coming-soon-stories="featuredWorldComingSoonStories" />

        <!-- Xen Banner -->
        <XenBanner />

        <!-- Explore by Mood -->
        <ExploreByMood />

        <!-- Featured Worlds -->
        <NewStoriesBanners :coming-soon-stories="newStoriesComingSoonStories" />

        <ComingSoonBanners :stories="comingSoonSectionStories" />

        <!-- FAQ: slightly tighter top padding after Coming Soon so spacing matches Figma (hover glow → heading). -->
        <div id="faq" class="pt-10 pb-14 md:pt-12 md:pb-[3.75rem]">
            <div class="container">
                <div class="container-content">
                    <FrequentlyAskedQuestion />
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
