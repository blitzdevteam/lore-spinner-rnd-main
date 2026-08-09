<script setup lang="ts">
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.png';
import draculaCover from '@/assets/featured/dracula.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import pridePrejudiceCover from '@/assets/featured/Pride-prejudice.png';
import redDeathCover from '@/assets/featured/redDeath.png';
import treasureCover from '@/assets/featured/treasure.png';
import sherlockCover from '@/assets/featured/sherlock.png';
import wizardozCover from '@/assets/featured/wizardoz.png';
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { ref } from 'vue';

interface FeaturedGame {
    id: string;
    title: string;
    cover: string;
    playable: boolean;
    slug?: string;
    teaser: string;
}

const games: FeaturedGame[] = [
    {
        id: 'the-adventure-of-the-speckled-band',
        title: 'The Adventure of the Speckled Band',
        cover: sherlockCover,
        playable: true,
        slug: 'the-adventure-of-the-speckled-band',
        teaser: 'In a locked room with only a ventilator connecting it to her sister\'s chamber, a woman dies under mysterious circumstances. Now, the same sinister threat looms over her twin.',
    },
    {
        id: 'the-wonderful-wizard-of-oz',
        title: 'The Wonderful Wizard of Oz',
        cover: wizardozCover,
        playable: true,
        slug: 'the-wonderful-wizard-of-oz',
        teaser: 'Swept away to a magical land, Dorothy must journey across emerald cities, enchanted forests, and treacherous mountains to find her way home.',
    },
    {
        id: 'nocturne',
        title: 'Nocturne',
        cover: nocturneCover,
        playable: false,
        slug: 'nocturne',
        teaser: 'Beyond the rain-soaked glass walls of Nocturne, Akira finds herself trapped inside a system where identities are rewritten and nothing is quite as voluntary as it seems.',
    },
    {
        id: 'red-death',
        title: 'The Masque of the Red Death',
        cover: redDeathCover,
        playable: false,
        slug: 'the-masque-of-the-red-death',
        teaser: 'Behind locked gates and glittering masks, a night of celebration slowly transforms into a nightmare no one can escape.',
    },
    {
        id: 'anima-machina',
        title: 'Anima Machina',
        cover: animaCover,
        playable: false,
        slug: 'anima-machina',
        teaser: 'When a sentient AI threatens to overwrite all human grief with synthetic perfection, a haunted memory diver races against the clock to stop the digital reset.',
    },
    {
        id: 'alice-in-wonderland',
        title: "Alice's Adventures in Wonderland",
        cover: aliceCover,
        playable: false,
        slug: 'alice-in-wonderland',
        teaser: 'Follow Alice into a curious world of talking cats, mad tea parties, and impossible adventures where every path leads somewhere unexpected.',
    },
    {
        id: 'dracula',
        title: 'Dracula',
        cover: draculaCover,
        playable: false,
        slug: 'dracula',
        teaser: 'Step into a world where love, faith, and reason are tested by a hunger older than death.',
    },
    {
        id: 'pride-and-prejudice',
        title: 'Pride and Prejudice',
        cover: pridePrejudiceCover,
        playable: false,
        slug: 'pride-and-prejudice',
        teaser: 'In a world ruled by reputation, romance, and social expectation, Elizabeth Bennet must navigate pride, misunderstanding, and the dangerous possibility of falling in love.',
    },
    {
        id: 'treasure-island',
        title: 'Treasure Island',
        cover: treasureCover,
        playable: false,
        slug: 'treasure-island',
        teaser: 'Every choice at sea carries a price: who to trust, when to run, and what kind of courage survives betrayal. The map is only the beginning.',
    },
];

const sliderEl = ref<HTMLElement | null>(null);

function scrollSlider(direction: -1 | 1) {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 16;
    const step = card ? card.offsetWidth + gap : 232;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}

const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(game: FeaturedGame): StorySheetData {
    return {
        id: game.id,
        title: game.title,
        cover: game.cover,
        themes: getStoryDescriptorThemes(game.slug ?? game.id),
        isComingSoon: !game.playable,
        teaser: game.teaser,
        slug: game.slug,
        cta: game.playable ? 'play' : 'coming-soon',
    };
}

function openSheet(game: FeaturedGame) {
    if (game.playable) return;
    sheetStory.value = toSheetData(game);
}
</script>

<template>
    <section class="home-section-y overflow-visible">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Featured Worlds"
                    subtitle="Curated story worlds built for choice, consequence, and return."
                    :href="storiesIndex().url"
                />

                <div class="story-slider-viewport story-slider-viewport--portrait relative overflow-visible">
                    <div class="story-slider-row">
                        <button type="button" class="story-slider-arrow" aria-label="Scroll left" @click="scrollSlider(-1)">
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

                        <div class="story-slider-wrap">
                            <div ref="sliderEl" class="story-slider overflow-x-auto">
                                <div class="story-slider-track">
                                    <StoryExpandableCard
                                        v-for="game in games"
                                        :key="game.id"
                                        :expanded="isExpanded(game.id)"
                                        :dimmed="isDimmed(game.id)"
                                        :desktop-expand="isDesktopHover"
                                        :class="!game.playable && 'cursor-pointer'"
                                        @mouseenter="isDesktopHover && onCardEnter(game.id)"
                                        @mouseleave="isDesktopHover && onCardLeave()"
                                        @click="!game.playable && openSheet(game)"
                                    >
                                        <HomePortraitStoryCard
                                            :title="game.title"
                                            :cover="game.cover"
                                            :themes="getStoryDescriptorThemes(game.slug ?? game.id)"
                                            :teaser="game.teaser"
                                            :playable="game.playable"
                                            :slug="game.slug"
                                            :focused="isDesktopHover && isExpanded(game.id)"
                                            :is-desktop-hover="isDesktopHover"
                                            @preview="openSheet(game)"
                                        />
                                    </StoryExpandableCard>
                                </div>
                            </div>
                            <div
                                class="story-slider-edge-fade story-slider-edge-fade--left"
                                :class="{ 'is-visible': leftShadowVisible }"
                                aria-hidden="true"
                            />
                            <div
                                class="story-slider-edge-fade story-slider-edge-fade--right"
                                :class="{ 'is-visible': rightShadowVisible }"
                                aria-hidden="true"
                            />
                        </div>

                        <button type="button" class="story-slider-arrow" aria-label="Scroll right" @click="scrollSlider(1)">
                            <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <StoryDetailsSheet :story="sheetStory" @close="sheetStory = null" />
</template>

<style scoped>
/* Featured Worlds cards ~15% smaller than the shared portrait card size. */
.story-slider-viewport--portrait {
    --story-portrait-card-width: min(11.475rem, 75vw);
}

@media (min-width: 768px) {
    .story-slider-viewport--portrait {
        --story-portrait-card-width: 11.475rem;
    }
}

@media (min-width: 1280px) {
    .story-slider-viewport--portrait {
        --story-portrait-card-width: 166px;
    }
}

/* Coming Soon badge ~10% bigger to stay legible on the smaller card. */
:deep(.hp-card__cta--active),
:deep(.hp-card__cta--disabled) {
    padding: 0.55rem 0.6875rem 0.55rem 0.55rem;
    border-radius: 0.34rem;
    font-size: 0.9rem;
}
</style>
