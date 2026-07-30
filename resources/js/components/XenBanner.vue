<script setup lang="ts">
import xenBannerBg from '@/assets/story/xen.png';
</script>

<template>
    <!-- Narrow vertical band between carousels; keeps section rhythm without doubling 60px gaps. -->
    <section class="py-9 md:py-10">
        <div class="container">
            <div class="container-content">
                <div
                    class="xen-banner relative flex w-full items-center justify-center overflow-hidden rounded-[0.875rem] border border-solid border-white/15 p-[1.25rem]"
                >
                    <img
                        :src="xenBannerBg"
                        alt=""
                        class="pointer-events-none absolute inset-0 size-full object-cover object-center"
                        aria-hidden="true"
                    />
                    <p
                        class="xen-banner__text relative z-[1] text-center font-[Inter] text-[0.75rem] font-normal leading-[1.25rem] tracking-[1.5px] text-white md:text-[0.95rem] md:leading-[1.625rem] md:tracking-[2.52px]"
                    >
                        Written by humans. Powered by your imagination. Brought to life by
                        <span class="text-primary">Xen</span>
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.xen-banner__text {
    text-shadow: 0 4px 24.8px rgba(0, 0, 0, 0.25);
}

</style>
