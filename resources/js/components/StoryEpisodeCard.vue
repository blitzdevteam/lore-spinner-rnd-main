<script setup lang="ts">
import checkRingIcon from '@/assets/story-play/check-ring.svg';
import chapterPlaceholder from '@/assets/temp/chapter.png';
import { StoryEpisodeInterface } from '@/types';
import { LucideLock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    episode: StoryEpisodeInterface;
}>();

const coverImage = computed(() => props.episode.cover || chapterPlaceholder);

const isLocked = computed(() => !props.episode.is_playable);

const episodeLabel = computed(() => `Episode ${props.episode.number}`);

const progressLabel = computed(() => (props.episode.is_completed ? 'Completed' : null));

/** Locked episodes show a padlock, completed ones a check; unplayed episodes stay unmarked. */
const statusIcon = computed<'locked' | 'completed' | null>(() => {
    if (isLocked.value) {
        return 'locked';
    }

    return props.episode.is_completed ? 'completed' : null;
});
</script>

<template>
    <article
        class="group rounded-lg border border-solid border-[#373737] bg-[#0f0f0f] p-1.5 transition-[border-color,background-color,transform,box-shadow] duration-200 ease-out hover:-translate-y-px hover:border-[#59c1ca]/40 hover:bg-[#141414] hover:shadow-[0_8px_32px_rgba(0,0,0,0.35)]"
    >
        <div class="flex items-stretch gap-2">
            <!-- Thumbnail + episode number -->
            <div class="relative h-[116px] w-[79px] shrink-0 overflow-hidden rounded-md bg-gray-800">
                <img :src="coverImage" alt="" class="size-full object-cover transition-transform duration-200 group-hover:scale-[1.02]" />
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-md bg-black/60" />
                <span
                    class="pointer-events-none absolute inset-x-0 bottom-[1.7rem] z-[1] text-center font-['Times_New_Roman',Times,serif] text-[50px] leading-5 font-bold text-white/80"
                    aria-hidden="true"
                >
                    {{ episode.number }}
                </span>
            </div>

            <!-- Content -->
            <div class="flex min-h-[116px] min-w-0 flex-1 flex-col gap-1">
                <div class="flex w-full items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3
                            class="truncate font-['Inter',sans-serif] text-[0.9375rem] leading-normal font-semibold"
                            :class="isLocked ? 'text-white/25' : 'text-white'"
                        >
                            {{ episodeLabel }}
                        </h3>

                        <p v-if="progressLabel" class="w-full font-['Inter',sans-serif] text-[0.8125rem] leading-normal font-normal text-[#59c1ca]">
                            {{ progressLabel }}
                        </p>
                    </div>

                    <!-- Status icon -->
                    <div
                        v-if="statusIcon"
                        class="relative flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-[27px] bg-white/[0.04]"
                        style="box-shadow: 0px 4px 80px rgba(0, 0, 0, 0.2)"
                        :title="statusIcon === 'completed' ? 'Episode completed' : 'Episode locked'"
                    >
                        <span
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-0 rounded-[inherit] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter backdrop-blur-[3px]"
                        />
                        <LucideLock
                            v-if="statusIcon === 'locked'"
                            class="relative z-[1] size-3.5 text-white/70"
                            :stroke-width="1.75"
                            aria-hidden="true"
                        />
                        <img v-else :src="checkRingIcon" alt="" class="relative z-[1] size-[18px]" width="18" height="18" aria-hidden="true" />
                        <span
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-0 rounded-[inherit]"
                            style="
                                box-shadow:
                                    inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
                                    inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
                            "
                        />
                    </div>
                </div>

                <p
                    v-if="episode.teaser"
                    class="line-clamp-4 font-['Inter',sans-serif] text-sm leading-[1.35] font-light tracking-[-0.0125rem] text-[#d8d8d8]"
                >
                    {{ episode.teaser }}
                </p>
            </div>
        </div>
    </article>
</template>
