<script setup lang="ts">
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import LibrarySortMenu, { type LibrarySortMode } from '@/components/LibrarySortMenu.vue';
import MoodHeroBanner from '@/components/MoodHeroBanner.vue';
import MoodSelector from '@/components/MoodSelector.vue';
import MoodTopPicks from '@/components/MoodTopPicks.vue';
import MultiplayerRoomSection from '@/components/MultiplayerRoomSection.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useHomeHeaderNav } from '@/composables/useHomeHeaderNav';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { filterVisibleLibraryStories } from '@/data/hiddenLibraryStorySlugs';
import { MOCK_LIBRARY_STORIES } from '@/data/mockLibraryStories';
import { getMoodBannerConfig, normalizeMood, storyMatchesMood } from '@/data/moodBanners';
import { dedupeStoriesByCanonicalSlug, getMoodSecondaryPickSlugs, selectStoriesByMoodSlugs } from '@/data/moodStories';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import HomeLayout from '@/layouts/HomeLayout.vue';
import type { MultiplayerLobbyCardInterface, StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface StoryGridGame {
    id: string;
    storyId: number;
    title: string;
    cover: string;
    playable: boolean;
    slug: string;
    teaser: string | null;
    category?: string;
    rating?: string;
}

const props = withDefaults(
    defineProps<{
        stories?: StoryInterface[];
        multiplayerRooms?: MultiplayerLobbyCardInterface[];
    }>(),
    {
        stories: () => [],
        multiplayerRooms: () => [],
    },
);

const { activeMood } = useHomeHeaderNav();
const activeLibraryTab = ref<'all' | 'single' | 'multiplayer'>('all');

function switchLibraryTab(tab: 'all' | 'single' | 'multiplayer'): void {
    activeLibraryTab.value = tab;
}

const normalizedMood = computed(() => normalizeMood(activeMood.value));

const moodHero = computed(() => getMoodBannerConfig(activeMood.value));

const pageTitle = computed(() => (normalizedMood.value ? moodHero.value.title : 'Library'));

/** API stories plus featured mock worlds; mocks skipped when the same slug already exists from the server. */
const allLibraryStories = computed((): StoryInterface[] => {
    const real = filterVisibleLibraryStories(props.stories ?? []).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));
    const realSlugs = new Set(real.map((s) => s.slug));
    const extra = MOCK_LIBRARY_STORIES.filter((m) => !realSlugs.has(m.slug)).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));
    return dedupeStoriesByCanonicalSlug([...real, ...extra]);
});

/** All stories assigned to the active mood (home portrait / landscape card art). */
const moodPageStories = computed((): StoryInterface[] => {
    const mood = normalizedMood.value;
    if (!mood) return [];

    return allLibraryStories.value.filter((story) => storyMatchesMood(story.slug, mood));
});

/** Mood page grid: secondary catalog order (top picks live in MoodTopPicks). */
const moodGridStories = computed((): StoryInterface[] => {
    const mood = normalizedMood.value;
    if (!mood) return [];

    return selectStoriesByMoodSlugs(moodPageStories.value, getMoodSecondaryPickSlugs(mood));
});

const libraryStories = computed((): StoryInterface[] => {
    const mood = normalizedMood.value;
    if (!mood) return allLibraryStories.value;

    return moodGridStories.value;
});

const isMoodPage = computed(() => normalizedMood.value !== null);

const listHeading = computed(() => {
    if (normalizedMood.value) {
        return `${libraryStories.value.length} Stories`;
    }
    return 'Stories';
});

const headerTitle = computed(() => {
    if (normalizedMood.value) {
        return listHeading.value;
    }
    return `${listHeading.value} (${libraryStories.value.length})`;
});

const sortMode = ref<LibrarySortMode>('recent');

const sortedStories = computed(() => {
    const list = [...libraryStories.value];
    if (sortMode.value === 'title_asc') {
        list.sort((a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }));
    } else if (sortMode.value === 'title_desc') {
        list.sort((a, b) => b.title.localeCompare(a.title, undefined, { sensitivity: 'base' }));
    } else if (!normalizedMood.value) {
        list.sort((a, b) => {
            const ta = a.updated_at ? new Date(a.updated_at).getTime() : 0;
            const tb = b.updated_at ? new Date(b.updated_at).getTime() : 0;
            return tb - ta;
        });
    }
    /* Mood pages: keep secondary catalog order when sort is Recent. */
    return list;
});

function isPlayableStory(story: StoryInterface): boolean {
    return story.status?.value === StoryStatusEnum.PUBLISHED && isStoryPlayable(story.slug);
}

const games = computed<StoryGridGame[]>(() =>
    sortedStories.value.map((story) => ({
        id: String(story.id),
        storyId: story.id,
        title: story.title,
        cover: story.cover,
        playable: isPlayableStory(story),
        slug: story.slug,
        teaser: story.teaser,
        category: story.category?.title,
        rating: story.rating?.label,
    })),
);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(game: StoryGridGame): StorySheetData {
    return {
        id: game.storyId,
        title: game.title,
        cover: game.cover,
        themes: getStoryDescriptorThemes(game.slug ?? game.id),
        category: game.category,
        rating: game.rating,
        isComingSoon: !game.playable,
        teaser: game.teaser,
        slug: game.slug,
        cta: game.playable ? 'play' : 'coming-soon',
    };
}

function openSheet(game: StoryGridGame): void {
    if (game.playable) return;
    sheetStory.value = toSheetData(game);
}
</script>

<template>
    <Head :title="pageTitle" />

    <HomeLayout>
        <MoodHeroBanner :mood="activeMood" />

        <div class="mx-auto flex w-full justify-center px-5 pt-6 sm:px-8 lg:px-16">
            <div class="flex items-center gap-2.5 rounded-[150px] bg-[#0f0f0f] p-[5px]">
                <button
                    v-for="tab in [
                        { key: 'all', label: 'All Stories' },
                        { key: 'single', label: 'Single Player' },
                        { key: 'multiplayer', label: 'Multiplayer' },
                    ]"
                    :key="tab.key"
                    type="button"
                    class="flex h-[35px] w-[122px] items-center justify-center rounded-[150px] px-2.5 py-2 text-[13px] transition-colors"
                    :class="activeLibraryTab === tab.key ? 'bg-[#232323] text-[#59c1ca]' : 'text-[#7e7e7e] hover:text-white'"
                    @click="switchLibraryTab(tab.key as 'all' | 'single' | 'multiplayer')"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <template v-if="activeLibraryTab !== 'multiplayer'">
            <template v-if="isMoodPage && normalizedMood">
                <div class="mood-page-flow">
                    <MoodSelector :active-mood="normalizedMood" />
                    <MoodTopPicks :mood="normalizedMood" :mood-label="moodHero.label" :stories="moodPageStories" />

                    <div id="all-stories" class="mood-page-stories-section overflow-visible pb-12 md:pb-[3.75rem]">
                        <div class="container">
                            <div class="container-content mood-page-section-header-gap">
                                <SectionHeader :title="headerTitle">
                                    <template #action>
                                        <LibrarySortMenu v-model="sortMode" />
                                    </template>
                                </SectionHeader>

                                <div class="story-card-grid grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                    <div v-for="game in games" :key="game.id" class="col-span-1 min-w-0">
                                        <StoryExpandableCard
                                            :expanded="isExpanded(game.id)"
                                            :dimmed="isDimmed(game.id)"
                                            :desktop-expand="isDesktopHover"
                                            :class="!game.playable && 'cursor-pointer'"
                                            @mouseenter="isDesktopHover && onCardEnter(game.id)"
                                            @mouseleave="isDesktopHover && onCardLeave()"
                                            @click="!game.playable && openSheet(game)"
                                        >
                                            <HomePortraitStoryCard
                                                :title="game.title"
                                                :cover="game.cover"
                                                :themes="getStoryDescriptorThemes(game.slug ?? game.id)"
                                                :teaser="game.teaser"
                                                :playable="game.playable"
                                                :slug="game.slug"
                                                :focused="isDesktopHover && isExpanded(game.id)"
                                                :is-desktop-hover="isDesktopHover"
                                                @preview="openSheet(game)"
                                            />
                                        </StoryExpandableCard>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-else id="all-stories" class="relative z-10 pt-6 pb-12 md:pt-[3.75rem] md:pb-[3.75rem]">
                <div class="container">
                    <div class="container-content flex min-w-0 flex-col gap-5 md:gap-[1.125rem]">
                        <SectionHeader :title="headerTitle">
                            <template #action>
                                <LibrarySortMenu v-model="sortMode" />
                            </template>
                        </SectionHeader>

                        <div class="story-card-grid grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                            <div v-for="game in games" :key="game.id" class="col-span-1 min-w-0">
                                <StoryExpandableCard
                                    :expanded="isExpanded(game.id)"
                                    :dimmed="isDimmed(game.id)"
                                    :desktop-expand="isDesktopHover"
                                    :class="!game.playable && 'cursor-pointer'"
                                    @mouseenter="isDesktopHover && onCardEnter(game.id)"
                                    @mouseleave="isDesktopHover && onCardLeave()"
                                    @click="!game.playable && openSheet(game)"
                                >
                                    <HomePortraitStoryCard
                                        :title="game.title"
                                        :cover="game.cover"
                                        :themes="getStoryDescriptorThemes(game.slug ?? game.id)"
                                        :teaser="game.teaser"
                                        :playable="game.playable"
                                        :slug="game.slug"
                                        :focused="isDesktopHover && isExpanded(game.id)"
                                        :is-desktop-hover="isDesktopHover"
                                        @preview="openSheet(game)"
                                    />
                                </StoryExpandableCard>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <MultiplayerRoomSection v-if="activeLibraryTab === 'all' || activeLibraryTab === 'multiplayer'" :rooms="props.multiplayerRooms" />

        <StoryDetailsSheet :story="sheetStory" @close="sheetStory = null" />
    </HomeLayout>
</template>

<style scoped>
.mood-page-sections {
    gap: 3rem;
}

@media (min-width: 768px) {
    .mood-page-sections {
        gap: 4rem;
    }
}

@media (min-width: 1024px) {
    .mood-page-sections {
        gap: 4.5rem;
    }
}

.story-card-grid {
    --story-portrait-card-width: 100%;
}

:deep(.story-card-grid .hp-card),
:deep(.story-card-grid .hp-card__frame) {
    width: 100%;
}
</style>
