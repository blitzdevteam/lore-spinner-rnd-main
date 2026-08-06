<script setup lang="ts">
import StoryChapterCard from '@/components/StoryChapterCard.vue';
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
import backChevronIcon from '@/assets/story-play/back-chevron.svg';
import { useBookmark } from '@/composables/useBookmark';
import { formatCreatorDisplayName, resolveClassicsCreatorAvatar } from '@/data/loreSpinnerSeriesLabels';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { resolveStoryDisplayTitle } from '@/data/storyDisplayTitleBySlug';
import { useShare } from '@/composables/useShare';
import { StoryInterface } from '@/types';
import { store, show as showGame } from '@/wayfinder/actions/App/Http/Controllers/User/GameController';
import { show as storyShowRoute } from '@/wayfinder/routes/stories';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    story: StoryInterface;
    existingGameId?: string | null;
    isPlayable?: boolean;
}>();

type Panel = 'details' | 'chapters';

const panel = ref<Panel>('details');
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

const handleStartStory = (): void => {
    if (!props.isPlayable) return;
    if (props.existingGameId) {
        router.visit(showGame.url(props.existingGameId));
    } else {
        router.post(store(), {
            story_id: props.story.id,
        });
    }
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

const durationLabel = computed(() => {
    const n = props.story.chapters_count ?? props.story.chapters?.length ?? 0;
    if (n <= 0) {
        return null;
    }
    const mins = Math.max(25, Math.round(n * 10));
    return `${mins}min`;
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
        return [
            { text: `${totalMins}` },
            { text: 'Min', muted: true },
        ];
    }

    const totalHours = Math.floor(totalMins / 60);
    if (totalHours < 24) {
        const mins = totalMins % 60;
        return [
            { text: `${totalHours}` },
            { text: 'Hr ', muted: true },
            { text: `${mins}` },
            { text: 'Min', muted: true },
        ];
    }

    const days = Math.floor(totalHours / 24);
    if (days < 30) {
        return [
            { text: `${days}` },
            { text: days === 1 ? 'Day' : 'Days', muted: true },
        ];
    }

    const months = Math.floor(days / 30);
    if (months < 12) {
        return [
            { text: `${months}` },
            { text: months === 1 ? 'Mo' : 'Mos', muted: true },
        ];
    }

    const years = Math.floor(months / 12);
    return [
        { text: `${years}` },
        { text: years === 1 ? 'Yr' : 'Yrs', muted: true },
    ];
}

const statItems = computed(() => [
    {
        label: 'EPISODES',
        value: `${props.story.chapters_count ?? props.story.chapters?.length ?? 0}`,
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

const primaryCtaLabel = computed(() => (hasExistingGame.value ? 'Continue Story' : 'Enter The Story'));

const creatorAvatar = computed(() =>
    resolveClassicsCreatorAvatar(props.story.creator, props.story.creator?.avatar),
);

const leftColumnChapters = computed(() => props.story.chapters?.filter((_, index) => index % 2 === 0) ?? []);

const rightColumnChapters = computed(() => props.story.chapters?.filter((_, index) => index % 2 === 1) ?? []);
</script>

<template>
    <div class="relative min-h-svh overflow-x-hidden bg-black text-white selection:bg-primary-500/30 lg:h-svh lg:overflow-hidden lg:selection:bg-primary-500/35">
        <StoryPlayAmbientGlows />

        <div class="relative z-[1] mx-auto flex h-full max-w-[72rem] flex-col px-5 pt-8 pb-28 md:px-[3.25rem] md:pt-10 lg:h-svh lg:max-h-svh lg:flex-row lg:items-start lg:gap-6 lg:overflow-hidden lg:px-[3.25rem] lg:pt-[100px] lg:pb-0 xl:max-w-[75rem] xl:gap-10">

            <!-- Left: Poster column -->
            <div class="relative mb-8 w-[20.875rem] max-w-full shrink-0 lg:mb-0 lg:flex lg:w-[20.875rem] lg:shrink-0 xl:mr-2">
                <StoryPlayCoverColumn :src="coverImageUrl" :title="displayTitle" :headline="coverHeadline" :footer-credit="coverFooterCredit">
                    <template #overlay>
                        <!-- Back button — Figma 45×45 glass circle -->
                        <div class="pointer-events-auto absolute left-4 top-4">
                            <StoryPlayGlassRoundButton aria-label="Go back" @click="handleBack">
                                <img
                                    :src="backChevronIcon"
                                    alt=""
                                    class="h-8 w-5 shrink-0"
                                    width="20"
                                    height="32"
                                    aria-hidden="true"
                                />
                            </StoryPlayGlassRoundButton>
                        </div>
                        <div
                            v-if="!coverImageUrl"
                            class="pointer-events-none absolute inset-0 z-[2] grid place-items-center"
                            aria-hidden="true"
                        >
                            <span class="font-['Marcellus_SC','Marcellus SC',serif] text-4xl uppercase tracking-wider text-white/55">
                                {{ displayTitle.charAt(0)?.toUpperCase() }}
                            </span>
                        </div>
                    </template>
                </StoryPlayCoverColumn>
            </div>

            <!-- Right: Content column — full height, scrollable -->
            <div class="relative min-w-0 flex-1 story-show-panel lg:min-h-0 lg:self-stretch lg:overflow-y-auto lg:overscroll-contain lg:pr-1">
                <div class="flex flex-col lg:min-h-full">
                    <!-- Top bar: tabs + action buttons -->
                    <div class="shrink-0 overflow-visible pt-4 lg:pt-0">
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
                        <div
                            v-if="panel === 'details'"
                            class="flex flex-col gap-[33px] pt-[41px] pb-4"
                        >
                            <div class="flex flex-col gap-[21px]">
                                <div class="flex flex-col gap-2.5">
                                    <StoryPlayTitleProgress :title="displayTitle" />
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
                            <div v-if="story.chapters?.length" class="flex flex-col gap-2.5 sm:flex-row sm:items-start">
                                <div class="flex min-w-0 flex-1 flex-col gap-2.5">
                                    <StoryChapterCard
                                        v-for="(chapter, index) in leftColumnChapters"
                                        :key="chapter.id"
                                        :chapter
                                        :episode-number="index * 2 + 1"
                                    />
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col gap-2.5">
                                    <StoryChapterCard
                                        v-for="(chapter, index) in rightColumnChapters"
                                        :key="chapter.id"
                                        :chapter
                                        :episode-number="index * 2 + 2"
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
                    <div class="sticky bottom-0 z-10 mt-auto hidden shrink-0 bg-linear-to-t from-black via-black/95 to-transparent pt-8 pb-6 lg:block">
                        <StoryPlayStartCta
                            :label="primaryCtaLabel"
                            :coming-soon="!isPlayable"
                            @click="handleStartStory"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile CTA strip -->
        <div class="fixed inset-x-0 bottom-0 z-30 lg:hidden">
            <div class="pointer-events-none absolute inset-x-0 bottom-[4.5rem] h-[6rem] bg-linear-to-t from-black via-black/88 to-transparent" />
            <div class="relative bg-black/90 px-4 pt-4 pb-[calc(16px+env(safe-area-inset-bottom,0px))] backdrop-blur-md">
                <StoryPlayStartCta
                    :label="primaryCtaLabel"
                    :coming-soon="!isPlayable"
                    @click="handleStartStory"
                />
            </div>
        </div>
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
