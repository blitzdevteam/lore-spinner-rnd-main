<script setup lang="ts">
import MoodPhotoCardFace from '@/components/MoodPhotoCardFace.vue';
import { MOOD_PHOTO_CARD_CONFIGS } from '@/data/moodPhotoCards';
import type { MoodId } from '@/data/moodBanners';
import { getMoodNavLinks } from '@/data/moodBanners';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    activeMood: MoodId;
}>();

const moodScrollEl = ref<HTMLElement | null>(null);
const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(moodScrollEl);

const moodLinks = getMoodNavLinks(storiesIndex().url);
</script>

<template>
    <section class="mood-selector" aria-label="Browse by mood">
        <div class="container">
            <div class="container-content">
                <div class="relative">
                    <div
                        ref="moodScrollEl"
                        class="mood-scroll flex w-full max-w-full flex-nowrap items-center gap-[0.625rem] overflow-x-auto pb-1 md:w-max md:overflow-visible"
                    >
                        <!-- Figma 5412:903: background photo + gradient + bottom-left icon badge/label -->
                        <Link
                            v-for="card in MOOD_PHOTO_CARD_CONFIGS"
                            :key="card.id"
                            :href="moodLinks.find((link) => link.slug === card.id)?.href"
                            class="mood-card relative z-0 block h-[5.625rem] w-[12.1875rem] shrink-0 overflow-hidden rounded-[0.625rem] no-underline transition-[transform,opacity] duration-200 hover:scale-[1.02] active:scale-[0.99]"
                            :class="{
                                'mood-card--active': activeMood === card.id,
                                'opacity-50': activeMood !== card.id,
                            }"
                            :style="{ '--mood-accent': card.accent }"
                            :aria-current="activeMood === card.id ? 'page' : undefined"
                            preserve-scroll
                        >
                            <MoodPhotoCardFace :config="card" />
                        </Link>
                    </div>

                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-6 bg-gradient-to-r from-black/70 to-transparent transition-opacity duration-300 md:hidden"
                        :class="leftShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-black to-transparent transition-opacity duration-300 md:hidden"
                        :class="rightShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.mood-selector {
    margin-bottom: -30px;
    position: relative;
    z-index: 10;
}

.mood-card--active {
    outline: 1px solid var(--mood-accent);
    outline-offset: 2px;
}
</style>
