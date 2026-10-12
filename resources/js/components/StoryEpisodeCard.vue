<script setup lang="ts">
import checkRingIcon from '@/assets/story-play/check-ring.svg';
import lockIcon from '@/assets/story-play/episode-lock.svg';
import progressIcon from '@/assets/story-play/episode-progress.svg';
import chapterPlaceholder from '@/assets/temp/chapter.png';
import { StoryEpisodeInterface } from '@/types';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    episode: StoryEpisodeInterface;
    fallbackCover?: string | null;
}>();

type CoverStage = 'episode' | 'fallback' | 'placeholder';

const coverStage = ref<CoverStage>('episode');

watch(
    () => props.episode.cover,
    () => {
        coverStage.value = 'episode';
    },
);

const coverImage = computed(() => {
    if (coverStage.value === 'episode' && props.episode.cover) {
        return props.episode.cover;
    }

    if (coverStage.value !== 'placeholder' && props.fallbackCover && props.fallbackCover !== props.episode.cover) {
        return props.fallbackCover;
    }

    return chapterPlaceholder;
});

function onCoverError(): void {
    if (coverStage.value === 'episode' && props.fallbackCover && props.fallbackCover !== props.episode.cover) {
        coverStage.value = 'fallback';

        return;
    }

    coverStage.value = 'placeholder';
}

type EpisodeStatus = 'completed' | 'in_progress' | 'not_started';

const status = computed<EpisodeStatus>(() => {
    if (props.episode.is_completed) {
        return 'completed';
    }

    if (props.episode.events_played > 0) {
        return 'in_progress';
    }

    return 'not_started';
});

const episodeLabel = computed(() => `Episode ${props.episode.number}`);

const titleClass = computed(() => {
    if (status.value === 'completed') {
        return 'text-white';
    }

    if (status.value === 'in_progress') {
        return 'text-[#ffbe58]';
    }

    return 'text-[rgba(255,190,88,0.2)]';
});

const statusLabel = computed(() => {
    if (status.value === 'completed') {
        return 'Events completed';
    }

    return null;
});
</script>

<template>
    <article class="rounded-lg border border-solid border-[#373737] bg-[#0f0f0f] p-2.5">
        <div class="flex items-center gap-2">
            <div class="relative h-[116px] w-[79px] shrink-0 overflow-hidden rounded-md bg-gray-800">
                <img :src="coverImage" alt="" class="size-full object-cover" @error="onCoverError" />
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-black/60" />
                <img
                    :src="coverImage"
                    alt=""
                    class="pointer-events-none absolute top-[18px] left-[5px] h-[37px] w-[69px] rounded-md object-cover"
                />
                <span
                    class="pointer-events-none absolute inset-x-0 top-[69px] z-[1] text-center font-['Times_New_Roman',Times,serif] text-[50px] leading-5 font-bold text-white/80"
                    aria-hidden="true"
                >
                    {{ episode.number }}
                </span>
            </div>

            <div class="flex h-[111px] min-w-0 flex-1 flex-col items-end">
                <div class="flex h-[87px] w-full flex-col gap-1.5">
                    <div class="w-full">
                        <h3 class="font-['Inter',sans-serif] text-[15px] leading-normal font-semibold" :class="titleClass">
                            {{ episodeLabel }}
                        </h3>

                        <p
                            v-if="status === 'completed'"
                            class="font-['Inter',sans-serif] text-[13px] leading-normal font-normal text-[#59c1ca]"
                        >
                            {{ statusLabel }}
                        </p>
                        <p v-else class="font-['Inter',sans-serif] text-[13px] leading-normal font-normal text-[#3f3f3f]">
                            <template v-if="status === 'in_progress'">
                                <span class="text-[#59c1ca]">Events {{ episode.events_played }}/</span>{{ episode.events_total }}
                            </template>
                            <template v-else>Events 0/{{ episode.events_total }}</template>
                        </p>
                    </div>

                    <p
                        v-if="episode.teaser"
                        class="line-clamp-2 font-['Inter',sans-serif] text-[14px] leading-[1.198] font-light tracking-[-0.42px] text-[#d8d8d8]"
                    >
                        {{ episode.teaser }}
                    </p>
                </div>

                <div
                    v-if="status === 'in_progress'"
                    class="relative size-6 shrink-0"
                    title="Episode in progress"
                >
                    <div class="absolute inset-[-316.67%_-333.33%_-350%_-333.33%]">
                        <img :src="progressIcon" alt="" width="184" height="184" class="block size-full max-w-none" />
                    </div>
                </div>
                <div
                    v-else
                    class="relative flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-[27px] bg-white/[0.04] shadow-[0_4px_80px_rgba(0,0,0,0.2)]"
                    :title="status === 'completed' ? 'Episode completed' : 'Episode not started'"
                >
                    <span
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-0 rounded-[inherit] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter backdrop-blur-[3px]"
                    />
                    <img
                        v-if="status === 'completed'"
                        :src="checkRingIcon"
                        alt=""
                        width="18"
                        height="18"
                        class="relative z-[1] max-w-none"
                    />
                    <img
                        v-else
                        :src="lockIcon"
                        alt=""
                        width="10.1818"
                        height="12.7273"
                        class="relative z-[1] max-w-none"
                    />
                    <span
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                    />
                </div>
            </div>
        </div>
    </article>
</template>
