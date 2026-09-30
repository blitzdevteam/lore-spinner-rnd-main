<script setup lang="ts">
import bookOpenIcon from '@/assets/continue-story/book-open.svg';
import chevronIcon from '@/assets/continue-story/chevron.svg';
import clockIcon from '@/assets/continue-story/clock.svg';
import SectionHeader from '@/components/SectionHeader.vue';
import { formatCreatorDisplayName } from '@/data/loreSpinnerSeriesLabels';
import { resolveStoryDisplayTitle } from '@/data/storyDisplayTitleBySlug';
import { resolveStoryTopMoodCover } from '@/data/storyTopMoodCoverBySlug';
import { GameInterface } from '@/types';
import { show } from '@/wayfinder/routes/user/games';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    game: GameInterface;
}>();

const story = computed(() => props.game.story);

const posterUrl = computed(() => {
    const s = story.value;
    if (!s?.slug) {
        return '';
    }

    return resolveStoryTopMoodCover(s.slug, s.cover);
});

const hasCover = computed(() => Boolean(posterUrl.value));
const gameUrl = computed(() => show(props.game.id).url);

const displayTitle = computed(() => {
    const s = story.value;
    if (!s) {
        return '';
    }

    return resolveStoryDisplayTitle(s.slug, s.title);
});

const episodeNumber = computed(() => props.game.current_session_number);

const episodeBadgeLabel = computed(() => (episodeNumber.value ? `Episode ${episodeNumber.value}` : null));

// An episode is an adaptation session, so a same-numbered chapter title would mislabel it.
const currentEpisodeLine = computed(() => (episodeNumber.value ? `Episode ${episodeNumber.value}` : null));

const timeAgo = computed(() => {
    if (!props.game.updated_at) {
        return 'Just now';
    }

    const diff = Date.now() - new Date(props.game.updated_at).getTime();
    const minutes = Math.floor(diff / 60000);

    if (minutes < 1) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes} ${minutes === 1 ? 'Minute' : 'Minutes'} ago`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return `${hours} ${hours === 1 ? 'Hour' : 'Hours'} ago`;
    }

    const days = Math.floor(hours / 24);
    if (days < 30) {
        return `${days} ${days === 1 ? 'Day' : 'Days'} ago`;
    }

    const months = Math.floor(days / 30);

    return `${months} ${months === 1 ? 'Month' : 'Months'} ago`;
});

const creatorName = computed(() => {
    const creator = story.value?.creator;
    if (!creator) {
        return '';
    }

    const raw =
        creator.full_name?.trim() ||
        [creator.first_name, creator.last_name].filter(Boolean).join(' ') ||
        creator.username ||
        '';

    if (!raw) {
        return '';
    }

    return formatCreatorDisplayName(raw, creator);
});
</script>

<template>
    <section class="home-section-y" aria-label="Continue your adventure">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader title="Continue Your Adventure" subtitle="Pick up where you left off." />

                <article
                    class="flex flex-col rounded-lg border border-solid border-[#373737] bg-[#0f0f0f] px-2.5 pt-2.5 pb-3.5"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-5">
                        <div
                            class="relative aspect-[433/231] w-full shrink-0 overflow-hidden rounded-[5px] sm:aspect-auto sm:h-[12.5rem] sm:w-[min(27.0625rem,42%)] lg:h-[14.4375rem] lg:w-[27.0625rem]"
                        >
                            <img
                                v-if="hasCover"
                                :src="posterUrl"
                                :alt="displayTitle"
                                class="absolute inset-0 size-full object-cover object-center"
                            />
                            <div
                                v-else
                                class="absolute inset-0 grid place-items-center bg-[#1a1a1a]"
                            >
                                <span class="text-4xl font-semibold text-white/30">{{ displayTitle.charAt(0)?.toUpperCase() }}</span>
                            </div>
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col justify-between gap-5 sm:h-[12.5rem] sm:items-end lg:h-[13.4375rem]">
                            <div class="flex w-full flex-col gap-3.5">
                                <div class="flex max-w-[24.5625rem] flex-col gap-1.5">
                                    <div class="flex flex-wrap items-center gap-3.5">
                                        <div
                                            v-if="episodeBadgeLabel"
                                            class="relative flex h-7 min-w-[5.0625rem] shrink-0 items-center justify-center overflow-hidden rounded-[27px] px-2.5"
                                            style="box-shadow: 0 4px 80px rgba(0, 0, 0, 0.2)"
                                        >
                                            <span
                                                aria-hidden="true"
                                                class="pointer-events-none absolute inset-0 rounded-[inherit] bg-white/[0.04]"
                                            />
                                            <span
                                                aria-hidden="true"
                                                class="pointer-events-none absolute inset-0 rounded-[inherit] bg-[rgba(30,30,30,0.25)] backdrop-blur-[3px] mix-blend-plus-lighter"
                                            />
                                            <span
                                                class="relative z-[1] font-['Inter',sans-serif] text-[13px] leading-none font-normal text-[#fdd24f]"
                                            >
                                                {{ episodeBadgeLabel }}
                                            </span>
                                            <span
                                                aria-hidden="true"
                                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                                            />
                                        </div>
                                        <span class="flex items-center gap-1 font-['Inter',sans-serif] text-xs leading-normal font-normal text-[#7e7e7e]">
                                            <span class="relative size-[18px] shrink-0 overflow-clip">
                                                <img
                                                    :src="clockIcon"
                                                    alt=""
                                                    class="size-full"
                                                    width="18"
                                                    height="18"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            Last played {{ timeAgo }}
                                        </span>
                                    </div>

                                    <div class="flex w-full flex-col gap-1">
                                        <h3
                                            class="font-['Inter',sans-serif] text-xl leading-[1.875rem] font-semibold text-white"
                                        >
                                            {{ displayTitle }}
                                        </h3>
                                        <p
                                            v-if="creatorName"
                                            class="font-['Inter',sans-serif] text-[13px] leading-normal font-normal text-[#d8d8d8]"
                                        >
                                            {{ creatorName }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="currentEpisodeLine" class="flex items-center gap-1">
                                    <span class="relative size-[18px] shrink-0 overflow-clip">
                                        <img
                                            :src="bookOpenIcon"
                                            alt=""
                                            class="size-full"
                                            width="18"
                                            height="18"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <p class="font-['Inter',sans-serif] text-xs leading-normal font-normal text-[#fdd24f]">
                                        {{ currentEpisodeLine }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex w-full flex-wrap items-center justify-end gap-3.5">
                                <p
                                    v-if="game.prompts_count != null"
                                    class="font-['Inter',sans-serif] text-xs leading-normal font-normal whitespace-nowrap text-[#7e7e7e]"
                                >
                                    {{ game.prompts_count }} turns played
                                </p>
                                <Link
                                    :href="gameUrl"
                                    class="flex h-[50px] w-[183px] shrink-0 items-center justify-center rounded-[10px] border-[0.5px] border-solid border-[#59c1ca] bg-[#0e1f22] px-2.5 py-2 no-underline transition-colors hover:border-[#8fcbd3] hover:bg-[#163f46] active:bg-[#0a1618]"
                                >
                                    <span class="flex h-6 items-center justify-center gap-2.5">
                                        <span
                                            class="font-['Inter',sans-serif] text-center text-base leading-normal font-normal whitespace-nowrap text-white"
                                        >
                                            Continue Story
                                        </span>
                                        <span class="flex size-[11px] items-center justify-center overflow-clip">
                                            <img
                                                :src="chevronIcon"
                                                alt=""
                                                class="h-[10.125px] w-[5.063px] -scale-y-100 rotate-180"
                                                width="6"
                                                height="11"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    </span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
