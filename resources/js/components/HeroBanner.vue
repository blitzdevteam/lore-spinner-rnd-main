<script setup lang="ts">
import greenKnightImage from '@/assets/carosel/greenknight.webp';
import prideImage from '@/assets/top-moods/Pride prejudice 16_9 untitled.webp';
import ozImage from '@/assets/carosel/Ultrawide Oz.webp';
import sherlockImage from '@/assets/carosel/Sherlock Ultrawide hero banner.webp';
import tellTaleImage from '@/assets/carosel/Ultrawide the tell tale heart.webp';
import BaseButton from '@/components/BaseButton.vue';
import { LORE_SPINNER_CLASSIC } from '@/data/loreSpinnerSeriesLabels';
import { StoryInterface } from '@/types';
import { show } from '@/wayfinder/routes/stories';
import { Autoplay, EffectFade, Pagination } from 'swiper/modules';
import type { Swiper as SwiperInstance } from 'swiper';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { LucideArrowRight, LucideStar } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import 'swiper/css';
import 'swiper/css/effect-fade';
import 'swiper/css/pagination';

const props = withDefaults(
    defineProps<{
        stories?: StoryInterface[];
    }>(),
    {
        stories: () => [],
    },
);

interface FeaturedHeroSlideConfig {
    slug: string;
    image: string;
    imagePosition: string;
    titleLines: [string, string] | null;
    fallbackTitle: string;
    teaserLines: [string, string] | null;
    fallbackTeaser: string;
    fallbackAuthor: string;
    seriesLabel: string;
    comingSoon?: boolean;
}

const featuredHeroSlideConfigs: FeaturedHeroSlideConfig[] = [
    {
        slug: 'the-adventure-of-the-speckled-band',
        image: sherlockImage,
        imagePosition: 'object-center',
        titleLines: ['Sherlock Holmes', 'In The Speckled Band'],
        fallbackTitle: 'Sherlock Holmes in The Speckled Band',
        teaserLines: [
            'A young woman fears she will suffer the same fate as her sister,',
            'forcing Sherlock Holmes to confront a mystery hidden behind locked doors and deadly secrets.',
        ],
        fallbackTeaser:
            'A young woman fears she will suffer the same fate as her sister, forcing Sherlock Holmes to confront a mystery hidden behind locked doors and deadly secrets.',
        fallbackAuthor: 'Sir Arthur Conan Doyle',
        seriesLabel: LORE_SPINNER_CLASSIC,
    },
    {
        slug: 'the-wonderful-wizard-of-oz',
        image: ozImage,
        imagePosition: 'object-[58%_top] md:object-top',
        titleLines: ['The Wonderful', 'Wizard of Oz'],
        fallbackTitle: 'The Wonderful Wizard of Oz',
        teaserLines: [
            'A storm carries you into the magical land of Oz, where witches whisper, lions tremble,',
            'and every step down the Yellow Brick Road changes who you are becoming.',
        ],
        fallbackTeaser:
            'A storm carries you into the magical land of Oz, where witches whisper, lions tremble, and every step down the Yellow Brick Road changes who you are becoming.',
        fallbackAuthor: 'L. Frank Baum',
        seriesLabel: LORE_SPINNER_CLASSIC,
    },
    {
        slug: 'the-tell-tale-heart',
        image: tellTaleImage,
        imagePosition: 'object-[62%_top] md:object-top',
        titleLines: ['The Tell-Tale', 'Heart'],
        fallbackTitle: 'The Tell-Tale Heart',
        teaserLines: [
            'As guilt begins to twist reality around him, a man struggles to silence the terrifying sound he cannot escape:',
            "the beating of a dead man's heart.",
        ],
        fallbackTeaser:
            "As guilt begins to twist reality around him, a man struggles to silence the terrifying sound he cannot escape: the beating of a dead man's heart.",
        fallbackAuthor: 'Edgar Allan Poe',
        seriesLabel: LORE_SPINNER_CLASSIC,
    },
    {
        slug: 'pride-and-prejudice',
        image: prideImage,
        imagePosition: 'object-[62%_center] md:object-center',
        titleLines: ['Pride and', 'Prejudice'],
        fallbackTitle: 'Pride and Prejudice',
        teaserLines: [
            'In a world ruled by reputation, romance, and social expectation,',
            'Elizabeth Bennet must navigate pride, misunderstanding, and the dangerous possibility of falling in love.',
        ],
        fallbackTeaser:
            'In a world ruled by reputation, romance, and social expectation, Elizabeth Bennet must navigate pride, misunderstanding, and the dangerous possibility of falling in love.',
        fallbackAuthor: 'Jane Austen',
        seriesLabel: LORE_SPINNER_CLASSIC,
    },
    {
        slug: 'sir-gawain-and-the-green-knight',
        image: greenKnightImage,
        imagePosition: 'object-center',
        titleLines: ['Sir Gawain &', 'The Green Knight'],
        fallbackTitle: 'Sir Gawain & The Green Knight',
        teaserLines: [
            'Green knights, oaths, and the year',
            'that will define a hero.',
        ],
        fallbackTeaser: 'Green knights, oaths, and the year that will define a hero.',
        fallbackAuthor: 'Anonymous',
        seriesLabel: LORE_SPINNER_CLASSIC,
    },
];

interface ResolvedHeroSlide {
    slug: string;
    image: string;
    imagePosition: string;
    titleLines: [string, string] | null;
    title: string;
    teaserLines: [string, string] | null;
    teaser: string;
    author: string | null;
    seriesLabel: string;
    storyUrl: string;
    comingSoon: boolean;
}

const swiperModules = [Autoplay, EffectFade, Pagination];

const swiperRef = ref<SwiperInstance | null>(null);
const activeIndex = ref(0);

function resolveTitleLines(title: string): [string, string] | null {
    const t = title.trim();
    const m = t.match(/^(.+?)\s+in\s+(.+)$/i);
    if (!m) return null;
    return [m[1].trim(), `In ${m[2].trim()}`];
}

function resolveTeaserLines(teaser: string): [string, string] | null {
    const s = teaser.trim();
    const needle = ' bends and ';
    const i = s.indexOf(needle);
    if (i !== -1) {
        const first = (s.slice(0, i) + ' bends and').trimEnd();
        const second = s.slice(i + needle.length).trim();
        if (second) return [first, second];
    }

    const splitAt = s.search(/\s+(as|and|but|—)\s+/i);
    if (splitAt !== -1) {
        const first = s.slice(0, splitAt).trim();
        const second = s.slice(splitAt).trim();
        if (first && second) return [first, second];
    }

    return null;
}

const featuredSlides = computed((): ResolvedHeroSlide[] =>
    featuredHeroSlideConfigs.map((config) => {
        const story = props.stories.find((s) => s.slug === config.slug);
        const title = story?.title ?? config.fallbackTitle;
        const teaser = story?.teaser?.trim() ?? config.fallbackTeaser;

        return {
            slug: config.slug,
            image: config.image,
            imagePosition: config.imagePosition,
            titleLines: story ? (resolveTitleLines(title) ?? config.titleLines) : config.titleLines,
            title,
            teaserLines: story ? (resolveTeaserLines(teaser) ?? config.teaserLines) : config.teaserLines,
            teaser,
            author: config.fallbackAuthor,
            seriesLabel: config.seriesLabel,
            storyUrl: show(config.slug).url,
            comingSoon: config.comingSoon ?? false,
        };
    }),
);

const slides = computed((): ResolvedHeroSlide[] => featuredSlides.value);

const activeSlide = computed(() => slides.value[activeIndex.value] ?? slides.value[0]);

function shouldLoadSlideImage(index: number): boolean {
    const count = slides.value.length;

    if (count === 0) {
        return false;
    }

    const active = activeIndex.value;
    const previous = (active - 1 + count) % count;
    const next = (active + 1) % count;

    return index === active || index === previous || index === next;
}

function onSwiper(swiper: SwiperInstance) {
    swiperRef.value = swiper;
}

function onSlideChange(swiper: SwiperInstance) {
    activeIndex.value = swiper.realIndex;
}

function goPrev() {
    swiperRef.value?.slidePrev();
}

function goNext() {
    swiperRef.value?.slideNext();
}
</script>

<template>
    <div class="home-hero">
        <section class="hero-carousel" aria-label="Featured stories">
            <div class="hero-media">
                <Swiper
                    class="hero-swiper h-full w-full"
                    :modules="swiperModules"
                    :slides-per-view="1"
                    :effect="'fade'"
                    :fade-effect="{ crossFade: true }"
                    :speed="900"
                    :loop="true"
                    :autoplay="{ delay: 6500, disableOnInteraction: false, pauseOnMouseEnter: true }"
                    :pagination="{ clickable: true }"
                    @swiper="onSwiper"
                    @slide-change="onSlideChange"
                >
                    <SwiperSlide v-for="(slide, index) in slides" :key="slide.slug" class="hero-slide">
                        <div class="hero-slide-media">
                            <img
                                v-if="slide.image && shouldLoadSlideImage(index)"
                                :src="slide.image"
                                alt=""
                                class="hero-slide-image h-full w-full object-cover"
                                :class="slide.imagePosition"
                                :fetchpriority="index === activeIndex ? 'high' : 'low'"
                                decoding="async"
                            />
                            <div v-else class="hero-slide-fallback" aria-hidden="true" />
                            <div class="hero-slide-gradient" aria-hidden="true" />
                        </div>
                    </SwiperSlide>
                </Swiper>
            </div>

            <div class="hero-nav" aria-label="Hero carousel controls">
                <button type="button" class="hero-arrow hero-arrow-prev" aria-label="Previous slide" @click="goPrev">
                    <svg
                        viewBox="0 0 8 14"
                        width="8"
                        height="14"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                        class="rotate-180"
                    >
                        <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>

                <button type="button" class="hero-arrow hero-arrow-next" aria-label="Next slide" @click="goNext">
                    <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </section>

        <section class="hero-branch" aria-hidden="true">
            <img src="/static-branch.jpg" alt="" class="hero-branch__image" width="919px" height="86px" decoding="async" />
        </section>

        <section class="hero-copy-block">
            <div class="hero-copy-wrap">
                <div class="container w-full">
                    <div class="container-content">
                        <Transition name="hero-copy" mode="out-in">
                            <div :key="activeSlide.slug" class="hero-copy">
                                <div class="hero-copy-body">
                                    <h1 class="hero-title font-marcellus-sc text-white uppercase">
                                        <template v-if="activeSlide.titleLines">
                                            {{ activeSlide.titleLines[0] }}<br />
                                            {{ activeSlide.titleLines[1] }}
                                        </template>
                                        <template v-else>{{ activeSlide.title }}</template>
                                    </h1>

                                    <div class="hero-meta font-[Inter] text-white">
                                        <p class="hero-teaser text-white" :title="activeSlide.teaser">
                                            {{ activeSlide.teaser }}
                                        </p>
                                        <div class="hero-stats flex flex-col gap-0">
                                            <p v-if="activeSlide.author" class="hero-stat-line text-white">
                                                Written by:
                                                <span class="text-primary">{{ activeSlide.author }}</span>
                                            </p>
                                            <p v-if="activeSlide.seriesLabel" class="hero-stat-line hero-series-badge text-primary">
                                                <LucideStar class="hero-series-badge__icon" :stroke-width="1.75" aria-hidden="true" />
                                                <span>{{ activeSlide.seriesLabel }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <span
                                    v-if="activeSlide.comingSoon"
                                    class="begin-btn begin-btn--coming-soon !box-border !inline-flex !h-auto !min-h-[3rem] !w-auto max-w-full items-center justify-center self-start px-6 py-3 font-[Inter] text-[0.9375rem] !leading-none font-medium whitespace-nowrap sm:min-h-[3.3125rem] sm:max-w-[17.75rem] sm:px-8 sm:text-[1rem]"
                                    aria-disabled="true"
                                >
                                    Coming Soon
                                </span>
                                <BaseButton
                                    v-else
                                    severity="primary-outline"
                                    type="internal-link"
                                    :href="activeSlide.storyUrl"
                                    class="begin-btn !box-border !inline-flex !h-auto !min-h-[3rem] !w-auto max-w-full items-center justify-center gap-[0.625rem] self-start px-6 py-3 font-[Inter] text-[0.9375rem] !leading-none font-medium whitespace-nowrap text-white sm:min-h-[3.3125rem] sm:max-w-[17.75rem] sm:px-8 sm:text-[1rem]"
                                >
                                    Begin Your Journey
                                    <LucideArrowRight class="size-[1.125rem] shrink-0" aria-hidden="true" />
                                </BaseButton>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.home-hero {
    display: grid;
    grid-template-columns: 1fr;
    background: #000;
}

.hero-slide-fallback {
    width: 100%;
    height: 100%;
    background:
        radial-gradient(circle at 72% 42%, rgba(92, 104, 72, 0.45), transparent 46%),
        linear-gradient(180deg, #1c1e16 0%, #050605 100%);
}

.hero-carousel {
    position: relative;
    overflow: hidden;
    background: #000;
}

.hero-branch {
    width: 100%;
    background: #000;
}

.hero-branch__image {
    display: block;
    width: 100%;
    height: auto;
    max-width: 970px;
    margin-top: 40px;
    margin-inline: auto;
}

.hero-copy-block {
    background: #000;
}

.hero-media {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    min-height: 12.5rem;
    max-height: min(48vh, 20.5rem);
}

.hero-swiper :deep(.swiper-slide) {
    position: relative;
}

.hero-slide-media {
    position: relative;
    width: 100%;
    height: 100%;
}

.hero-slide-image {
    display: block;
    object-position: top;
}

.hero-slide-gradient {
    display: none;
    pointer-events: none;
    position: absolute;
    inset: 0;
}

.hero-copy-wrap {
    position: relative;
    z-index: 10;
    background: #000;
    padding: 1.25rem 0 1.5rem;
}

.hero-copy {
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 1.25rem;
}

.hero-copy-body {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.hero-title {
    max-width: 18ch;
    font-size: clamp(1.625rem, 7vw, 2.25rem);
    line-height: 1.12;
    text-shadow: 0 0 21.2px rgba(0, 0, 0, 0.85);
}

.hero-meta {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.hero-teaser {
    max-width: 38rem;
    font-size: 0.9375rem;
    line-height: 1.55;
    text-shadow: 0 1px 10px rgba(0, 0, 0, 0.75);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hero-stat-line {
    font-size: 0.8125rem;
    line-height: 1.5;
    text-shadow: 0 1px 8px rgba(0, 0, 0, 0.75);
}

.hero-series-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4375rem;
    font-weight: 500;
    letter-spacing: 0.02em;
}

.hero-series-badge__icon {
    width: 0.8125rem;
    height: 0.8125rem;
    flex-shrink: 0;
    fill: currentColor;
    filter: drop-shadow(0 0 6px color-mix(in srgb, var(--color-primary) 50%, transparent));
}

/* Flat dark fill with a thin teal outline — matches the updated Figma CTA design. */
.begin-btn {
    border-radius: 0.625rem;
    text-shadow: 0 1px 8px rgba(0, 0, 0, 0.8);
}

/* Coming Soon — matches the flat gray outline used on Featured Worlds cards. */
.begin-btn--coming-soon {
    background: transparent !important;
    border: 0.5px solid #8f8f8f !important;
    box-shadow: none !important;
    color: #8f8f8f !important;
    text-shadow: none;
    cursor: default;
    pointer-events: none;
}

/*
 * Full-bleed control rail. Stretch to the carousel box.
 * A separate 16:9 + max-height box shrinks on iPad and pulls the next arrow inward.
 */
.hero-nav {
    position: absolute;
    z-index: 25;
    inset: 0;
    width: 100%;
    pointer-events: none;
}

.hero-arrow {
    position: absolute;
    top: 50%;
    display: flex;
    width: 2.125rem;
    height: 2.125rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(6px);
    pointer-events: auto;
    transform: translateY(-50%);
    transition:
        background 0.2s,
        border-color 0.2s;
}

.hero-arrow-prev {
    left: 0.625rem;
}

.hero-arrow-next {
    right: 0.625rem;
}

.hero-arrow:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(255, 255, 255, 0.28);
}

.hero-copy-enter-active,
.hero-copy-leave-active {
    transition:
        opacity 0.45s ease,
        transform 0.45s ease;
}

.hero-copy-enter-from {
    opacity: 0;
    transform: translateY(0.625rem);
}

.hero-copy-leave-to {
    opacity: 0;
    transform: translateY(-0.375rem);
}

@media (min-width: 40rem) {
    .hero-media {
        max-height: min(44vh, 23rem);
    }

    .hero-arrow-prev {
        left: 0.875rem;
    }

    .hero-arrow-next {
        right: 0.875rem;
    }

    .hero-copy-wrap {
        padding: 1.5rem 0 1.75rem;
    }

    .hero-copy {
        gap: 1.5rem;
    }

    .hero-copy-body {
        gap: 1rem;
    }

    .hero-title {
        max-width: 22ch;
        font-size: clamp(2rem, 5vw, 2.75rem);
    }

    .hero-teaser {
        font-size: 1rem;
        line-height: 1.6;
    }

    .hero-stat-line {
        font-size: 0.875rem;
        line-height: 1.625;
    }

    .hero-arrow {
        width: 2.375rem;
        height: 2.375rem;
    }
}

@media (max-width: 47.9375rem) {
    .hero-copy-wrap {
        background: #000;
    }

    .hero-copy {
        gap: 1.5rem;
    }

    .begin-btn {
        width: auto;
        max-width: min(100%, 17.75rem);
        align-self: flex-start;
    }
}

@media (min-width: 48rem) {
    .home-hero {
        grid-template-rows: auto auto;
    }

    .hero-carousel {
        grid-row: 1;
        grid-column: 1;
        height: clamp(33rem, 50vh, 48rem);
    }

    .hero-branch {
        grid-row: 2;
        grid-column: 1;
    }

    .hero-copy-block {
        position: relative;
        z-index: 10;
        grid-row: 1;
        grid-column: 1;
        height: clamp(33rem, 50vh, 48rem);
        pointer-events: none;
        background: transparent;
    }

    .hero-slide-gradient {
        display: block;
        /* LoreSpinner Desktop Hero Readability Gradient — left-to-right cinematic fade */
        background: linear-gradient(
            90deg,
            rgba(0, 0, 0, 0.86) 0%,
            rgba(0, 0, 0, 0.7) 16%,
            rgba(0, 0, 0, 0.42) 30%,
            rgba(0, 0, 0, 0.16) 40%,
            rgba(0, 0, 0, 0) 46%
        );
    }

    .hero-media {
        position: absolute;
        inset: 0;
        aspect-ratio: unset;
        min-height: unset;
        max-height: none;
        height: 100%;
    }

    .hero-copy-wrap {
        position: absolute;
        inset: 0;
        z-index: 10;
        display: flex;
        align-items: flex-start;
        background: transparent;
        padding: 5.5625rem 0 1.75rem;
        pointer-events: none;
    }

    .hero-copy-wrap .container,
    .hero-copy-wrap .container-content {
        height: 100%;
    }

    .hero-copy {
        pointer-events: auto;
        max-width: min(34.75rem, 100%);
        gap: 1.5rem;
        /* Clear left-edge carousel control (arrow + gutter) */
        padding-inline: 3.5rem 1.5rem;
    }

    .hero-copy-body {
        gap: 0.9375rem;
    }

    .hero-title {
        max-width: 30.75rem;
        font-size: clamp(2rem, 4vw, 3rem);
        line-height: 1.08;
        text-shadow: 0 0 21.2px rgba(0, 0, 0, 0.85);
    }

    .hero-teaser {
        max-width: 25.6875rem;
        font-size: 1.125rem;
        line-height: 1.444;
        text-shadow: 0 1px 12px rgba(0, 0, 0, 0.75);
        -webkit-line-clamp: 3;
    }

    .hero-stat-line {
    }

    .hero-nav {
        inset: 0;
        height: 100%;
    }

    .hero-arrow {
        width: 2.5rem;
        height: 2.5rem;
    }

    .hero-arrow-prev {
        left: 1rem;
    }

    .hero-arrow-next {
        right: 1rem;
    }
}

/*
 * Touch-first tablet widths: pin controls low so they clear the left copy stack.
 * Skip for hover-capable pointers — browser zoom shrinks the layout viewport on
 * desktop/laptop but should keep arrows vertically centered on the hero image.
 */
@media (min-width: 48rem) and (max-width: 74.9375rem) and (hover: none) {
    .hero-arrow {
        top: auto;
        bottom: 3.75rem;
        transform: none;
    }
}

/* Wide desktop: copy no longer needs extra inset; arrows stay on viewport edges */
@media (min-width: 75rem) {
    .hero-arrow {
        top: 50%;
        bottom: auto;
        transform: translateY(-50%);
    }
    .hero-copy {
        padding-inline-start: 0;
        padding-right: 1.5rem;
    }
}

@media (min-width: 64rem) {
    .hero-title {
        font-size: 3rem;
        line-height: 1.08;
    }
}

/* iMac / large desktop — taller hero without dominating the page */
@media (min-width: 80rem) {
    .hero-carousel {
        height: clamp(38rem, 55vh, 56rem);
    }

    .hero-copy-block {
        height: clamp(38rem, 55vh, 56rem);
    }

    .hero-copy-wrap {
        padding-top: clamp(5.5625rem, 10vh, 7.5rem);
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-copy-enter-active,
    .hero-copy-leave-active {
        transition-duration: 0.01ms;
    }
}
</style>

<style>
.hero-swiper .swiper-pagination {
    bottom: 0.75rem !important;
    left: 50% !important;
    transform: translateX(-50%);
    width: auto !important;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    z-index: 20;
    padding: 0 0.5rem;
}

@media (min-width: 48rem) {
    .hero-swiper .swiper-pagination {
        bottom: 1.25rem !important;
        gap: 0.5rem;
    }
}

.hero-swiper .swiper-pagination-bullet {
    width: 6px;
    height: 6px;
    margin: 0 !important;
    border-radius: 62.4375rem;
    background: #605a58b2;
    opacity: 1;
    transition:
        width 0.3s ease,
        background 0.3s ease;
}

@media (min-width: 48rem) {
    .hero-swiper .swiper-pagination-bullet {
        width: 6px;
        height: 6px;
    }
}

.hero-swiper .swiper-pagination-bullet-active {
    width: 20px;
    background: white;
}

@media (min-width: 48rem) {
    .hero-swiper .swiper-pagination-bullet-active {
        width: 20px;
        box-shadow: 0 0 2px 0px white;
    }
}
</style>
