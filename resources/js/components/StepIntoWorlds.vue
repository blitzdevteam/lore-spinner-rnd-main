<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import WorldCard from '@/components/WorldCard.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import type { WorldInterface } from '@/types';
import { index as worldsIndex } from '@/wayfinder/routes/worlds';
import { computed } from 'vue';

const HOMEPAGE_WORLD_LIMIT = 3;

const props = withDefaults(
    defineProps<{
        worlds?: WorldInterface[];
        worldsCount?: number;
    }>(),
    {
        worlds: () => [],
        worldsCount: 0,
    },
);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);
const previewWorlds = computed(() => props.worlds.slice(0, HOMEPAGE_WORLD_LIMIT));
const viewAllCount = computed(() => props.worldsCount || props.worlds.length);

function worldCardId(id: number): string {
    return String(id);
}
</script>

<template>
    <section v-if="previewWorlds.length" class="home-section-y overflow-visible">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Step into Worlds"
                    subtitle="Choose a world to enter. Each has its own stories, and they remember who you become."
                    :href="worldsIndex().url"
                    :count="viewAllCount"
                />

                <div class="worlds-row">
                    <div
                        v-for="world in previewWorlds"
                        :key="world.id"
                        class="world-card-slot"
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
    </section>
</template>

<style scoped>
.worlds-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.625rem;
    overflow: visible;
}

.world-card-slot {
    position: relative;
    z-index: 1;
    display: flex;
    height: 18.125rem;
    width: min(20.75rem, 86vw);
    overflow: hidden;
    transition: z-index 0s linear 480ms;
}

.world-card-slot:has(.world-card--expanded) {
    z-index: 5;
    transition: z-index 0s;
}

@media (min-width: 768px) {
    .world-card-slot {
        width: 20.75rem;
    }
}
</style>
