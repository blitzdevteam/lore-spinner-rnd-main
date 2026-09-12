<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import WorldCard from '@/components/WorldCard.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import type { WorldInterface } from '@/types';
import { index as worldsIndex } from '@/wayfinder/routes/worlds';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const HOMEPAGE_WORLD_LIMIT = 3;
/** Match desktop hover preview: snap-focus expand below this. */
const WORLDS_SLIDER_QUERY = '(max-width: 1023px)';

const props = withDefaults(
    defineProps<{
        worlds?: WorldInterface[];
    }>(),
    {
        worlds: () => [],
    },
);

const isDesktopHover = useDesktopStoryPreview();
const {
    onCardEnter,
    onCardLeave,
    isExpanded: isHoverExpanded,
    isDimmed: isHoverDimmed,
} = useStoryCardExpand(isDesktopHover);

const sliderEl = ref<HTMLElement | null>(null);
const isMobileSlider = ref(false);
const activeMobileId = ref<string | null>(null);

const previewWorlds = computed(() => props.worlds.slice(0, HOMEPAGE_WORLD_LIMIT));
const viewAllHref = computed(() => worldsIndex().url);

function worldCardId(id: number): string {
    return String(id);
}

function isExpanded(id: string): boolean {
    if (isDesktopHover.value) {
        return isHoverExpanded(id);
    }

    if (isMobileSlider.value) {
        return activeMobileId.value === id;
    }

    return false;
}

function isDimmed(id: string): boolean {
    if (isDesktopHover.value) {
        return isHoverDimmed(id);
    }

    return false;
}

function syncActiveMobileCard(): void {
    const slider = sliderEl.value;
    if (!slider || !isMobileSlider.value) {
        return;
    }

    const slots = [...slider.querySelectorAll<HTMLElement>('[data-world-id]')];
    if (slots.length === 0) {
        activeMobileId.value = null;
        return;
    }

    const scrollLeft = slider.scrollLeft;
    let bestId = slots[0]?.dataset.worldId ?? null;
    let bestDist = Number.POSITIVE_INFINITY;

    for (const slot of slots) {
        const dist = Math.abs(slot.offsetLeft - scrollLeft);
        if (dist < bestDist) {
            bestDist = dist;
            bestId = slot.dataset.worldId ?? null;
        }
    }

    activeMobileId.value = bestId;
}

function onSliderScroll(): void {
    if (!isMobileSlider.value) {
        return;
    }

    syncActiveMobileCard();
}

function seedActiveMobileCard(): void {
    const first = previewWorlds.value[0];
    activeMobileId.value = first ? worldCardId(first.id) : null;
    void nextTick(() => {
        syncActiveMobileCard();
    });
}

let sliderMql: MediaQueryList | null = null;

function syncSliderMode(): void {
    const next = sliderMql?.matches ?? false;
    isMobileSlider.value = next;

    if (next) {
        seedActiveMobileCard();
    } else {
        activeMobileId.value = null;
    }
}

watch(previewWorlds, () => {
    if (isMobileSlider.value) {
        seedActiveMobileCard();
    }
});

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    sliderMql = window.matchMedia(WORLDS_SLIDER_QUERY);
    syncSliderMode();
    sliderMql.addEventListener('change', syncSliderMode);
});

onUnmounted(() => {
    sliderMql?.removeEventListener('change', syncSliderMode);
});
</script>

<template>
    <section v-if="previewWorlds.length" class="home-section-y overflow-visible">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Step into Worlds"
                    subtitle="Choose a world to enter. Each has its own stories, and they remember who you become."
                    :href="viewAllHref"
                />

                <div class="worlds-slider-shell">
                    <div ref="sliderEl" class="worlds-row" @scroll.passive="onSliderScroll">
                        <div
                            v-for="world in previewWorlds"
                            :key="world.id"
                            class="world-card-slot"
                            :data-world-id="worldCardId(world.id)"
                            @mouseenter="onCardEnter(worldCardId(world.id))"
                            @mouseleave="onCardLeave()"
                        >
                            <WorldCard
                                :world="world"
                                :expanded="isExpanded(worldCardId(world.id))"
                                :dimmed="isDimmed(worldCardId(world.id))"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.worlds-slider-shell {
    position: relative;
    width: 100%;
    min-width: 0;
}

/* Mobile / tablet: horizontal snap slider (Figma 9645:2999) */
.worlds-row {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.625rem;
    overflow-x: auto;
    overflow-y: visible;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -ms-overflow-style: none;
    margin-inline: -0.25rem;
    padding-inline: 0.25rem;
    padding-block: 0.25rem 0.5rem;
}

.worlds-row::-webkit-scrollbar {
    display: none;
}

.world-card-slot {
    position: relative;
    z-index: 1;
    display: flex;
    height: 18.125rem;
    width: 18.75rem;
    flex-shrink: 0;
    scroll-snap-align: start;
    overflow: visible;
    transition: z-index 0s linear 480ms;
}

.world-card-slot:has(.world-card--expanded) {
    z-index: 5;
    transition: z-index 0s;
}

/* Desktop: wrapping row, hover expands */
@media (min-width: 1024px) {
    .worlds-row {
        flex-wrap: wrap;
        overflow: visible;
        scroll-snap-type: none;
        margin-inline: 0;
        padding: 0;
    }

    .world-card-slot {
        width: 20.75rem;
        scroll-snap-align: unset;
    }
}
</style>
