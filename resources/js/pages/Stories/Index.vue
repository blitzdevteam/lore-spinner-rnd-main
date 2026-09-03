<script setup lang="ts">
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import LibraryMultiplayerCard from '@/components/LibraryMultiplayerCard.vue';
import LibrarySortMenu, { type LibrarySortMode } from '@/components/LibrarySortMenu.vue';
import MoodHeroBanner from '@/components/MoodHeroBanner.vue';
import MoodSelector from '@/components/MoodSelector.vue';
import MoodTopPicks from '@/components/MoodTopPicks.vue';
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
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Head, router } from '@inertiajs/vue3';
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

type LibraryTab = 'all' | 'single' | 'multiplayer';

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

function tabFromUrl(): LibraryTab {
    if (typeof window === 'undefined') {
        return 'all';
    }

    const tab = new URLSearchParams(window.location.search).get('tab');
    if (tab === 'single' || tab === 'multiplayer') {
        return tab;
    }

    return 'all';
}

const activeLibraryTab = ref<LibraryTab>(tabFromUrl());

function switchLibraryTab(tab: LibraryTab): void {
    activeLibraryTab.value = tab;

    router.get(
        storiesIndex.url({
            query: {
                mood: activeMood.value || null,
                tab: tab === 'all' ? null : tab,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

const showSinglePlayer = computed(() => activeLibraryTab.value !== 'multiplayer');
const showMultiplayer = computed(() => activeLibraryTab.value !== 'single');

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
const showMoodTopPicks = computed(() => isMoodPage.value && activeLibraryTab.value === 'all');

const filteredStories = computed((): StoryInterface[] => {
    if (!showSinglePlayer.value) {
        return [];
    }

    return sortedStories.value;
});

const filteredRooms = computed((): MultiplayerLobbyCardInterface[] => {
    if (!showMultiplayer.value) {
        return [];
    }

    const rooms = props.multiplayerRooms ?? [];
    const mood = normalizedMood.value;
    if (!mood) {
        return rooms;
    }

    return rooms.filter((room) => storyMatchesMood(room.story.slug, mood));
});

const visibleCount = computed(() => filteredStories.value.length + filteredRooms.value.length);

const headerTitle = computed(() => {
    if (normalizedMood.value) {
        return `${visibleCount.value} Stories`;
    }

    return `Stories (${visibleCount.value})`;
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
    filteredStories.value.map((story) => ({
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

        <div class="mx-auto flex w-full justify-center px-5 pt-6 pb-10 sm:px-8 lg:px-16">
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
                    @click="switchLibraryTab(tab.key as LibraryTab)"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div :class="isMoodPage ? 'mood-page-flow' : ''">
            <template v-if="isMoodPage && normalizedMood">
                <MoodSelector :active-mood="normalizedMood" :overlap-next="showMoodTopPicks" />
                <MoodTopPicks
                    v-if="showMoodTopPicks"
                    :mood="normalizedMood"
                    :mood-label="moodHero.label"
                    :stories="moodPageStories"
                />
            </template>

            <div
                id="all-stories"
                class="overflow-visible pb-12 md:pb-[3.75rem]"
                :class="isMoodPage ? 'mood-page-stories-section' : 'relative z-10'"
            >
            <div class="container">
                <div class="container-content flex min-w-0 flex-col gap-5 md:gap-[1.125rem]">
                    <SectionHeader :title="headerTitle">
                        <template #action>
                            <LibrarySortMenu v-model="sortMode" />
                        </template>
                    </SectionHeader>

                    <div v-if="visibleCount" class="story-card-grid grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        <div v-for="game in games" :key="`story-${game.id}`" class="col-span-1 min-w-0">
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
                        <LibraryMultiplayerCard v-for="room in filteredRooms" :key="`room-${room.id}`" :room="room" />
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-dashed border-[#373737] bg-[#0f0f0f] px-6 py-10 text-center text-sm text-[#7e7e7e]"
                    >
                        {{
                            activeLibraryTab === 'multiplayer'
                                ? 'No multiplayer rooms are waiting for players right now.'
                                : 'No stories to show.'
                        }}
                    </div>
                </div>
            </div>
        </div>
        </div>

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
