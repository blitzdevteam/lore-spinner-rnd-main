<script setup lang="ts">
import metaDurationIcon from '@/assets/story-play/meta-duration.svg';
import metaThemeIcon from '@/assets/story-play/meta-theme.svg';
import { computed } from 'vue';

const props = defineProps<{
    durationLabel?: string | null;
    themes?: string[];
}>();

const themesLabel = computed(() => {
    if (!props.themes?.length) {
        return null;
    }
    return props.themes.join('-');
});
</script>

<template>
    <div class="flex flex-wrap items-end gap-2">
        <div
            v-if="durationLabel"
            class="flex h-[1.375rem] items-center justify-center gap-[0.3307rem]"
        >
            <img
                :src="metaDurationIcon"
                alt=""
                class="size-[1.03125rem] shrink-0"
                width="17"
                height="17"
                aria-hidden="true"
            />
            <span
                class="font-['Inter',sans-serif] text-[0.8125rem] font-semibold not-italic leading-[1.375rem] text-[#373737]"
            >
                {{ durationLabel }}
            </span>
        </div>
        <div
            v-if="themesLabel"
            class="flex h-[1.375rem] items-center justify-center gap-[0.3307rem]"
        >
            <img
                :src="metaThemeIcon"
                alt=""
                class="h-[1.027rem] w-[0.9844rem] shrink-0"
                width="16"
                height="17"
                aria-hidden="true"
            />
            <span
                class="font-['Inter',sans-serif] text-[0.8125rem] font-semibold not-italic leading-[1.375rem] text-[#373737]"
            >
                {{ themesLabel }}
            </span>
        </div>
    </div>
</template>
