<script setup lang="ts">
defineProps<{
    label: string;
    error?: string;
}>();
</script>

<template>
    <label class="flex w-full flex-col gap-2.5">
        <span class="font-inter text-sm font-normal text-white">{{ label }}</span>
        <slot />
        <span v-if="error" class="font-inter text-sm text-red-400">{{ error }}</span>
    </label>
</template>
