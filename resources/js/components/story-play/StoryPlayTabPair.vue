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
        class="story-play-tablist flex gap-2.5"
        role="tablist"
        aria-label="Story sections"
    >
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="isActive(tab.id)"
            class="story-play-tab relative box-border flex h-[2.8125rem] w-[7.8125rem] shrink-0 items-center justify-center overflow-hidden rounded-[1.6875rem] border border-solid outline-none transition-[border-color,color,background-color] duration-200 ease-out focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-black"
            :class="isActive(tab.id) ? 'story-play-tab--active' : 'story-play-tab--inactive'"
            @click="select(tab.id)"
        >
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[inherit] bg-white/[0.04]"
            />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[inherit] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter"
                :class="isActive(tab.id) ? '' : 'backdrop-blur-[3px]'"
            />
            <span
                class="relative z-[1] font-['Inter',sans-serif] text-base font-medium not-italic leading-[1.1859rem]"
            >
                {{ tab.label }}
            </span>
            <span
                v-if="!isActive(tab.id)"
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[inherit]"
                style="
                    box-shadow:
                        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
                        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
                "
            />
        </button>
    </div>
</template>

<style scoped>
.story-play-tablist {
    overflow: visible;
}

.story-play-tab--inactive {
    border-color: transparent;
    color: #fff;
    box-shadow: 0 4px 80px rgba(0, 0, 0, 0.2);
}

.story-play-tab--active {
    border-color: #59c1ca;
    color: #59c1ca;
    box-shadow: none;
}
</style>
