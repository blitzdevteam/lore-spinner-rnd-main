<script setup lang="ts">
import backChevronIcon from '@/assets/auth/back-chevron.svg';
import { Link } from '@inertiajs/vue3';

defineProps<{
    imageSrc: string;
    panelTitle: string;
    panelSubtitle: string;
    shiftImage?: boolean;
    backHref?: string;
}>();
</script>

<template>
    <div class="relative min-h-svh overflow-x-hidden bg-black text-white">
        <Link
            v-if="backHref"
            :href="backHref"
            aria-label="Go back"
            class="auth-back-btn absolute top-6 left-5 z-20 lg:top-[43px] lg:left-[calc(50%+5.375rem)]"
        >
            <img :src="backChevronIcon" alt="" />
        </Link>

        <div
            class="mx-auto flex min-h-svh w-full max-w-[90rem] flex-col items-center justify-center gap-8 px-5 py-8 lg:flex-row lg:gap-16 lg:px-10 xl:gap-24"
        >
            <div
                class="relative h-[min(52svh,28rem)] w-full max-w-[40.625rem] overflow-hidden rounded-[10px] lg:h-[min(45.25rem,calc(100svh-5rem))] lg:w-[min(40.625rem,46vw)] lg:shrink-0"
            >
                <img
                    :src="imageSrc"
                    alt=""
                    class="pointer-events-none absolute max-w-none"
                    :class="
                        shiftImage
                            ? 'top-0 left-[-18.27%] h-full w-[259.94%]'
                            : 'inset-0 size-full object-cover'
                    "
                />
                <div class="absolute inset-x-0 bottom-0 px-6 pb-6 lg:px-10 lg:pb-10">
                    <p
                        class="font-marcellus-sc text-[1.75rem] leading-none text-white uppercase [text-shadow:0_0_21.2px_#000] lg:text-[2.5rem] lg:leading-[5rem]"
                    >
                        {{ panelTitle }}
                    </p>
                    <p class="font-inter text-base leading-[1.625rem] text-white lg:text-[1.125rem]">
                        {{ panelSubtitle }}
                    </p>
                </div>
            </div>

            <div class="flex w-full max-w-[28.8125rem] flex-col items-center">
                <slot />
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
