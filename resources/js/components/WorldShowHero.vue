<script setup lang="ts">
import backArrow from '@/assets/worlds/back-arrow.svg';
import type { WorldInterface } from '@/types';
import { index as worldsIndex } from '@/wayfinder/routes/worlds';
import { Link } from '@inertiajs/vue3';

defineProps<{
    world: WorldInterface;
}>();
</script>

<template>
    <section class="world-show-hero relative flex min-h-[16rem] w-full flex-col justify-center overflow-hidden rounded-[10px] px-6 py-8 md:min-h-[23.0625rem] md:py-10">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <img
                v-if="world.background"
                :src="world.background"
                :alt="world.title"
                class="absolute inset-0 size-full object-cover"
            />
            <div class="world-show-hero__shade absolute inset-0" />
        </div>

        <div class="relative z-[1] flex max-w-[21.0625rem] flex-col gap-6">
            <div class="flex flex-col gap-3">
                <Link :href="worldsIndex().url" class="inline-flex h-[1.875rem] items-center gap-1.5 text-[0.8125rem] font-medium text-[#ffbe58]">
                    <span class="flex h-3 w-[0.46875rem] shrink-0 items-center justify-center overflow-clip">
                        <img :src="backArrow" alt="" width="8" height="12" class="size-full" />
                    </span>
                    BACK TO THE WORLDS
                </Link>

                <div class="flex flex-col gap-2">
                    <h1 class="font-marcellus-sc text-[2.25rem] leading-none text-white md:text-[2.75rem]">
                        {{ world.title }}
                    </h1>
                    <p v-if="world.tagline" class="text-[0.8125rem] font-light text-[#ff3a3a]">
                        {{ world.tagline }}
                    </p>
                    <p v-if="world.description" class="text-sm font-normal text-white">
                        {{ world.description }}
                    </p>
                </div>
            </div>

            <div v-if="world.tags.length" class="flex flex-wrap items-center gap-2">
                <span
                    v-for="tag in world.tags"
                    :key="tag"
                    class="flex h-[1.875rem] items-center rounded-[5px] border-[0.5px] border-[#373737] bg-black px-2 text-[0.8125rem] font-light text-[#8f8f8f]"
                >
                    {{ tag }}
                </span>
            </div>
        </div>
    </section>
</template>

<style scoped>
.world-show-hero__shade {
    background-image: linear-gradient(105deg, rgba(0, 0, 0, 0.9) 5.77%, rgba(0, 0, 0, 0) 55.11%);
}
</style>
