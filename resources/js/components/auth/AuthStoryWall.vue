<script setup lang="ts">
import col11 from '@/assets/auth/covers/col1-1.webp';
import col12 from '@/assets/auth/covers/col1-2.webp';
import col13 from '@/assets/auth/covers/col1-3.webp';
import col14 from '@/assets/auth/covers/col1-4.webp';
import col21 from '@/assets/auth/covers/col2-1.webp';
import col22 from '@/assets/auth/covers/col2-2.webp';
import col23 from '@/assets/auth/covers/col2-3.webp';
import col24 from '@/assets/auth/covers/col2-4.webp';
import col31 from '@/assets/auth/covers/col3-1.webp';
import col32 from '@/assets/auth/covers/col3-2.webp';
import col33 from '@/assets/auth/covers/col3-3.webp';
import col34 from '@/assets/auth/covers/col3-4.webp';
import col41 from '@/assets/auth/covers/col4-1.webp';
import col42 from '@/assets/auth/covers/col4-2.webp';
import col43 from '@/assets/auth/covers/col4-3.webp';
import col44 from '@/assets/auth/covers/col4-4.webp';
import { onMounted, onUnmounted, ref } from 'vue';

const columnOffsets = ['-75px', '-41px', '-14px', '11px'] as const;

/** Each column is repeated so one loop stays taller than a large desktop viewport. */
const columns: string[][] = [
    [col11, col12, col13, col14],
    [col21, col22, col23, col24],
    [col31, col32, col33, col34],
    [col41, col42, col43, col44],
].map((covers) => [...covers, ...covers]);

const visible = ref(false);
let wallMedia: MediaQueryList | null = null;

function syncWall(): void {
    visible.value = wallMedia?.matches ?? false;
}

onMounted(() => {
    wallMedia = window.matchMedia('(min-width: 1024px)');
    syncWall();
    wallMedia.addEventListener('change', syncWall);
});

onUnmounted(() => {
    wallMedia?.removeEventListener('change', syncWall);
});
</script>

<template>
    <div
        class="auth-story-wall sticky top-0 hidden h-svh w-[min(50rem,56vw)] shrink-0 self-start overflow-hidden lg:block"
        aria-hidden="true"
    >
        <div v-if="visible" class="auth-story-stage">
            <div
                v-for="(column, columnIndex) in columns"
                :key="columnIndex"
                class="auth-story-column"
                :class="columnIndex % 2 === 0 ? 'auth-story-column--up' : 'auth-story-column--down'"
                :style="{ '--column-offset': columnOffsets[columnIndex] }"
            >
                <div class="auth-story-track">
                    <div v-for="copy in 2" :key="copy" class="auth-story-set">
                        <div v-for="(src, cardIndex) in column" :key="`${copy}-${cardIndex}`" class="auth-story-card">
                            <img :src="src" alt="" width="156" height="234" decoding="async" draggable="false" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="auth-story-fade auth-story-fade--top" />
        <div class="auth-story-fade auth-story-fade--bottom" />
        <div class="auth-story-fade auth-story-fade--left" />
        <div class="auth-story-fade auth-story-fade--right" />
    </div>
</template>

<style scoped>
.auth-story-wall {
    container-type: inline-size;
    contain: paint;
    pointer-events: none;
}

.auth-story-stage {
    display: flex;
    height: 100%;
    width: max-content;
    gap: 20px;
    padding-left: 4rem;
    transform-origin: top left;
    scale: min(1, calc((100cqw - 4rem) / 44rem));
}

.auth-story-column {
    flex: none;
    margin-top: var(--column-offset);
}

.auth-story-track {
    display: flex;
    flex-direction: column;
    animation-duration: 55s;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
    will-change: transform;
}

.auth-story-column--up .auth-story-track {
    animation-name: auth-story-up;
}

.auth-story-column--down .auth-story-track {
    animation-name: auth-story-down;
}

.auth-story-set {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 20px;
}

.auth-story-card {
    box-sizing: border-box;
    width: 161px;
    padding: 2px;
    border: 0.5px solid #373737;
    border-radius: 8px;
    background: #0f0f0f;
    box-shadow: 0 4px 4px rgba(0, 0, 0, 0.25);
}

.auth-story-card img {
    display: block;
    width: 156px;
    height: 234px;
    border-radius: 5px;
    object-fit: cover;
}

.auth-story-fade {
    position: absolute;
    z-index: 2;
    pointer-events: none;
}

.auth-story-fade--top {
    top: 0;
    right: 0;
    left: 0;
    height: 195px;
    background: linear-gradient(180deg, #000 0%, rgba(0, 0, 0, 0) 73%);
}

.auth-story-fade--bottom {
    right: 0;
    bottom: 0;
    left: 0;
    height: 203px;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0) 1.4%, #000 82%);
}

.auth-story-fade--left,
.auth-story-fade--right {
    top: 0;
    bottom: 0;
    width: 91px;
}

.auth-story-fade--left {
    left: 0;
    background: linear-gradient(90deg, #000 0%, rgba(0, 0, 0, 0) 73%);
}

.auth-story-fade--right {
    right: 0;
    background: linear-gradient(270deg, #000 0%, rgba(0, 0, 0, 0) 73%);
}

@keyframes auth-story-up {
    from {
        transform: translate3d(0, 0, 0);
    }

    to {
        transform: translate3d(0, -50%, 0);
    }
}

@keyframes auth-story-down {
    from {
        transform: translate3d(0, -50%, 0);
    }

    to {
        transform: translate3d(0, 0, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-story-track {
        animation: none;
        will-change: auto;
    }
}
</style>
