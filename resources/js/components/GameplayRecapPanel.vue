<script setup lang="ts">
defineProps<{
    text: string | null;
    loading: boolean;
    error: string | null;
}>();
</script>

<template>
    <div class="flex flex-col justify-center gap-5 py-1">
        <div v-if="loading" class="flex flex-col gap-3" aria-live="polite">
            <div class="h-4 w-11/12 animate-pulse rounded bg-white/10" />
            <div class="h-4 w-full animate-pulse rounded bg-white/10" />
            <div class="h-4 w-9/12 animate-pulse rounded bg-white/10" />
            <p class="pt-2 text-center text-sm text-gray-400">Re-entering the story…</p>
        </div>
        <p v-else-if="error" class="text-center text-sm text-red-300/90" role="alert">
            {{ error }}
        </p>
        <p v-else-if="text" class="text-base leading-7 text-gray-100" aria-live="polite">
            {{ text }}
        </p>
        <p v-else class="text-center text-sm leading-7 text-gray-400">
            Tap Recap whenever you need to find your way back into the story.
        </p>
    </div>
</template>
