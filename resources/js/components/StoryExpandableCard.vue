<script setup lang="ts">
defineProps<{
    expanded: boolean;
    dimmed: boolean;
    desktopExpand: boolean;
}>();
</script>

<template>
    <div
        class="story-card-slot"
        :class="[
            desktopExpand && expanded && 'story-card-slot--focused',
            desktopExpand && dimmed && 'story-card-slot--dimmed',
        ]"
    >
        <slot />
    </div>
</template>

<style scoped>
.story-card-slot {
    position: relative;
    z-index: 1;
    flex-shrink: 0;
    transition:
        transform 220ms cubic-bezier(0.25, 0.1, 0.25, 1),
        opacity 220ms ease,
        filter 220ms ease;
}

.story-card-slot--dimmed {
    opacity: 0.72;
    filter: brightness(0.82);
}

@media (min-width: 1024px) {
    .story-card-slot--focused {
        z-index: 20;
    }
}
</style>
