<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import iconYouChoose from '@/assets/story/left.svg';
import iconStoryResponds from '@/assets/story/center.svg';
import iconXen from '@/assets/story/right .svg';
import storyChangesBg from '@/assets/story/story-changes.png';
import { Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/vue';

import 'swiper/css';
import 'swiper/css/pagination';

const swiperModules = [Pagination];

const storyItems = [
    {
        id: 'imagination',
        colClass: 'story-changes-col--choose',
        iconClass: 'you-choose-icon',
        glowClass: 'you-choose-glow',
        icon: iconYouChoose,
        iconImgClass: 'h-[1.75rem] w-[1.7rem]',
        width: 27,
        height: 28,
        title: 'Use Your Imagination',
        description: 'Your ideas shape the story.',
    },
    {
        id: 'xen',
        colClass: 'story-changes-col--xen',
        iconClass: 'xen-icon',
        glowClass: 'xen-glow',
        icon: iconXen,
        iconImgClass: 'h-[0.95rem] w-[2.6rem]',
        width: 42,
        height: 14,
        title: 'Xen Responds',
        description:
            'Xen unfolds the story based on your choices while staying true to the author\'s\u00A0world.',
    },
    {
        id: 'journey',
        colClass: 'story-changes-col--responds',
        iconClass: 'story-responds-icon',
        glowClass: 'story-responds-glow',
        icon: iconStoryResponds,
        iconImgClass: 'h-[1.4rem] w-[1.9rem]',
        width: 30,
        height: 22,
        title: 'Every Journey Is Different',
        description: 'Return, replay, and discover what else is\u00A0possible.',
    },
] as const;
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Tell the Story What to Do"
                    subtitle="Stories are no longer something you simply read. They're something you can participate in."
                />

                <div
                    class="story-changes-card relative flex flex-col overflow-hidden rounded-[1.5rem] border border-solid border-white/15 p-5 outline-none md:p-5"
                >
                    <div
                        class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                        aria-hidden="true"
                    >
                        <img
                            :src="storyChangesBg"
                            alt=""
                            class="absolute inset-0 size-full object-cover object-center"
                        />
                    </div>

                    <Swiper
                        class="story-changes-swiper relative z-[1] w-full"
                        :modules="swiperModules"
                        :slides-per-view="1"
                        :space-between="0"
                        :pagination="{ clickable: true }"
                        :breakpoints="{
                            768: {
                                slidesPerView: 3,
                                spaceBetween: 35,
                                allowTouchMove: false,
                                pagination: { enabled: false },
                            },
                        }"
                    >
                        <SwiperSlide v-for="item in storyItems" :key="item.id">
                            <div
                                class="story-changes-col flex w-full max-w-[17.5rem] flex-col items-center text-center md:mx-auto md:w-[17.5rem]"
                                :class="item.colClass"
                            >
                                <div
                                    class="icon-circle relative flex size-[70px] shrink-0 items-center justify-center overflow-hidden rounded-full"
                                    :class="item.iconClass"
                                >
                                    <div class="absolute inset-0 rounded-full bg-[rgba(255,255,255,0.02)]" aria-hidden="true" />
                                    <div
                                        class="absolute inset-0 rounded-full backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                        aria-hidden="true"
                                    />
                                    <img
                                        :src="item.icon"
                                        alt=""
                                        class="story-changes-col__icon relative z-[2] shrink-0 object-contain"
                                        :class="item.iconImgClass"
                                        :width="item.width"
                                        :height="item.height"
                                    />
                                    <div
                                        class="absolute inset-0 pointer-events-none rounded-full"
                                        :class="item.glowClass"
                                        aria-hidden="true"
                                    />
                                </div>
                                <h3 class="story-changes-col__title text-primary">
                                    {{ item.title }}
                                </h3>
                                <p class="story-changes-col__body -mt-2 text-white ">
                                    {{ item.description }}
                                </p>
                            </div>
                        </SwiperSlide>
                    </Swiper>
                </div>

                <div
                    class="try-telling-banner relative flex w-full items-center justify-center overflow-hidden rounded-[0.875rem] border border-solid border-white/15 px-4 py-5 md:px-8 md:py-[1.125rem]"
                    role="region"
                    aria-label="Try telling the story"
                >
                    <div class="try-telling-banner__inner">
                        <div class="try-telling-banner__main">
                            <p class="try-telling-banner__label m-0 text-primary">
                                Try telling the story:
                            </p>
                            <p class="try-telling-banner__examples m-0 text-white">
                                <span class="italic">"Inspect the clue"</span>
                                <span class="try-telling-banner__dot" aria-hidden="true">•</span>
                                <span class="italic">"Climb the tower"</span>
                                <span class="try-telling-banner__dot" aria-hidden="true">•</span>
                                <span class="uppercase italic">"MAKE THE TIN MAN BREAKDANCE"</span>
                            </p>
                        </div>
                        <p class="try-telling-banner__caption m-0 text-[#8a8a8a]">
                            The story responds in real time.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.story-changes-card {
    background-color: #0d0d0d;
    box-shadow: none;
}

.story-changes-swiper {
    height: auto !important;
}

.story-changes-swiper :deep(.swiper-wrapper) {
    align-items: stretch;
}

.story-changes-swiper :deep(.swiper-slide) {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    height: auto !important;
    box-sizing: border-box;
}

/* Shared vertical rhythm — identical across all three columns */
.story-changes-col {
    --col-gap-icon-title: 0.875rem;
    --col-gap-title-body: 1.25rem;
    gap: var(--col-gap-icon-title);
}

.story-changes-col__icon {
    display: block;
}

.story-changes-col__title {
    margin: 0;
    width: 100%;
    text-align: center;
    font-size: 1.125rem;
    max-width: 15rem;
    font-weight: 600;
    line-height: 1.375rem;
    min-height: 1.375rem;
}

.story-changes-col__body {
    width: 100%;
    text-align: center;
    font-size: 0.875rem;
    font-weight: 300;
    line-height: 1.25rem;
    text-shadow: 0px 0px 26.9px #0f0f0f;
}

@media (min-width: 48rem) {
    .story-changes-swiper :deep(.swiper-slide:not(:last-child)) {
        border-right: 1px solid rgba(255, 255, 255, 0.14);
    }
}

.story-changes-swiper :deep(.swiper-pagination) {
    position: relative;
    bottom: auto;
    left: auto;
    transform: none;
    width: 100%;
    margin-top: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
}

@media (min-width: 48rem) {
    .story-changes-swiper :deep(.swiper-pagination) {
        display: none;
    }
}

.story-changes-swiper :deep(.swiper-pagination-bullet) {
    width: 1.75rem;
    height: 3px;
    margin: 0 !important;
    border-radius: 62.4375rem;
    background: rgba(255, 255, 255, 0.35);
    opacity: 1;
    transition:
        width 0.3s ease,
        background 0.3s ease;
}

.story-changes-swiper :deep(.swiper-pagination-bullet-active) {
    width: 2.5rem;
    background: var(--color-primary);
}

/* Glass base for all icon circles -- Figma: subtle 2.25px blur + near-transparent fill */
.icon-circle {
    backdrop-filter: blur(2.25px);
    -webkit-backdrop-filter: blur(2.25px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    transition:
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}

/* Default: all three circles match (no gold until Xen column hover) */
.you-choose-icon,
.story-responds-icon,
.xen-icon {
    border-color: #131313;
    box-shadow: 0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

/* Shared hover intensity — same shadow structure, accent color per column */
.story-changes-col--choose:hover .you-choose-icon {
    border-color: color-mix(in srgb, var(--color-primary-400) 92%, transparent);
    box-shadow:
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.35),
        0 0 1.375rem 3px color-mix(in srgb, var(--color-primary-400) 28%, transparent);
}

.story-changes-col--responds:hover .story-responds-icon {
    border-color: rgba(255, 255, 255, 0.92);
    box-shadow:
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.35),
        0 0 1.375rem 3px rgba(255, 255, 255, 0.28);
}

.story-changes-col--xen:hover .xen-icon {
    border-color: rgba(255, 190, 88, 0.96);
    box-shadow:
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.35),
        0 0 1.375rem 3px rgba(255, 190, 88, 0.28);
}

/* Inset glow overlays rendered as the topmost layer inside each circle */
.you-choose-glow,
.story-responds-glow {
    box-shadow:
        inset 0px 0px 0.8688rem 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}

/* Xen: neutral inset at rest so the circle matches the other two */
.xen-glow {
    box-shadow:
        inset 0px 0px 0.8688rem 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}

.try-telling-banner {
    background:
        radial-gradient(ellipse 40% 120% at 0% 50%, rgba(0, 180, 200, 0.12), transparent 70%),
        radial-gradient(ellipse 35% 120% at 100% 50%, rgba(255, 160, 60, 0.1), transparent 70%),
        linear-gradient(180deg, #0a0a0a 0%, #101010 50%, #0a0a0a 100%);
}

.try-telling-banner__inner {
    display: flex;
    width: 100%;
    max-width: 100%;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    text-align: center;
}

.try-telling-banner__main {
    display: flex;
    width: 100%;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.try-telling-banner__label {
    font-size: 0.95rem !important;
    font-weight: 400;
    line-height: 1.5;
}

.try-telling-banner__examples {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.375rem 0.5rem;
    font-size: 0.95rem;
    font-weight: 400;
    line-height: 1.5;
}

.try-telling-banner__dot {
    color: rgba(255, 255, 255, 0.4);
}

.try-telling-banner__caption {
    font-size: 0.95rem;
    font-weight: 400;
    line-height: 1.25rem;
    text-align: center;
}

@media (min-width: 48rem) {
    .try-telling-banner__inner {
        width: fit-content;
        gap: 0.125rem;
    }

    .try-telling-banner__main {
        width: auto;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }

    .try-telling-banner__label {
        font-size: 0.95rem;
        line-height: 1.625rem;
        white-space: nowrap;
    }

    .try-telling-banner__examples {
        flex-wrap: nowrap;
        font-size: 0.95rem;
        line-height: 1.625rem;
        white-space: nowrap;
    }
}
</style>
