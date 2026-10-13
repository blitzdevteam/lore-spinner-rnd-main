<script setup lang="ts">
import backChevronIcon from '@/assets/auth/back-chevron.svg';
import AuthStoryWall from '@/components/auth/AuthStoryWall.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    backHref?: string;
}>();
</script>

<template>
    <div class="relative min-h-svh overflow-x-hidden bg-black text-white">
        <Link
            v-if="backHref"
            :href="backHref"
            aria-label="Go back"
            class="auth-back-btn absolute top-4 left-4 z-30"
        >
            <img :src="backChevronIcon" alt="" />
        </Link>

        <div class="flex min-h-svh w-full">
            <AuthStoryWall />

            <div class="flex min-h-svh flex-1 items-center justify-center px-5 py-20 lg:px-10">
                <div class="w-full max-w-[28.8125rem]">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-back-btn {
    display: grid;
    width: 35px;
    height: 35px;
    place-items: center;
    overflow: hidden;
    border-radius: 27px;
    background: rgba(15, 15, 15, 0.72);
    box-shadow:
        0 4px 80px rgba(0, 0, 0, 0.2),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(3px);
}
</style>
