<script setup lang="ts">
defineProps<{
    ariaLabel?: string;
}>();

defineSlots<{
    default(): unknown;
}>();
</script>

<template>
    <button
        type="button"
        class="story-play-glass-btn group relative box-border size-[45px] shrink-0 overflow-hidden rounded-[27px] border border-solid border-transparent bg-[rgba(255,255,255,0.04)] outline-none backdrop-blur-[3px] transition-[transform,box-shadow,border-color,background-color] duration-200 ease-out hover:-translate-y-px focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-black active:translate-y-0"
        :aria-label="ariaLabel"
    >
        <span
            aria-hidden="true"
            class="story-play-glass-btn__fill pointer-events-none absolute inset-0 rounded-[inherit] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter transition-[background-color,opacity] duration-200"
        />
        <span
            class="relative z-[1] flex size-full items-center justify-center [&>svg]:size-5 [&>svg]:text-white/85 [&>svg]:transition-colors [&>svg]:duration-200 group-hover:[&>svg]:text-primary-300"
        >
            <slot />
        </span>
        <span
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 rounded-[inherit] transition-opacity duration-200"
            style="
                box-shadow:
                    inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
                    inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
            "
        />
    </button>
</template>

<style scoped>
.story-play-glass-btn {
    box-shadow: 0 4px 80px rgba(0, 0, 0, 0.2);
}

.story-play-glass-btn:hover {
    border-color: rgba(111, 175, 186, 0.45);
    background: rgba(30, 63, 70, 0.35);
    box-shadow:
        0 1px 3px rgba(0, 0, 0, 0.3),
        0 2px 8px rgba(0, 0, 0, 0.22),
        0 0 10px rgba(111, 175, 186, 0.14);
}

.story-play-glass-btn:hover .story-play-glass-btn__fill {
    background: rgba(32, 68, 76, 0.55);
    opacity: 1;
}

/* Bookmark icon keeps fill on hover when saved */
.story-play-glass-btn:hover :deep(svg.fill-primary-500) {
    fill: var(--color-primary-300);
    color: var(--color-primary-300);
}
</style>
