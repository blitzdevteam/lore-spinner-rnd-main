<script setup lang="ts">
type Panel = 'details' | 'chapters';

const props = defineProps<{
    modelValue: Panel;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Panel];
}>();

function select(value: Panel): void {
    emit('update:modelValue', value);
}

const tabs: { id: Panel; label: string }[] = [
    { id: 'details', label: 'Details' },
    { id: 'chapters', label: 'Episodes' },
];

function isActive(id: Panel): boolean {
    return props.modelValue === id;
}
</script>

<template>
    <div
        class="story-play-tablist inline-flex items-center rounded-full border border-solid border-[#3a3a3a] p-1"
        role="tablist"
        aria-label="Story sections"
    >
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="isActive(tab.id)"
            class="story-play-tab relative box-border flex h-11 shrink-0 items-center justify-center rounded-full border border-solid px-7 font-['Inter',sans-serif] text-base font-medium outline-none transition-[color,background-color,border-color] duration-200 ease-out focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-black sm:px-8"
            :class="isActive(tab.id) ? 'story-play-tab--active' : 'story-play-tab--inactive'"
            @click="select(tab.id)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>

<style scoped>
.story-play-tab--inactive {
    border-color: transparent;
    background: transparent;
    color: #8d8d8d;
}

.story-play-tab--inactive:hover {
    color: #c8c8c8;
}

.story-play-tab--active {
    border-color: #5c5c5c;
    background: #1a1a1a;
    color: #fff;
    box-shadow: inset 0 0.5px 0 rgba(255, 255, 255, 0.08);
}
</style>
