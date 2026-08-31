<script setup lang="ts">
import sortListAlt from '@/assets/worlds/sort-list-alt.svg';
import { onClickOutside } from '@vueuse/core';
import { ArrowDownUp, LucideCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export type LibrarySortMode = 'playable' | 'recent' | 'title_asc' | 'title_desc';

withDefaults(
    defineProps<{
        variant?: 'text' | 'boxed';
        itemsLabel?: string;
    }>(),
    {
        variant: 'text',
        itemsLabel: 'stories',
    },
);

const sortMode = defineModel<LibrarySortMode>({ required: true });

const SORT_OPTIONS: { value: LibrarySortMode; label: string }[] = [
    { value: 'playable', label: 'Playable' },
    { value: 'recent', label: 'Recent' },
    { value: 'title_asc', label: 'A–Z' },
    { value: 'title_desc', label: 'Z–A' },
];

const menuOpen = ref(false);
const menuWrap = ref<HTMLElement | null>(null);

onClickOutside(menuWrap, () => {
    menuOpen.value = false;
});

const sortLabel = computed(() => SORT_OPTIONS.find((o) => o.value === sortMode.value)?.label ?? 'Recent');

function toggleMenu(): void {
    menuOpen.value = !menuOpen.value;
}

function selectSort(mode: LibrarySortMode): void {
    sortMode.value = mode;
    menuOpen.value = false;
}

function optionClass(mode: LibrarySortMode): string {
    const base =
        'library-sort-option flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors';
    return sortMode.value === mode
        ? `${base} bg-primary/15 text-primary`
        : `${base} text-gray-200 hover:bg-white/8 hover:text-white`;
}
</script>

<template>
    <div ref="menuWrap" class="library-sort-menu relative inline-flex shrink-0">
        <button
            type="button"
            class="library-sort-btn group"
            :class="variant === 'boxed' && 'library-sort-btn--boxed'"
            :aria-expanded="menuOpen"
            aria-haspopup="listbox"
            :aria-label="`Sort ${itemsLabel}. Current: ${sortLabel}.`"
            @click="toggleMenu"
        >
            <span v-if="variant === 'boxed'" class="library-sort-btn__icon-wrap" aria-hidden="true">
                <img :src="sortListAlt" alt="" class="library-sort-btn__icon-img" width="26" height="26" />
            </span>
            <ArrowDownUp v-else class="library-sort-btn__icon" :stroke-width="2.25" aria-hidden="true" />
            <span class="library-sort-btn__label">Sort</span>
            <span class="sr-only"> ({{ sortLabel }})</span>
        </button>

        <div
            v-show="menuOpen"
            class="library-sort-dropdown absolute top-full right-0 z-50 mt-2 min-w-[10.5rem] overflow-hidden rounded-[0.9375rem] p-1.5"
            role="listbox"
            :aria-label="`Sort by. ${sortLabel} selected.`"
        >
            <button
                v-for="option in SORT_OPTIONS"
                :key="option.value"
                type="button"
                role="option"
                :aria-selected="sortMode === option.value"
                :class="optionClass(option.value)"
                @click="selectSort(option.value)"
            >
                <span>{{ option.label }}</span>
                <LucideCheck v-if="sortMode === option.value" class="size-4 shrink-0 text-primary" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.library-sort-btn {
    position: relative;
    display: inline-flex;
    height: calc(1.375rem * 1.1);
    flex-shrink: 0;
    align-items: center;
    gap: 0.3125rem;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    letter-spacing: 0.01em;
    color: var(--color-primary, #00d4aa);
    white-space: nowrap;
    cursor: pointer;
    transition:
        opacity 150ms ease,
        transform 150ms ease;
}

@media (min-width: 768px) {
    .library-sort-btn {
        height: calc(1.625rem * 1.1);
    }
}

.library-sort-btn:hover {
    opacity: 0.8;
}

.library-sort-btn:active {
    opacity: 0.7;
    transform: scale(0.98);
}

.library-sort-btn__icon {
    width: 1em;
    height: 1em;
    flex-shrink: 0;
    transition: transform 150ms ease;
}

.library-sort-menu:has([aria-expanded='true']) .library-sort-btn__icon {
    transform: rotate(180deg);
}

.library-sort-btn--boxed {
    height: 2.375rem;
    width: 6rem;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.5rem;
    background: #0f0f0f;
    font-size: 1.125rem;
    font-weight: 500;
    letter-spacing: 0;
}

@media (min-width: 768px) {
    .library-sort-btn--boxed {
        height: 2.375rem;
    }
}

.library-sort-btn--boxed:hover {
    opacity: 1;
    background: #161616;
}

.library-sort-btn__icon-wrap {
    display: flex;
    width: 1.625rem;
    height: 1.625rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    overflow: clip;
}

.library-sort-btn__icon-img {
    display: block;
    width: 1.625rem;
    height: 1.625rem;
}

.library-sort-btn__label {
    line-height: 1;
}

.library-sort-dropdown {
    background:
        linear-gradient(180deg, rgba(111, 175, 186, 0.2) 1.34%, rgba(102, 102, 102, 0) 12.75%),
        linear-gradient(0deg, rgba(2, 3, 3, 0.58), rgba(2, 3, 3, 0.58)), rgba(23, 26, 27, 0.92);
    box-shadow:
        0 4px 5rem rgba(0, 0, 0, 0.2),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
}
</style>
