<script setup lang="ts">
import { ChapterInterface } from '@/types';
import { ChapterStatusEnum } from '@/types/enum';
import chapterPlaceholder from '@/assets/temp/chapter.png';
import checkRingIcon from '@/assets/story-play/check-ring.svg';
import { LucideLock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    chapter: ChapterInterface;
    /** 1-based episode index for display (Episode N). */
    episodeNumber: number;
    isCompleted?: boolean;
}>();

const coverImage = computed(() => props.chapter.cover || chapterPlaceholder);

const displayNumber = computed(() => props.chapter.position ?? props.episodeNumber);

const isPlayable = computed(
    () => props.chapter.status?.value === ChapterStatusEnum.READY_TO_PLAY,
);

const isLocked = computed(() => !isPlayable.value);

const episodeLabel = computed(() => `Episode ${displayNumber.value}`);

const eventsLabel = computed(() => {
    if (props.isCompleted) {
        return 'Events completed';
    }
    if (isPlayable.value) {
        return null;
    }
    const count = props.chapter.events_count;
    if (count == null) {
        return null;
    }
    return `Events ${count}`;
});
</script>

<template>
    <article
        class="group rounded-lg border border-solid border-[#373737] bg-[#0f0f0f] p-1.5 transition-[border-color,background-color,transform,box-shadow] duration-200 ease-out hover:-translate-y-px hover:border-[#59c1ca]/40 hover:bg-[#141414] hover:shadow-[0_8px_32px_rgba(0,0,0,0.35)]"
    >
        <div class="flex items-center justify-center gap-2">
            <!-- Thumbnail + episode number -->
            <div class="relative h-[116px] w-[79px] shrink-0 overflow-hidden rounded-md bg-gray-800">
                <img
                    :src="coverImage"
                    alt=""
                    class="size-full object-cover transition-transform duration-200 group-hover:scale-[1.02]"
                />
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 rounded-md bg-black/60"
                />
                <span
                    class="pointer-events-none absolute inset-x-0 bottom-[1.7rem] z-[1] text-center font-['Times_New_Roman',Times,serif] text-[50px] leading-5 font-bold text-white/80"
                    aria-hidden="true"
                >
                    {{ displayNumber }}
                </span>
            </div>

            <!-- Content -->
            <div class="flex h-[111px] min-w-0 flex-1 flex-col items-end">
                <div class="flex h-[87px] w-full flex-col gap-1.5">
                    <div class="flex w-full flex-col items-start">
                        <div class="flex h-6 w-full items-center justify-between gap-2">
                            <h3
                                class="truncate font-['Inter',sans-serif] text-[0.9375rem] font-semibold leading-normal"
                                :class="isLocked ? 'text-white/25' : 'text-white'"
                            >
                                {{ episodeLabel }}
                            </h3>

                        </div>

                        <p
                            v-if="eventsLabel"
                            class="w-full font-['Inter',sans-serif] text-[0.8125rem] font-normal leading-normal"
                            :class="isCompleted ? 'text-[#59c1ca]' : 'text-[#3f3f3f]'"
                        >
                            {{ eventsLabel }}
                        </p>
                    </div>

                    <p
                        v-if="chapter.teaser"
                        class="line-clamp-2 h-[34px] font-['Inter',sans-serif] text-sm font-light leading-[1.198] tracking-[-0.02625rem] text-[#d8d8d8] text-justify"
                    >
                        {{ chapter.teaser }}
                    </p>
                </div>

                <!-- Status icon -->
                <div
                    class="relative mt-auto flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-[27px] bg-white/[0.04]"
                    style="box-shadow: 0px 4px 80px rgba(0, 0, 0, 0.2)"
                >
                    <span
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-0 rounded-[inherit] bg-[rgba(30,30,30,0.25)] backdrop-blur-[3px] mix-blend-plus-lighter"
                    />
                    <LucideLock
                        v-if="isLocked"
                        class="relative z-[1] size-3.5 text-white/70"
                        :stroke-width="1.75"
                        aria-hidden="true"
                    />
                    <img
                        v-else
                        :src="checkRingIcon"
                        alt=""
                        class="relative z-[1] size-[18px]"
                        width="18"
                        height="18"
                        aria-hidden="true"
                    />
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
        </div>
    </article>
</template>
