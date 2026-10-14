<script setup lang="ts">
import backChevronIcon from '@/assets/auth/back-chevron.svg';
import AuthStoryWall from '@/components/auth/AuthStoryWall.vue';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        backHref?: string;
        mobile?: 'gate' | 'sheet';
    }>(),
    {
        mobile: 'sheet',
    },
);
</script>

<template>
    <div class="relative min-h-svh overflow-x-hidden bg-black text-white">
        <Link
            v-if="backHref"
            :href="backHref"
            aria-label="Go back"
            class="auth-back-btn absolute top-4 left-4 z-30 hidden lg:grid"
        >
            <img :src="backChevronIcon" alt="" />
        </Link>

        <div class="flex min-h-svh w-full">
            <AuthStoryWall />

            <div class="relative z-10 flex min-h-svh flex-1 items-center justify-center px-5 py-20 max-lg:p-0 lg:px-10">
                <div class="auth-frame w-full max-w-[28.8125rem]" :class="`auth-frame--${mobile}`">
                    <div class="auth-frame__panel w-full">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-back-btn {
    display: none;
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

@media (min-width: 1024px) {
    .auth-back-btn {
        display: grid;
    }
}

@media (max-width: 1023px) {
    .auth-frame--gate {
        position: fixed;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 20;
        display: flex;
        max-width: none;
        justify-content: center;
        padding: 0 1.6875rem calc(1.875rem + env(safe-area-inset-bottom));
        pointer-events: none;
    }

    .auth-frame--gate .auth-frame__panel {
        width: min(100%, 21.25rem);
        pointer-events: auto;
        border-radius: 20px;
        background: #000;
        padding: 1.25rem;
    }

    .auth-frame--sheet {
        position: fixed;
        inset: 0;
        z-index: 20;
        display: flex;
        max-width: none;
        align-items: flex-start;
        justify-content: center;
        overflow-y: auto;
        background: rgb(0 0 0 / 0.6);
        padding: 18vh 1.25rem 1.5rem;
    }

    .auth-frame--sheet .auth-frame__panel {
        width: min(100%, 21.25rem);
        border-radius: 20px;
        background: #000;
        padding: 1.25rem;
    }
}
</style>
