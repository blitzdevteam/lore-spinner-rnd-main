<script setup lang="ts">
import heroBackground from '@/assets/about/hero-background.png';
import journeyBackground from '@/assets/about/journey-background.jpg';
import storyOrbit from '@/assets/about/story-orbit.png';
import storyPath from '@/assets/about/story-path.png';
import iconStoryResponds from '@/assets/story/center.svg';
import iconYouChoose from '@/assets/story/left.svg';
import iconXen from '@/assets/story/right .svg';
import storyChangesBg from '@/assets/story/story-changes.png';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, CircleCheck, Globe2, MessageCircleMore, Play, Sparkles, Star } from 'lucide-vue-next';
import { Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/vue';

import 'swiper/css';
import 'swiper/css/pagination';

const swiperModules = [Pagination];

const experienceSteps = [
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
        description: 'Xen unfolds the story based on your choices while staying true to the author’s world.',
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
        description: 'Return, replay, and discover what else is possible.',
    },
] as const;

const testimonials = [
    'I love how the author blends humor with serious themes. It makes the characters feel so relatable and real.',
    'Every choice felt meaningful. I stopped watching the story from a distance and became part of it.',
    'The world kept surprising me while still feeling true to the story I already loved.',
    'I came back to make one different choice and found an entirely new path through the world.',
];

const orbitItems = [
    { label: 'The story responds', icon: MessageCircleMore, position: 'orbit-top' },
    { label: 'The world evolves', icon: Globe2, position: 'orbit-left' },
    { label: 'Built for readers and creators', icon: Star, position: 'orbit-right' },
    { label: 'Your choice', icon: CircleCheck, position: 'orbit-bottom' },
];
</script>

<template>
    <HomeLayout>
        <Head title="About Us" />

        <main class="about-page bg-black text-white">
            <section class="hero-section relative overflow-hidden">
                <img :src="heroBackground" alt="" class="absolute inset-0 h-full w-full object-cover object-center" />
                <div class="hero-shade absolute inset-0" aria-hidden="true" />

                <div class="relative z-10 container">
                    <div class="container-content grid min-h-[35rem] items-center gap-10 py-14 lg:grid-cols-[1.05fr_0.95fr] lg:py-0">
                        <div class="max-w-[33.375rem]">
                            <h1 class="font-marcellus-sc text-[2.25rem] leading-[1.45] uppercase md:text-[2.25rem] md:leading-[1.5]">
                                <span>
                                    LoreSpinner is a new way to
                                    <span class="text-primary">step inside stories</span>
                                </span>
                            </h1>
                            <p class="mt-4 max-w-[32rem] text-base leading-[1.625rem] text-white md:text-lg">
                                LoreSpinner was built by story lovers for people who believe stories aren’t finished just because the page ends.
                            </p>
                        </div>

                        <div class="orbit-wrap relative mx-auto aspect-square w-full">
                            <img :src="storyOrbit" alt="" class="orbit-ring absolute object-contain" aria-hidden="true" />
                            <div v-for="item in orbitItems" :key="item.label" class="orbit-item" :class="item.position">
                                <span class="orbit-icon"><component :is="item.icon" class="size-5" :stroke-width="1.5" /></span>
                                <span class="orbit-label">{{ item.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="how-it-works" class="container pb-10 md:pb-16">
                <div class="container-content">
                    <div
                        class="story-changes-card relative flex flex-col overflow-hidden rounded-[1.5rem] border border-solid border-white/15 p-5 outline-none md:p-5"
                    >
                        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true">
                            <img :src="storyChangesBg" alt="" class="absolute inset-0 size-full object-cover object-center" />
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
                            <SwiperSlide v-for="step in experienceSteps" :key="step.id">
                                <div
                                    class="story-changes-col flex w-full max-w-[17.5rem] flex-col items-center text-center md:mx-auto md:w-[17.5rem]"
                                    :class="step.colClass"
                                >
                                    <div
                                        class="icon-circle relative flex size-[70px] shrink-0 items-center justify-center overflow-hidden rounded-full"
                                        :class="step.iconClass"
                                    >
                                        <div class="absolute inset-0 rounded-full bg-[rgba(255,255,255,0.02)]" aria-hidden="true" />
                                        <div class="absolute inset-0 rounded-full mix-blend-plus-lighter backdrop-blur-[2.25px]" aria-hidden="true" />
                                        <img
                                            :src="step.icon"
                                            alt=""
                                            class="story-changes-col__icon relative z-[2] shrink-0 object-contain"
                                            :class="step.iconImgClass"
                                            :width="step.width"
                                            :height="step.height"
                                        />
                                        <div class="pointer-events-none absolute inset-0 rounded-full" :class="step.glowClass" aria-hidden="true" />
                                    </div>
                                    <h3 class="story-changes-col__title text-primary">
                                        {{ step.title }}
                                    </h3>
                                    <p class="story-changes-col__body -mt-2 text-white">
                                        {{ step.description }}
                                    </p>
                                </div>
                            </SwiperSlide>
                        </Swiper>
                    </div>
                </div>
            </section>

            <section class="container py-8 md:py-12">
                <div class="container-content space-y-20 md:space-y-24">
                    <article class="numbered-section">
                        <div class="section-number">01</div>
                        <div>
                            <header>
                                <h2>What is LoreSpinner?</h2>
                                <p class="section-lead">
                                    LoreSpinner was built by story lovers for people who believe stories aren’t finished just because the page ends.
                                </p>
                            </header>
                            <div class="mt-7 grid gap-10 lg:grid-cols-[1.15fr_1fr] lg:items-center">
                                <div class="section-copy">
                                    <p>
                                        We begin with human-crafted adaptations of beloved classics and timeless stories from around the world,
                                        created to honor the voice, world, and spirit of the original work — the things that made them worth loving in
                                        the first place.
                                    </p>
                                    <p>But stories don’t have to end there.</p>
                                    <p>LoreSpinner invites you to step inside and experience stories in a completely new way.</p>
                                </div>
                                <div class="decision-demo grid gap-6 sm:grid-cols-[1fr_10rem] sm:items-center">
                                    <div>
                                        <h3 class="text-base font-medium">Every decision matters</h3>
                                        <p class="mt-1 text-[0.8125rem] tracking-[0.03em] text-[#7e7e7e]">Your imagination enters.</p>
                                        <ul class="mt-3 space-y-2">
                                            <li
                                                v-for="(choice, index) in [
                                                    'Report them to the guards.',
                                                    'Walk away silently.',
                                                    'Trust the stranger.',
                                                ]"
                                                :key="choice"
                                                class="flex min-h-11 items-center gap-2 rounded-lg border px-2 text-xs"
                                                :class="index === 2 ? 'border-primary bg-primary/10' : 'border-[#373737]'"
                                            >
                                                <span
                                                    class="grid size-5 place-items-center rounded-full border"
                                                    :class="index === 2 ? 'border-primary text-primary' : 'border-[#7e7e7e] text-transparent'"
                                                >
                                                    <Check class="size-3" :stroke-width="2.5" />
                                                </span>
                                                {{ choice }}
                                            </li>
                                        </ul>
                                    </div>
                                    <img
                                        :src="storyPath"
                                        alt="A branching story path with locked routes and a treasure destination"
                                        class="story-path-image mx-auto w-full max-w-[10rem]"
                                    />
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="numbered-section">
                        <div class="section-number">02</div>
                        <div>
                            <header>
                                <h2>How it is changing world building</h2>
                                <p class="section-lead">From passive reading to living, interactive worlds.</p>
                            </header>
                            <div class="section-copy mt-7">
                                <p>In LoreSpinner, you don’t just read or listen to what happens next. You participate.</p>
                                <p>
                                    You can speak, choose, wonder, question, challenge, interrupt, or offer your own ideas. You don’t need permission
                                    to be creative here.
                                </p>
                                <p>Be a little outrageous. Tell characters to do things you’ve only imagined before.</p>
                                <p>Play.</p>
                                <p>That’s where Xen comes in.</p>
                                <p>
                                    Xen is your guide through every story: listening, adapting, and helping bring each moment to life while staying
                                    true to the world you entered.
                                </p>
                                <p>
                                    Xen doesn’t replace the author or storyteller. Xen helps the story listen to you — and might even become your
                                    partner in crime along the way.
                                </p>
                            </div>
                        </div>
                    </article>

                    <article class="numbered-section">
                        <div class="section-number">03</div>
                        <div>
                            <header>
                                <h2>The future of storytelling</h2>
                                <p class="section-lead">Stories deserve to be discovered, experienced, and brought back to life.</p>
                            </header>
                            <div class="section-copy mt-7">
                                <p>
                                    We’re starting with stories many people know and love, then expanding toward stories from around the world that
                                    more people deserve to discover.
                                </p>
                                <p>Folktales, novels, myths, mysteries, adventures, ghost stories, romances, epics, and more.</p>
                                <p>If a story has lived in the hearts of story lovers for generations, we believe it can live again here.</p>
                                <p>LoreSpinner is not a library you browse from a distance.</p>
                                <p>It’s a doorway.</p>
                                <p>Step inside. Bring your imagination.</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!--            <section class="container py-16 md:py-20">-->
            <!--                <div class="container-content">-->
            <!--                    <h2 class="text-[1.625rem] font-bold uppercase">Loved by lore seekers</h2>-->
            <!--                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">-->
            <!--                        <article-->
            <!--                            v-for="(quote, index) in testimonials"-->
            <!--                            :key="quote"-->
            <!--                            class="rounded-lg border bg-[#0f0f0f] p-4"-->
            <!--                            :class="index === 1 ? 'border-primary' : 'border-[#191919]'"-->
            <!--                        >-->
            <!--                            <div class="flex gap-0.5 text-[#ffbe58]" aria-label="5 out of 5 stars">-->
            <!--                                <Star v-for="star in 5" :key="star" class="size-3.5 fill-current" />-->
            <!--                            </div>-->
            <!--                            <blockquote class="mt-4 min-h-[6.5rem] text-xs leading-5 text-white">“ {{ quote }} ”</blockquote>-->
            <!--                            <div class="mt-3 flex items-center gap-2.5">-->
            <!--                                <span class="grid size-7 place-items-center rounded-full bg-[#17282b] text-primary"-->
            <!--                                    ><Sparkles class="size-3.5"-->
            <!--                                /></span>-->
            <!--                                <span class="text-xs font-semibold">KELMOR</span>-->
            <!--                            </div>-->
            <!--                        </article>-->
            <!--                    </div>-->
            <!--                </div>-->
            <!--            </section>-->

            <!--            <section class="journey-banner relative overflow-hidden">-->
            <!--                <img :src="journeyBackground" alt="" class="absolute inset-0 h-full w-full object-cover object-center" />-->
            <!--                <div class="journey-shade absolute inset-0" aria-hidden="true" />-->
            <!--                <div class="relative z-10 container">-->
            <!--                    <div class="container-content flex min-h-[17rem] flex-col items-center justify-center px-4 text-center">-->
            <!--                        <h2 class="font-marcellus-sc text-2xl uppercase md:text-[2rem]">-->
            <!--                            Your next great story <span class="text-primary">starts here</span>-->
            <!--                        </h2>-->
            <!--                        <p class="mt-2 text-base text-[#8f8f8f] md:text-lg">Step into a universe of endless possibilities.</p>-->
            <!--                        <Link :href="storiesIndex().url" class="about-button mt-6 inline-flex min-h-12 items-center gap-3 px-6">-->
            <!--                            Begin Your Journey-->
            <!--                            <ArrowRight class="size-5" />-->
            <!--                        </Link>-->
            <!--                    </div>-->
            <!--                </div>-->
            <!--            </section>-->
        </main>
    </HomeLayout>
</template>

<style scoped>
.hero-section {
    margin-top: -1px;
}

.hero-shade {
    background:
        linear-gradient(180deg, rgba(0, 0, 0, 0.12) 0%, rgba(0, 0, 0, 0) 42%, #000 100%),
        linear-gradient(90deg, #000 4%, rgba(0, 0, 0, 0.25) 48%, #000 100%);
}

.about-button {
    justify-content: center;
    border: 0.5px solid #59c1ca;
    border-radius: 0.625rem;
    background: #0e1f22;
    font-size: 0.875rem;
    transition:
        border-color 180ms ease,
        background-color 180ms ease,
        transform 180ms ease;
}

.about-button:hover {
    border-color: #8fcbd3;
    background: #163f46;
    transform: translateY(-1px);
}

.orbit-wrap {
    --orbit-inset: 10%;
    --orbit-icon-size: 3.5rem;
    max-width: 35rem;
    translate: 4% -7%;
}

.orbit-ring {
    inset: var(--orbit-inset);
    width: calc(100% - (var(--orbit-inset) * 2));
    height: calc(100% - (var(--orbit-inset) * 2));
}

.orbit-item {
    position: absolute;
    display: flex;
    width: 11rem;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    color: white;
    font-size: 1rem;
    line-height: 1.35rem;
    text-align: center;
    text-transform: capitalize;
}

.orbit-icon {
    display: grid;
    place-items: center;
    border: 1px solid #262626;
    border-radius: 9999px;
    background: #191919;
    color: #ffbe58;
    box-shadow: inset 0 0 14px rgba(255, 190, 88, 0.08);
    width: var(--orbit-icon-size);
    height: var(--orbit-icon-size);
    flex-shrink: 0;
}

.orbit-top {
    top: calc(var(--orbit-inset) - (var(--orbit-icon-size) / 2));
    left: 50%;
    transform: translateX(-50%);
}

.orbit-left {
    top: calc(50% - (var(--orbit-icon-size) / 2));
    left: var(--orbit-inset);
    transform: translateX(-50%);
}

.orbit-right {
    top: calc(50% - (var(--orbit-icon-size) / 2));
    right: var(--orbit-inset);
    transform: translateX(50%);
}

.orbit-bottom {
    top: calc(100% - var(--orbit-inset) - (var(--orbit-icon-size) / 2));
    left: 50%;
    transform: translateX(-50%);
}

.numbered-section {
    display: grid;
    gap: 1rem;
}

.section-number {
    margin-right: 0.5rem;
    font-family: 'Abhaya Libre', serif;
    font-size: 4rem;
    font-weight: 700;
    line-height: 1;
    color: #59c1ca;
}

.numbered-section h2 {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 2rem;
    text-transform: uppercase;
}

.section-lead {
    margin-top: 0.25rem;
    color: #d8d8d8;
    font-size: 1rem;
    line-height: 1.625rem;
}

.section-copy {
    color: #7e7e7e;
    font-size: 0.875rem;
    line-height: 1.625rem;
}

.section-copy p + p {
    margin-top: 0.15rem;
}

.story-path-image {
    aspect-ratio: 728 / 768;
    border-radius: 0.375rem;
    object-fit: cover;
}

.journey-shade {
    background: linear-gradient(180deg, #000 0%, rgba(0, 0, 0, 0.28) 48%, #000 100%);
}

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
    max-width: 15rem;
    min-height: 1.375rem;
    text-align: center;
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.375rem;
}

.story-changes-col__body {
    width: 100%;
    text-align: center;
    font-size: 0.875rem;
    font-weight: 300;
    line-height: 1.25rem;
    text-shadow: 0px 0px 26.9px #0f0f0f;
}

.story-changes-swiper :deep(.swiper-pagination) {
    position: relative;
    bottom: auto;
    left: auto;
    display: flex;
    width: 100%;
    margin-top: 1.25rem;
    transform: none;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
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

.icon-circle {
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(2.25px);
    -webkit-backdrop-filter: blur(2.25px);
    transition:
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}

.you-choose-icon,
.story-responds-icon,
.xen-icon {
    border-color: #131313;
    box-shadow: 0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

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

.you-choose-glow,
.story-responds-glow,
.xen-glow {
    box-shadow:
        inset 0px 0px 0.8688rem 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}

@media (min-width: 48rem) {
    .story-changes-swiper :deep(.swiper-slide:not(:last-child)) {
        border-right: 1px solid rgba(255, 255, 255, 0.14);
    }

    .story-changes-swiper :deep(.swiper-pagination) {
        display: none;
    }

    .numbered-section {
        grid-template-columns: 5.5rem 1fr;
        gap: 1.25rem;
    }

    .section-number {
        font-size: 5rem;
        text-align: center;
    }

    .numbered-section h2 {
        font-size: 1.625rem;
        line-height: 2.0625rem;
    }
}

@media (max-width: 47.999rem) {
    .orbit-wrap {
        --orbit-icon-size: 2.875rem;
        max-width: 22rem;
        translate: 0;
    }

    .orbit-item {
        width: 7.5rem;
        font-size: 0.6875rem;
        line-height: 1rem;
    }
}
</style>
