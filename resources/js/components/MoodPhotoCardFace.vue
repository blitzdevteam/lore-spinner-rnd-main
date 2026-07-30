<script setup lang="ts">
import type { MoodPhotoCardConfig } from '@/data/moodPhotoCards';

defineProps<{
    config: MoodPhotoCardConfig;
}>();
</script>

<template>
    <img :src="config.banner" alt="" class="pointer-events-none absolute inset-0 size-full object-cover" />
    <div
        class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"
        aria-hidden="true"
    />
    <div class="absolute inset-x-0 bottom-0 flex items-center gap-2 px-[0.625rem] pb-[0.8125rem]">
        <span
            class="flex size-[1.875rem] shrink-0 items-center justify-center rounded-full"
            :style="{ backgroundColor: config.badgeBg }"
        >
            <img :src="config.icon" alt="" class="size-[1.125rem] object-contain" :class="config.iconClass" />
        </span>
        <span
            class="text-[0.9375rem] leading-none font-semibold whitespace-nowrap capitalize [text-shadow:0_0_0.8125rem_black]"
            :style="{ color: config.accent }"
        >
            {{ config.label }}
        </span>
    </div>
    <div
        class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
    />
</template>
