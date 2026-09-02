<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import MoodPhotoCardFace from '@/components/MoodPhotoCardFace.vue';
import { MOOD_PHOTO_CARD_CONFIGS } from '@/data/moodPhotoCards';
import { MOOD_BANNER_CONFIGS, MOOD_IDS, type MoodId } from '@/data/moodBanners';
import { MOCK_LIBRARY_STORIES } from '@/data/mockLibraryStories';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { getMoodStorySlugs } from '@/data/moodStories';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { computed, onUnmounted, ref, watch } from 'vue';

interface MoodGame {
    id: string;
    title: string;
    cover: string;
    playable: boolean;
    slug: string;
    themes: string[];
    teaser: string;
}

const mockBySlug = Object.fromEntries(MOCK_LIBRARY_STORIES.map((s) => [s.slug, s]));

function gameFromSlug(slug: string): MoodGame | null {
    const mock = mockBySlug[slug];
    if (!mock) return null;

    return {
        id: slug,
        slug,
        title: mock.title,
        cover: resolveStoryCover(slug, mock.cover),
        playable: isStoryPlayable(slug),
        themes: getStoryDescriptorThemes(slug),
        teaser: mock.teaser,
    };
}

const MOOD_ACCENT_RGB: Record<MoodId, string> = {
    heartfelt: '201, 52, 52',
    adventurous: '236, 200, 99',
    mysterious: '98, 232, 219',
    epic: '88, 217, 161',
    whimsical: '169, 121, 194',
};

const moodConfigs = MOOD_IDS.map((id) => ({
    id,
    label: MOOD_BANNER_CONFIGS[id].label,
    accentRgb: MOOD_ACCENT_RGB[id],
    blurb: MOOD_BANNER_CONFIGS[id].subtitle,
    storySlugs: getMoodStorySlugs(id),
}));

const openMoodId = ref<MoodId | null>(null);
const moodScrollEl = ref<HTMLElement | null>(null);
const moodSliderEl = ref<HTMLElement | null>(null);

const isPanelOpen = computed(() => openMoodId.value !== null);

const { leftShadowVisible: moodLeftShadow, rightShadowVisible: moodRightShadow } = useSliderEdgeShadows(moodScrollEl);
const { leftShadowVisible: panelLeftShadow, rightShadowVisible: panelRightShadow } = useSliderEdgeShadows(moodSliderEl, { enabled: isPanelOpen });

const activeMoodConfig = computed(() => moodConfigs.find((m) => m.id === openMoodId.value) ?? null);

const panelGames = computed((): MoodGame[] => {
    if (!activeMoodConfig.value) return [];
    return activeMoodConfig.value.storySlugs.map((slug) => gameFromSlug(slug)).filter((game): game is MoodGame => game != null);
});

function openMood(id: MoodId) {
    openMoodId.value = id;
}

function closeMood() {
    openMoodId.value = null;
}

function scrollMoodGames(delta: number) {
    moodSliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });
}

function formatThemes(themes: string[]): string {
    return themes.join(' · ');
}

function onBackdropKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        closeMood();
    }
}

watch(isPanelOpen, (open) => {
    if (typeof document === 'undefined') return;
    if (open) {
        document.body.classList.add('overflow-hidden');
        window.addEventListener('keydown', onBackdropKeydown);
    } else {
        document.body.classList.remove('overflow-hidden');
        window.removeEventListener('keydown', onBackdropKeydown);
    }
});

onUnmounted(() => {
    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onBackdropKeydown);
});
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader title="Explore by mood" subtitle="Choose the feeling you want to step into." />

                <!-- Figma 5412:908: flex row, gap exactly 10px (not stretched 1fr columns) -->
                <div class="relative">
                    <div
                        ref="moodScrollEl"
                        class="mood-scroll flex w-full max-w-full flex-nowrap items-center gap-[0.625rem] overflow-x-auto pb-1 md:w-max md:overflow-visible"
                    >
                        <!-- Figma 5412:903: background photo + gradient + bottom-left icon badge/label -->
                        <button
                            v-for="card in MOOD_PHOTO_CARD_CONFIGS"
                            :key="card.id"
                            type="button"
                            class="mood-card relative z-0 h-[5.625rem] w-[12.1875rem] shrink-0 cursor-pointer overflow-hidden rounded-[0.625rem] border-0 bg-transparent p-0 text-left transition-[transform,opacity] duration-200 hover:scale-[1.02] active:scale-[0.99]"
                            :class="{
                                'mood-card--active': openMoodId === card.id,
                                'opacity-50': openMoodId !== null && openMoodId !== card.id,
                            }"
                            :style="{ '--mood-accent': card.accent }"
                            :aria-expanded="openMoodId === card.id"
                            aria-controls="mood-explore-panel"
                            @click="openMood(card.id)"
                        >
                            <MoodPhotoCardFace :config="card" />
                        </button>
                    </div>

                    <!-- Edge fades — only needed when chips row scrolls (narrow viewports); hidden on md+ -->
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-6 bg-gradient-to-r from-black/70 to-transparent transition-opacity duration-300 md:hidden"
                        :class="moodLeftShadow ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-black to-transparent transition-opacity duration-300 md:hidden"
                        :class="moodRightShadow ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                </div>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="mood-overlay">
                <div
                    v-if="activeMoodConfig"
                    id="mood-explore-panel"
                    class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="mood-panel-title"
                >
                    <div class="absolute inset-0 bg-[rgba(0,0,0,0.78)] backdrop-blur-md" @click="closeMood" />
                    <Transition name="mood-sheet" appear>
                        <div
                            v-if="activeMoodConfig"
                            class="relative z-10 flex max-h-[min(92vh,880px)] w-full max-w-5xl flex-col overflow-hidden rounded-t-[1.25rem] border border-white/10 bg-[#0c0c0c] shadow-[0_-8px_60px_rgba(0,0,0,0.65)] sm:rounded-2xl"
                            :style="{
                                boxShadow: `0 0 0 1px rgba(${activeMoodConfig.accentRgb}, 0.2), 0 24px 80px rgba(0,0,0,0.55), 0 0 120px rgba(${activeMoodConfig.accentRgb}, 0.12)`,
                            }"
                            @click.stop
                        >
                            <div
                                class="pointer-events-none absolute inset-x-0 top-0 h-32 opacity-90"
                                :style="{
                                    background: `radial-gradient(ellipse 90% 100% at 50% -20%, rgba(${activeMoodConfig.accentRgb}, 0.35), transparent 72%)`,
                                }"
                            />
                            <div class="relative flex items-start justify-between gap-4 border-b border-white/10 px-5 pt-5 pb-4 sm:px-8 sm:pt-7">
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <p class="text-[0.6875rem] font-semibold tracking-[0.2em] text-white/55 uppercase">Explore by mood</p>
                                    <h3
                                        id="mood-panel-title"
                                        class="text-2xl leading-tight font-bold tracking-tight text-white uppercase sm:text-[1.75rem]"
                                    >
                                        {{ activeMoodConfig.label }}
                                    </h3>
                                    <p class="max-w-xl text-[0.9375rem] leading-relaxed text-[#a8a8a8]">
                                        {{ activeMoodConfig.blurb }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <Link
                                        :href="`${storiesIndex().url}?mood=${activeMoodConfig.id}`"
                                        class="hidden rounded-lg border border-primary/50 bg-primary/10 px-3 py-2 text-sm font-medium text-primary hover:bg-primary/20 sm:inline-block"
                                        @click="closeMood"
                                    >
                                        Browse library
                                    </Link>
                                    <button
                                        type="button"
                                        class="flex size-10 items-center justify-center rounded-full border border-white/15 bg-white/5 text-white transition hover:bg-white/12"
                                        aria-label="Close"
                                        @click="closeMood"
                                    >
                                        <X class="size-5" :stroke-width="2" />
                                    </button>
                                </div>
                            </div>

                            <div class="relative flex flex-1 flex-col gap-4 overflow-hidden px-3 pt-2 pb-5 sm:px-8 sm:pt-4 sm:pb-8">
                                <div class="relative">
                                    <div
                                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-6 bg-gradient-to-r from-[#0c0c0c] to-transparent transition-opacity duration-300 sm:left-[-0.125rem] sm:w-14"
                                        :class="panelLeftShadow ? 'opacity-100' : 'opacity-0'"
                                        aria-hidden="true"
                                    />
                                    <div
                                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-[#0c0c0c] to-transparent transition-opacity duration-300 sm:right-[-0.125rem] sm:w-14"
                                        :class="panelRightShadow ? 'opacity-100' : 'opacity-0'"
                                        aria-hidden="true"
                                    />
                                    <button
                                        type="button"
                                        class="slider-arrow absolute top-[8.75rem] -left-1 z-10 hidden sm:flex"
                                        aria-label="Scroll picks left"
                                        @click="scrollMoodGames(-228)"
                                    >
                                        <span class="slider-arrow-icon rotate-180">&#8250;</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="slider-arrow absolute top-[8.75rem] -right-1 z-10 hidden sm:flex"
                                        aria-label="Scroll picks right"
                                        @click="scrollMoodGames(228)"
                                    >
                                        <span class="slider-arrow-icon">&#8250;</span>
                                    </button>

                                    <div
                                        ref="moodSliderEl"
                                        class="mood-game-slider flex items-stretch gap-4 overflow-x-auto px-2 pt-1 pb-2 sm:gap-5 sm:px-1"
                                    >
                                        <article
                                            v-for="game in panelGames"
                                            :key="game.id"
                                            class="group flex w-[min(192px,78vw)] shrink-0 flex-col gap-2.5"
                                        >
                                            <component
                                                :is="game.playable ? Link : 'div'"
                                                :href="game.playable && game.slug ? storyShow(game.slug).url : undefined"
                                                class="block outline-none"
                                                @click="game.playable && closeMood()"
                                            >
                                                <div
                                                    class="relative aspect-[192/287] w-full overflow-hidden rounded-xl border border-white/10 bg-[#1a1a1a] shadow-lg ring-1 ring-black/40 transition duration-300 group-hover:border-primary/40 group-hover:ring-primary/20"
                                                >
                                                    <img
                                                        :src="game.cover"
                                                        :alt="game.title"
                                                        class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                                    />
                                                    <div
                                                        class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-80"
                                                    />
                                                </div>
                                            </component>
                                            <div class="flex min-h-0 flex-1 flex-col gap-1.5 px-0.5">
                                                <p class="truncate text-base font-semibold text-white">
                                                    {{ game.title }}
                                                </p>
                                                <p class="line-clamp-2 min-h-[2.75rem] text-[0.8125rem] leading-snug text-[#5fd4e8]">
                                                    {{ formatThemes(game.themes) }}
                                                </p>
                                                <p class="line-clamp-2 min-h-[2.75rem] flex-1 text-[0.8125rem] leading-relaxed text-[#8a8a8a]">
                                                    {{ game.teaser }}
                                                </p>
                                                <div class="mt-auto shrink-0 pt-1">
                                                    <Link
                                                        v-if="game.playable && game.slug"
                                                        :href="storyShow(game.slug).url"
                                                        class="inline-flex h-[2.125rem] w-full items-center justify-center rounded-[0.3125rem] border-[0.5px] border-solid border-[#59c1ca] bg-[#0e1f22] px-2.5 font-['Inter',sans-serif] text-[0.9rem] leading-none font-normal text-[#f3f4ee] no-underline transition-[border-color,background-color,transform] duration-[180ms] ease-in-out hover:-translate-y-px hover:border-[#8fcbd3] hover:bg-[#163f46] active:translate-y-0 active:bg-cta-active"
                                                        @click="closeMood"
                                                    >
                                                        Play
                                                    </Link>
                                                    <div
                                                        v-else
                                                        class="flex h-[2.125rem] w-full items-center justify-center rounded-[0.3125rem] border-[0.5px] border-solid border-[#8f8f8f] bg-transparent px-2.5 font-['Inter',sans-serif] text-[0.9rem] leading-none font-normal text-[#8f8f8f]"
                                                    >
                                                        Coming Soon
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    </div>
                                </div>

                                <Link
                                    :href="`${storiesIndex().url}?mood=${activeMoodConfig.id}`"
                                    class="mx-auto flex h-11 w-full max-w-md items-center justify-center rounded-xl border border-primary/35 bg-primary/10 text-sm font-semibold text-primary sm:hidden"
                                    @click="closeMood"
                                >
                                    Browse full library for this mood
                                </Link>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>

<style scoped>
.mood-scroll::-webkit-scrollbar {
    display: none;
}

.mood-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.mood-game-slider {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.mood-game-slider::-webkit-scrollbar {
    display: none;
}

.slider-arrow {
    display: flex;
    width: 2.375rem;
    height: 2.375rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transform: translateY(-50%);
    transition: background 0.2s;
}
.slider-arrow:hover {
    background: rgba(255, 255, 255, 0.18);
}
.slider-arrow-icon {
    font-size: 1.375rem;
    line-height: 1;
    color: white;
}

/* Figma mood tiles: background photo, gradient legibility overlay, accent border on hover/active. */
.mood-card {
    background-color: #000;
    border: 1px solid transparent;
    box-shadow: none;
    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease;
}

.mood-card:hover,
.mood-card.mood-card--active {
    border-color: var(--mood-accent);
    box-shadow:
        0px 4px 5rem 0px rgba(0, 0, 0, 0.2),
        0 0 1.25rem color-mix(in srgb, var(--mood-accent) 35%, transparent);
}

.mood-overlay-enter-active,
.mood-overlay-leave-active {
    transition: opacity 0.28s ease;
}
.mood-overlay-enter-from,
.mood-overlay-leave-to {
    opacity: 0;
}

.mood-sheet-enter-active {
    transition:
        opacity 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.36s cubic-bezier(0.22, 1, 0.36, 1);
}
.mood-sheet-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.22s ease;
}
.mood-sheet-enter-from {
    opacity: 0;
    transform: translateY(1.5rem) scale(0.98);
}
.mood-sheet-leave-to {
    opacity: 0;
    transform: translateY(1rem);
}
@media (max-width: 39.9375rem) {
    .mood-sheet-enter-from {
        transform: translateY(100%);
    }
    .mood-sheet-leave-to {
        transform: translateY(40%);
    }
}
</style>
