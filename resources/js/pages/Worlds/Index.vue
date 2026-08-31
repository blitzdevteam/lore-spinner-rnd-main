<script setup lang="ts">
import LibrarySortMenu, { type LibrarySortMode } from '@/components/LibrarySortMenu.vue';
import WorldCard from '@/components/WorldCard.vue';
import WorldsSearchField from '@/components/WorldsSearchField.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { compareWorldsByPlayableOrder } from '@/data/playableStorySlugs';
import HomeLayout from '@/layouts/HomeLayout.vue';
import type { WorldInterface } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        worlds?: WorldInterface[];
    }>(),
    {
        worlds: () => [],
    },
);

const searchQuery = ref('');
const sortMode = ref<LibrarySortMode>('recent');
const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

const filteredWorlds = computed(() => {
    const needle = searchQuery.value.trim().toLowerCase();

    if (needle === '') {
        return props.worlds;
    }

    return props.worlds.filter((world) => worldMatchesSearch(world, needle));
});

const visibleWorlds = computed(() => {
    const list = [...filteredWorlds.value];

    if (sortMode.value === 'title_asc') {
        list.sort((a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }));
    } else if (sortMode.value === 'title_desc') {
        list.sort((a, b) => b.title.localeCompare(a.title, undefined, { sensitivity: 'base' }));
    } else if (sortMode.value === 'playable') {
        list.sort(compareWorldsByPlayableOrder);
    }

    return list;
});

const headerTitle = computed(() => `Step into worlds ( ${visibleWorlds.value.length} )`);

function worldMatchesSearch(world: WorldInterface, needle: string): boolean {
    if (world.title.toLowerCase().includes(needle)) {
        return true;
    }

    if (world.tagline?.toLowerCase().includes(needle)) {
        return true;
    }

    if (world.tags.some((tag) => tag.toLowerCase().includes(needle))) {
        return true;
    }

    return (world.stories ?? []).some((story) => story.title.toLowerCase().includes(needle));
}

function worldCardId(id: number): string {
    return String(id);
}
</script>

<template>
    <HomeLayout>
        <Head title="Worlds" />

        <main class="bg-black pt-6 pb-12 text-white md:pt-10 md:pb-[3.75rem]">
            <div class="container">
                <div class="container-content flex min-w-0 flex-col gap-10">
                    <WorldsSearchField v-model="searchQuery" />

                    <div class="flex min-w-0 flex-col gap-5 md:gap-[1.125rem]">
                        <header class="flex w-full min-w-0 flex-col gap-0">
                            <div class="flex w-full min-w-0 items-center justify-between gap-4">
                                <h1 class="home-section-title min-w-0 flex-1">{{ headerTitle }}</h1>
                                <LibrarySortMenu v-model="sortMode" items-label="worlds" />
                            </div>
                            <p class="home-section-description mt-2.5 max-w-none text-[#8f8f8f]">
                                Choose a world to enter. Each one contains its own stories, tone, and memory.
                            </p>
                        </header>

                        <div v-if="visibleWorlds.length" class="grid grid-cols-1 items-start gap-2.5 overflow-visible sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="world in visibleWorlds"
                                :key="world.id"
                                class="relative z-[1] min-w-0 overflow-visible has-[.world-card--expanded]:z-[5]"
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
                        <p v-else-if="searchQuery.trim()" class="text-[#8f8f8f]">No worlds match your search.</p>
                        <p v-else class="text-[#8f8f8f]">No worlds are available yet.</p>
                    </div>
                </div>
            </div>
        </main>
    </HomeLayout>
</template>
