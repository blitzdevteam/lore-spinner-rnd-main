<script setup lang="ts">
import featureGlow from '@/assets/about/feature-glow.png';
import storyCycle from '@/assets/about/story-cycle.png';
import iconStoryResponds from '@/assets/story/center.svg';
import iconYouChoose from '@/assets/story/left.svg';
import iconJourney from '@/assets/story/right .svg';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/vue';

import '../../css/fonts/abhaya-libre/abhaya-libre.css';
import 'swiper/css';
import 'swiper/css/pagination';

const swiperModules = [Pagination];

const experienceSteps = [
    {
        id: 'imagination',
        colClass: 'story-changes-col--choose',
        iconClass: 'feature-icon',
        glowClass: 'feature-glow',
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
        iconClass: 'feature-icon',
        glowClass: 'feature-glow',
        icon: iconStoryResponds,
        iconImgClass: 'h-[1.4rem] w-[1.9rem]',
        width: 30,
        height: 22,
        title: 'Xen Responds',
        description: 'Xen unfolds the story based on your choices while staying true to the author’s world.',
    },
    {
        id: 'journey',
        colClass: 'story-changes-col--journey',
        iconClass: 'feature-icon feature-icon--journey',
        glowClass: 'feature-glow feature-glow--journey',
        icon: iconJourney,
        iconImgClass: 'h-[0.95rem] w-[2.6rem]',
        width: 42,
        height: 14,
        title: 'Every Journey Is Different',
        description: 'Return, replay, and discover what else is possible.',
    },
] as const;

const sections = [
    {
        number: '01',
        title: 'What is LoreSpinner?',
        lead: 'LoreSpinner was built by story lovers for people who believe stories aren’t finished just because the page ends.',
        paragraphs: [
            'We begin with human-crafted adaptations of beloved classics and timeless stories from around the world, created to honor the voice, world, and spirit of the original work — the things that made them worth loving in the first place.',
            'But stories don’t have to end there.',
            'LoreSpinner invites you to step inside and experience stories in a completely new way.',
        ],
    },
    {
        number: '02',
        title: 'How it is changing world building',
        lead: 'From passive reading to living, interactive worlds.',
        paragraphs: [
            'In LoreSpinner, you don’t just read or listen to what happens next. You participate.',
            'You can speak, choose, wonder, question, challenge, interrupt, or offer your own ideas. You don’t need permission to be creative here.',
            'Be a little outrageous. Tell characters to do things you’ve only imagined before.',
            'Play.',
            'That’s where Xen comes in.',
            'Xen is your guide through every story: listening, adapting, and helping bring each moment to life while staying true to the world you entered.',
            'Xen doesn’t replace the author or storyteller. Xen helps the story listen to you — and might even become your partner in crime along the way.',
        ],
    },
    {
        number: '03',
        title: 'The future of storytelling',
        lead: 'Stories deserve to be discovered, experienced, and brought back to life.',
        paragraphs: [
            'We’re starting with stories many people know and love, then expanding toward stories from around the world that more people deserve to discover.',
            'Folktales, novels, myths, mysteries, adventures, ghost stories, romances, epics, and more.',
            'If a story has lived in the hearts of story lovers for generations, we believe it can live again here.',
            'LoreSpinner is not a library you browse from a distance.',
            'It’s a doorway.',
            'Step inside. Bring your imagination.',
        ],
    },
] as const;
</script>

<template>
    <HomeLayout>
        <Head title="About Us" />

        <main class="about-page bg-black text-white">
            <section class="container">
                <div
                    class="container-content flex flex-col items-center gap-10 py-12 lg:flex-row lg:items-center lg:justify-between lg:gap-8 lg:pt-14 lg:pb-[4.75rem]"
                >
                    <div class="w-full max-w-[33.375rem]">
                        <h1
                            class="font-marcellus-sc text-[2rem] leading-[1.35] text-white uppercase text-shadow-[0px_0px_21.2px_black] md:text-[2.5rem] md:leading-[3.4375rem]"
                        >
                            LoreSpinner is a new way to <span class="text-primary">step inside stories</span>
                        </h1>
                        <p class="mt-[0.9375rem] text-base leading-[1.625rem] text-white md:text-lg">
                            LoreSpinner was built by story lovers for people who believe stories aren’t finished just because the page ends.
                        </p>
                    </div>

                    <div class="w-full min-w-0 max-w-[23.375rem]">
                        <img
                            :src="storyCycle"
                            alt="Tell the story what to do, make a choice, the story responds, and the world evolves."
                            class="h-auto w-full"
                            width="374"
                            height="371"
                        />
                    </div>
                </div>
            </section>

            <section id="how-it-works" class="container pb-8 md:pb-12">
                <div class="container-content">
                    <div class="feature-card relative flex flex-col overflow-hidden rounded-[0.9375rem] border border-solid border-[#373737] p-5">
                        <img :src="featureGlow" alt="" class="feature-card__glow" aria-hidden="true" />

                        <Swiper
                            class="feature-swiper relative z-[1] w-full"
                            :modules="swiperModules"
                            :slides-per-view="1"
                            :space-between="0"
                            :pagination="{ clickable: true }"
                            :breakpoints="{
                                768: {
                                    slidesPerView: 3,
                                    spaceBetween: 0,
                                    allowTouchMove: false,
                                    pagination: { enabled: false },
                                },
                            }"
                        >
                            <SwiperSlide v-for="step in experienceSteps" :key="step.id">
                                <div class="feature-col mx-auto flex w-full max-w-[17.5rem] flex-col items-center text-center" :class="step.colClass">
                                    <div class="icon-circle relative flex size-[4.21875rem] shrink-0 items-center justify-center overflow-hidden rounded-full" :class="step.iconClass">
                                        <div class="absolute inset-0 rounded-full bg-[rgba(255,255,255,0.02)]" aria-hidden="true" />
                                        <div class="absolute inset-0 rounded-full mix-blend-plus-lighter backdrop-blur-[2.25px]" aria-hidden="true" />
                                        <img
                                            :src="step.icon"
                                            alt=""
                                            class="relative z-[2] shrink-0 object-contain"
                                            :class="step.iconImgClass"
                                            :width="step.width"
                                            :height="step.height"
                                        />
                                        <div class="pointer-events-none absolute inset-0 rounded-full" :class="step.glowClass" aria-hidden="true" />
                                    </div>
                                    <h3 class="feature-col__title text-primary">
                                        {{ step.title }}
                                    </h3>
                                    <p class="feature-col__body text-white">
                                        {{ step.description }}
                                    </p>
                                </div>
                            </SwiperSlide>
                        </Swiper>
                    </div>
                </div>
            </section>

            <section class="container pt-8 pb-16 md:pt-12 md:pb-24">
                <div class="container-content flex flex-col gap-16 md:gap-[4.5rem]">
                    <article v-for="section in sections" :key="section.number" class="numbered-section">
                        <div class="section-number">{{ section.number }}</div>
                        <div class="flex min-w-0 flex-col gap-[1.875rem]">
                            <header>
                                <h2>{{ section.title }}</h2>
                                <p class="section-lead">{{ section.lead }}</p>
                            </header>
                            <div class="section-copy">
                                <p v-for="paragraph in section.paragraphs" :key="paragraph">{{ paragraph }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </main>
    </HomeLayout>
</template>

<style scoped>
.feature-card {
    background-color: #030406;
}

.feature-card__glow {
    pointer-events: none;
    position: absolute;
    top: 42%;
    left: -12%;
    height: 9.5rem;
    width: 140%;
    max-width: none;
    object-fit: cover;
    object-position: bottom;
    opacity: 0.77;
    filter: blur(7px);
}

.feature-swiper {
    height: auto !important;
}

.feature-swiper :deep(.swiper-wrapper) {
    align-items: stretch;
}

.feature-swiper :deep(.swiper-slide) {
    display: flex;
    height: auto !important;
    box-sizing: border-box;
    align-items: flex-start;
    justify-content: center;
}

.feature-col {
    gap: 0.875rem;
}

.feature-col__title {
    margin: 0;
    width: 100%;
    text-align: center;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.25rem;
}

.feature-col__body {
    width: 100%;
    text-align: center;
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1.25rem;
    text-shadow: 0 0 24.3px #000;
}

.feature-swiper :deep(.swiper-pagination) {
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

.feature-swiper :deep(.swiper-pagination-bullet) {
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

.feature-swiper :deep(.swiper-pagination-bullet-active) {
    width: 2.5rem;
    background: var(--color-primary);
}

.icon-circle {
    border: 0.75px solid #131313;
    box-shadow: 0 2.25px 45px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(1.7px);
    -webkit-backdrop-filter: blur(1.7px);
}

.feature-icon--journey {
    border-color: rgba(255, 190, 88, 0.96);
}

.feature-glow {
    box-shadow:
        inset 0 0 10.425px rgba(0, 198, 222, 0.25),
        inset 0.141px 0.281px 0.281px 0.141px rgba(255, 255, 255, 0.22),
        inset -0.113px -0.281px 0.084px 0.281px rgba(255, 255, 255, 0.05);
}

.feature-glow--journey {
    box-shadow:
        inset 0 0 7.05px rgba(255, 190, 88, 0.1),
        inset 0.141px 0.281px 0.281px 0.141px rgba(255, 255, 255, 0.22),
        inset -0.113px -0.281px 0.084px 0.281px rgba(255, 255, 255, 0.05);
}

.numbered-section {
    display: grid;
    gap: 0.75rem;
}

.section-number {
    font-family: 'Abhaya Libre', serif;
    font-size: 4rem;
    font-weight: 600;
    line-height: 1;
    color: #59c1ca;
}

.numbered-section h2 {
    font-size: 1.375rem;
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

@media (min-width: 48rem) {
    .feature-swiper :deep(.swiper-slide:not(:last-child)) {
        border-right: 1px solid rgba(255, 255, 255, 0.14);
    }

    .feature-swiper :deep(.swiper-pagination) {
        display: none;
    }

    .feature-card {
        min-height: 13.1875rem;
        justify-content: center;
    }

    .numbered-section {
        grid-template-columns: 6.375rem minmax(0, 1fr);
        gap: 0.625rem;
        align-items: start;
    }

    .section-number {
        display: flex;
        height: 5.1875rem;
        align-items: center;
        justify-content: center;
        font-size: 5rem;
        line-height: 2.8125rem;
        text-align: center;
    }

    .numbered-section h2 {
        font-size: 1.5rem;
        line-height: 2.0625rem;
    }
}
</style>
