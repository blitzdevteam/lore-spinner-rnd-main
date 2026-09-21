<script setup lang="ts">
import backChevronIcon from '@/assets/story-play/back-chevron.svg';
import FirstStoryTutorialModal from '@/components/FirstStoryTutorialModal.vue';
import StoryEpisodeCard from '@/components/StoryEpisodeCard.vue';
import StoryCommentCard from '@/components/StoryCommentCard.vue';
import StoryPlayAmbientGlows from '@/components/story-play/StoryPlayAmbientGlows.vue';
import StoryPlayAuthorDescription from '@/components/story-play/StoryPlayAuthorDescription.vue';
import StoryPlayCoverColumn from '@/components/story-play/StoryPlayCoverColumn.vue';
import StoryPlayGlassRoundButton from '@/components/story-play/StoryPlayGlassRoundButton.vue';
import StoryPlayMetaRow from '@/components/story-play/StoryPlayMetaRow.vue';
import StoryPlayStartCta from '@/components/story-play/StoryPlayStartCta.vue';
import StoryPlayStatStrip from '@/components/story-play/StoryPlayStatStrip.vue';
import StoryPlayTitleProgress from '@/components/story-play/StoryPlayTitleProgress.vue';
import StoryPlayTopBar from '@/components/story-play/StoryPlayTopBar.vue';
import { useBookmark } from '@/composables/useBookmark';
import { useShare } from '@/composables/useShare';
import { formatCreatorDisplayName, resolveClassicsCreatorAvatar } from '@/data/loreSpinnerSeriesLabels';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { resolveStoryDisplayTitle } from '@/data/storyDisplayTitleBySlug';
import { StoryEpisodeInterface, StoryInterface } from '@/types';
import { reset as resetGame, show as showGame, store } from '@/wayfinder/actions/App/Http/Controllers/User/GameController';
import { multiplayer as multiplayerStoryRoute, show as storyShowRoute } from '@/wayfinder/routes/stories';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    story: StoryInterface;
    /** Adaptation sessions surfaced to players as episodes. */
    episodes?: StoryEpisodeInterface[];
    existingGameId?: string | null;
    isPlayable?: boolean;
    showFirstStoryTutorial?: boolean;
}>();

type Panel = 'details' | 'chapters';

const panel = ref<Panel>('details');
const isRestarting = ref(false);
const isTutorialOpen = ref(false);
const hasDismissedTutorial = ref(false);
const isStartingStory = ref(false);
const hasExistingGame = computed(() => !!props.existingGameId);
const { isBookmarked, toggleBookmark } = useBookmark(props.story.id, props.story.is_bookmarked ?? false);
const { share } = useShare();

const storyShareUrl = computed(() => {
    if (typeof window !== 'undefined') {
        return `${window.location.origin}${storyShowRoute.url(props.story.slug)}`;
    }
    return storyShowRoute.url(props.story.slug);
});

const displayTitle = computed(() => resolveStoryDisplayTitle(props.story.slug, props.story.title));

const descriptorThemes = computed(() => getStoryDescriptorThemes(props.story.slug));

const handleShare = async (): Promise<void> => {
    const result = await share({
        title: displayTitle.value,
        text: teaserText.value || displayTitle.value,
        url: storyShareUrl.value,
    });

    if (result === 'copied') {
        toast.success('Link copied to clipboard');
    } else if (result === 'failed') {
        toast.error('Could not share this story');
    }
};

const startStory = (): void => {
    if (isStartingStory.value) return;

    isStartingStory.value = true;

    if (props.existingGameId) {
        router.visit(showGame.url(props.existingGameId), {
            onFinish: () => {
                isStartingStory.value = false;
            },
        });

        return;
    }

    router.post(
        store(),
        {
            story_id: props.story.id,
            return_to: storyShowRoute.url(props.story.slug),
        },
        {
            onFinish: () => {
                isStartingStory.value = false;
            },
        },
    );
};

const handleStartStory = (): void => {
    if (!props.isPlayable || isStartingStory.value) return;

    if (props.showFirstStoryTutorial && !props.existingGameId && !hasDismissedTutorial.value) {
        isTutorialOpen.value = true;

        return;
    }

    startStory();
};

const handleTutorialSkip = (): void => {
    if (hasDismissedTutorial.value) return;

    hasDismissedTutorial.value = true;
    isTutorialOpen.value = false;
    startStory();
};

const handleRestartStory = (): void => {
    if (!props.isPlayable || !props.existingGameId || isRestarting.value) {
        return;
    }

    const confirmed = window.confirm(
        'Start a fresh story? This will erase all your current progress, including every prompt and choice you have made.',
    );
    if (!confirmed) {
        return;
    }

    isRestarting.value = true;
    router.post(
        resetGame.url(props.existingGameId),
        {},
        {
            preserveScroll: false,
            preserveState: false,
            onFinish: () => {
                isRestarting.value = false;
            },
        },
    );
};

const handleMultiplayer = (): void => {
    if (!props.isPlayable) return;

    router.visit(multiplayerStoryRoute.url(props.story.slug));
};

const handleBack = (): void => {
    window.history.back();
};

const teaserText = computed(() => props.story.teaser?.trim() || '');

const creatorName = computed(() => {
    const raw =
        props.story.creator?.full_name?.trim() ||
        [props.story.creator?.first_name, props.story.creator?.last_name].filter(Boolean).join(' ') ||
        props.story.creator?.username ||
        'Author';

    return formatCreatorDisplayName(raw, props.story.creator);
});

const coverHeadline = computed(() => displayTitle.value.toUpperCase());

const coverFooterCredit = computed(() => creatorName.value.toUpperCase());

const coverImageUrl = computed(() => {
    const resolved = resolveStoryCover(props.story.slug, props.story.cover);
    return resolved || null;
});

const durationLabel = 'Approx. 25m per episode';

const episodes = computed(() => props.episodes ?? []);

const episodeCount = computed(() => episodes.value.length);

const completedEpisodeCount = computed(() => episodes.value.filter((episode) => episode.is_completed).length);

const progressPercent = computed(() => {
    if (episodeCount.value <= 0) {
        return 0;
    }

    return (completedEpisodeCount.value / episodeCount.value) * 100;
});

type StatValuePart = { text: string; muted?: boolean };

function formatUpdatedParts(dateIso: string | null | undefined): StatValuePart[] {
    if (!dateIso) {
        return [{ text: '—' }];
    }
    const ts = Date.parse(dateIso);
    if (Number.isNaN(ts)) {
        return [{ text: '—' }];
    }

    const totalMins = Math.max(0, Math.floor((Date.now() - ts) / 60000));
    if (totalMins < 60) {
        return [{ text: `${totalMins}` }, { text: 'Min', muted: true }];
    }

    const totalHours = Math.floor(totalMins / 60);
    if (totalHours < 24) {
        const mins = totalMins % 60;
        return [{ text: `${totalHours}` }, { text: 'Hr ', muted: true }, { text: `${mins}` }, { text: 'Min', muted: true }];
    }

    const days = Math.floor(totalHours / 24);
    if (days < 30) {
        return [{ text: `${days}` }, { text: days === 1 ? 'Day' : 'Days', muted: true }];
    }

    const months = Math.floor(days / 30);
    if (months < 12) {
        return [{ text: `${months}` }, { text: months === 1 ? 'Mo' : 'Mos', muted: true }];
    }

    const years = Math.floor(months / 12);
    return [{ text: `${years}` }, { text: years === 1 ? 'Yr' : 'Yrs', muted: true }];
}

const statItems = computed(() => [
    {
        label: 'EPISODES',
        value: `${episodeCount.value}`,
    },
    {
        label: 'RATING',
        value: props.story.rating?.label ?? '—',
    },
    {
        label: 'UPDATED',
        valueParts: formatUpdatedParts(props.story.updated_at ?? props.story.published_at),
    },
    {
        label: 'STATUS',
        value: props.story.status?.label ?? '—',
    },
]);

const primaryCtaLabel = computed(() => (hasExistingGame.value ? 'Continue Story' : 'Solo Spin'));

const creatorAvatar = computed(() => resolveClassicsCreatorAvatar(props.story.creator, props.story.creator?.avatar));

const leftColumnEpisodes = computed(() => episodes.value.filter((_, index) => index % 2 === 0));

const rightColumnEpisodes = computed(() => episodes.value.filter((_, index) => index % 2 === 1));
</script>

<template>
    <div
        class="relative min-h-svh overflow-x-hidden bg-black text-white selection:bg-primary-500/30 lg:h-svh lg:overflow-hidden lg:selection:bg-primary-500/35"
    >
        <StoryPlayAmbientGlows class="hidden lg:block" />

        <div class="absolute top-4 left-4 z-30 md:top-6 md:left-6 lg:top-8 lg:left-[3.25rem]">
            <StoryPlayGlassRoundButton aria-label="Go back" @click="handleBack">
                <img :src="backChevronIcon" alt="" class="h-8 w-5 shrink-0" width="20" height="32" aria-hidden="true" />
            </StoryPlayGlassRoundButton>
        </div>

        <div
            class="relative z-[1] mx-auto flex h-full max-w-[72rem] flex-col px-5 pt-20 md:px-[3.25rem] md:pt-20 lg:h-svh lg:max-h-svh lg:flex-row lg:items-start lg:gap-6 lg:overflow-hidden lg:px-[3.25rem] lg:pt-[100px] lg:pb-0 xl:max-w-[75rem] xl:gap-10"
            :class="isPlayable ? 'pb-40' : 'pb-28'"
        >
            <!-- Left: Poster column -->
            <div class="relative mx-auto w-full max-w-[20.875rem] shrink-0 pb-8 lg:mx-0 lg:mb-0 lg:flex lg:w-[20.875rem] lg:max-w-none lg:shrink-0 lg:pb-0 xl:mr-2">
                <div
                    v-if="coverImageUrl"
                    class="pointer-events-none absolute inset-x-0 top-[-2rem] bottom-0 z-0 md:top-[-2.5rem] lg:hidden"
                    aria-hidden="true"
                >
                    <div class="absolute inset-y-0 left-1/2 w-screen -translate-x-1/2 overflow-hidden">
                        <img
                            :src="coverImageUrl"
                            alt=""
                            class="absolute inset-[-80px] size-[calc(100%+160px)] max-w-none object-cover blur-[40px]"
                        />
                        <div class="absolute inset-0 bg-[rgba(0,0,0,0.45)]" />
                        <div class="absolute inset-x-0 bottom-0 h-24 bg-linear-to-t from-black to-transparent" />
                    </div>
                </div>

                <StoryPlayCoverColumn :src="coverImageUrl" :title="displayTitle" :headline="coverHeadline" :footer-credit="coverFooterCredit">
                    <template #overlay>
                        <div v-if="!coverImageUrl" class="pointer-events-none absolute inset-0 z-[2] grid place-items-center" aria-hidden="true">
                            <span class="font-['Marcellus_SC','Marcellus SC',serif] text-4xl tracking-wider text-white/55 uppercase">
                                {{ displayTitle.charAt(0)?.toUpperCase() }}
                            </span>
                        </div>
                    </template>
                </StoryPlayCoverColumn>
            </div>

            <!-- Right: Content column — full height, scrollable -->
            <div class="story-show-panel relative min-w-0 flex-1 lg:min-h-0 lg:self-stretch lg:overflow-y-auto lg:overscroll-contain lg:pr-1">
                <div class="flex flex-col lg:min-h-full">
                    <!-- Top bar: tabs + action buttons -->
                    <div class="relative z-[1] shrink-0 overflow-visible bg-black pt-0 lg:bg-transparent lg:pt-0">
                        <StoryPlayTopBar
                            :tab="panel"
                            :bookmark-filled="isBookmarked"
                            @update:tab="panel = $event"
                            @bookmark="toggleBookmark"
                            @share="handleShare"
                        />
                    </div>

                    <!-- Panel content -->
                    <div class="flex-1">
                        <!-- Details panel -->
                        <div v-if="panel === 'details'" class="flex flex-col gap-[33px] pt-[41px] pb-4">
                            <div class="flex flex-col gap-[21px]">
                                <div class="flex flex-col gap-2.5">
                                    <StoryPlayTitleProgress :title="displayTitle" :progress-percent="progressPercent" />
                                    <StoryPlayMetaRow :duration-label="durationLabel" :themes="descriptorThemes" />
                                </div>
                                <StoryPlayStatStrip :items="statItems" />
                            </div>

                            <StoryPlayAuthorDescription
                                :author-name="creatorName"
                                :avatar-url="creatorAvatar"
                                :description="teaserText || 'Explore this playable story.'"
                                :collapse-at="360"
                            />

                            <section
                                v-if="story.comments?.length"
                                aria-label="Community comments"
                                class="rounded-xl border border-white/12 bg-black/35 p-4 backdrop-blur-sm"
                            >
                                <div class="mb-4 font-['Inter',sans-serif] text-[0.8125rem] font-semibold tracking-wide text-gray-400 uppercase">
                                    Comments · {{ story.comments_count ?? story.comments.length }}
                                </div>
                                <div class="flex flex-col gap-4">
                                    <StoryCommentCard v-for="comment in story.comments" :key="comment.id" :comment />
                                </div>
                            </section>
                        </div>

                        <!-- Episodes panel -->
                        <div v-else class="pt-[41px] pb-4">
                            <div v-if="episodes.length" class="flex flex-col gap-2.5 sm:flex-row sm:items-start">
                                <div class="flex min-w-0 flex-1 flex-col gap-2.5">
                                    <StoryEpisodeCard
                                        v-for="episode in leftColumnEpisodes"
                                        :key="episode.number"
                                        :episode
                                    />
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col gap-2.5">
                                    <StoryEpisodeCard
                                        v-for="episode in rightColumnEpisodes"
                                        :key="episode.number"
                                        :episode
                                    />
                                </div>
                            </div>
                            <p
                                v-else
                                class="rounded-xl border border-white/15 bg-black/45 py-14 text-center font-['Inter',sans-serif] text-sm font-medium text-gray-500 backdrop-blur-sm"
                            >
                                No episodes listed yet.
                            </p>
                        </div>
                    </div>

                    <!-- Desktop CTA — sticky at bottom of scrollable right column -->
                    <div
                        class="sticky bottom-0 z-10 mt-auto hidden shrink-0 bg-linear-to-t from-black via-black/95 to-transparent pt-8 pb-6 lg:block"
                    >
                        <div class="flex flex-col gap-6">
                            <div class="flex gap-2.5">
                                <StoryPlayStartCta :label="primaryCtaLabel" :coming-soon="!isPlayable" @click="handleStartStory" />
                                <StoryPlayStartCta label="Group Spin" variant="secondary" :coming-soon="!isPlayable" @click="handleMultiplayer" />
                            </div>
                            <button
                                v-if="isPlayable"
                                type="button"
                                class="font-['Inter',sans-serif] text-center text-[16px] font-semibold text-[#8f8f8f] transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-[#8f8f8f]"
                                :disabled="!hasExistingGame || isRestarting"
                                @click="handleRestartStory"
                            >
                                Restart Story
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile CTA strip -->
        <div class="fixed inset-x-0 bottom-0 z-30 lg:hidden">
            <div
                class="pointer-events-none absolute inset-x-0 h-[6rem] bg-linear-to-t from-black via-black/88 to-transparent"
                :class="isPlayable ? 'bottom-[7.5rem]' : 'bottom-[4.5rem]'"
            />
            <div class="relative bg-black/90 px-4 pt-4 pb-[calc(16px+env(safe-area-inset-bottom,0px))] backdrop-blur-md">
                <div class="flex flex-col gap-6">
                    <div class="flex gap-2.5">
                        <StoryPlayStartCta :label="primaryCtaLabel" :coming-soon="!isPlayable" @click="handleStartStory" />
                        <StoryPlayStartCta label="Group Spin" variant="secondary" :coming-soon="!isPlayable" @click="handleMultiplayer" />
                    </div>
                    <button
                        v-if="isPlayable"
                        type="button"
                        class="font-['Inter',sans-serif] text-center text-[16px] font-semibold text-[#8f8f8f] transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-[#8f8f8f]"
                        :disabled="!hasExistingGame || isRestarting"
                        @click="handleRestartStory"
                    >
                        Restart Story
                    </button>
                </div>
            </div>
        </div>

        <FirstStoryTutorialModal
            :open="isTutorialOpen"
            video-src="/videos/lorespinner-website-guide.mp4"
            @skip="handleTutorialSkip"
        />
    </div>
</template>

<style scoped>
@supports (scrollbar-color: auto) {
    .story-show-panel {
        scrollbar-width: thin;
        scrollbar-color: rgba(111, 175, 186, 0.35) transparent;
    }
}

.story-show-panel::-webkit-scrollbar {
    width: 6px;
}

.story-show-panel::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(111, 175, 186, 0.35);
}

.story-show-panel::-webkit-scrollbar-track {
    background: transparent;
}
</style>
