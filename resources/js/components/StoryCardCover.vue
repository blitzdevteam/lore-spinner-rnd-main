<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.png';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        src?: string | null;
        title: string;
        /** Enable image zoom on group hover (desktop). */
        zoomOnHover?: boolean;
    }>(),
    {
        src: null,
        zoomOnHover: true,
    },
);

const imageFailed = ref(false);

watch(
    () => props.src,
    () => {
        imageFailed.value = false;
    },
);

const showImage = computed(() => Boolean(props.src) && !imageFailed.value);

function onImageError(): void {
    imageFailed.value = true;
}
</script>

<template>
    <div class="story-card-cover">
        <img
            v-if="showImage"
            :src="src!"
            :alt="title"
            class="story-card-cover__img"
            :class="zoomOnHover && 'story-card-cover__img--zoom'"
            decoding="async"
            @error="onImageError"
        />
        <div v-else class="story-card-cover__fallback" aria-hidden="true">
            <p class="story-card-cover__fallback-title">{{ title }}</p>
            <img :src="mainLogo" alt="" class="story-card-cover__logo" />
        </div>
    </div>
</template>

<style scoped>
.story-card-cover {
    position: relative;
    width: 100%;
    aspect-ratio: 2 / 3;
    overflow: hidden;
    flex-shrink: 0;
    background: #0c0c0c;
    border-radius: 10px !important
}

.story-card-cover__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 200ms ease;
}

@media (min-width: 1024px) {
    .group:hover .story-card-cover__img--zoom {
        transform: scale(1.05);
    }
}

.story-card-cover__fallback {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1rem;
    background: linear-gradient(165deg, #1a1a1f 0%, #0d0d10 45%, #141418 100%);
}

.story-card-cover__fallback-title {
    margin: 0;
    max-width: 100%;
    padding: 0 0.5rem;
    font-size: clamp(0.8125rem, 2.8vw, 1rem);
    font-weight: 700;
    line-height: 1.35;
    text-align: center;
    color: rgba(255, 255, 255, 0.88);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
}

.story-card-cover__logo {
    width: auto;
    height: 1.125rem;
    opacity: 0.55;
    object-fit: contain;
}
</style>
