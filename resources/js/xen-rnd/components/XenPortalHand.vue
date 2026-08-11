<script setup lang="ts">
import type { XenGesture, XenPortalRect } from '../data/gestures';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

const emerged = ref(false);
const imageReady = ref(false);

const stageStyle = computed(() => ({
    '--xen-aspect': String(props.gesture.aspectRatio),
}));

function ellipseStyle(rect: XenPortalRect) {
    return {
        left: `${rect.cx}%`,
        top: `${rect.cy}%`,
        width: `${rect.rx * 2}%`,
        height: `${rect.ry * 2}%`,
        transform: 'translate(-50%, -50%)',
    };
}

const portalStyle = computed(() => ellipseStyle(props.gesture.portal));

const bakedCoverStyle = computed(() => {
    const cover = props.gesture.bakedCover;
    return cover ? ellipseStyle(cover) : null;
});

/** Track (image + eraser) moves; portal rim stays fixed on stage. */
const trackTransform = computed(() => {
    const g = props.gesture;
    const offset = emerged.value ? 0 : g.emergeStart;

    if (g.emergeAxis === 'y') {
        return `translate3d(0, ${offset}%, 0)`;
    }

    return `translate3d(${offset}%, 0, 0)`;
});

async function playEmergence(): Promise<void> {
    emerged.value = false;
    await nextTick();
    if (!imageReady.value) {
        return;
    }
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            emerged.value = true;
        });
    });
}

function onImageLoad(): void {
    imageReady.value = true;
    void playEmergence();
}

watch(
    () => props.gesture.id,
    () => {
        imageReady.value = false;
        emerged.value = false;
    },
);

onMounted(() => {
    if (imageReady.value) {
        void playEmergence();
    }
});

defineExpose({ replay: playEmergence });
</script>

<template>
    <div
        class="xen-portal-hand"
        :class="{ 'xen-portal-hand--emerged': emerged, 'xen-portal-hand--loading': !imageReady }"
        :style="stageStyle"
    >
        <div class="xen-portal-hand__stage">
            <div class="xen-portal-hand__track" :style="{ transform: trackTransform }">
                <img
                    :key="gesture.id"
                    :src="gesture.src"
                    :alt="gesture.label"
                    class="xen-portal-hand__image"
                    draggable="false"
                    @load="onImageLoad"
                />

                <div
                    v-if="bakedCoverStyle"
                    class="xen-portal-hand__baked-cover"
                    :style="bakedCoverStyle"
                    aria-hidden="true"
                />
            </div>

            <div class="xen-portal-hand__portal" :style="portalStyle" aria-hidden="true">
                <div class="xen-portal-hand__portal-rim" />
                <div class="xen-portal-hand__portal-glow" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.xen-portal-hand {
    --xen-stage-bg: #050409;
    --xen-portal-white: #ffffff;
    --xen-portal-glow: color-mix(in srgb, var(--color-primary-300) 28%, white);
    --xen-emerge-duration: 1.15s;
    --xen-emerge-ease: cubic-bezier(0.22, 1, 0.28, 1);
    width: min(92vw, 52rem);
    aspect-ratio: var(--xen-aspect);
    position: relative;
}

.xen-portal-hand__stage {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: var(--xen-stage-bg);
    border-radius: 0.35rem;
}

.xen-portal-hand__track {
    position: absolute;
    inset: 0;
    transform-origin: center center;
    transition: transform var(--xen-emerge-duration) var(--xen-emerge-ease);
    will-change: transform;
    backface-visibility: hidden;
}

.xen-portal-hand__image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: fill;
    display: block;
}

.xen-portal-hand--loading .xen-portal-hand__track {
    opacity: 0;
}

.xen-portal-hand__baked-cover {
    position: absolute;
    z-index: 2;
    border-radius: 50%;
    background: var(--xen-stage-bg);
    pointer-events: none;
}

.xen-portal-hand__portal {
    position: absolute;
    z-index: 3;
    pointer-events: none;
}

.xen-portal-hand__portal-rim {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--xen-portal-white);
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--xen-portal-white) 85%, #000 15%),
        0 0 14px color-mix(in srgb, var(--xen-portal-glow) 50%, transparent),
        inset 0 -1px 4px color-mix(in srgb, #000 10%, transparent);
}

.xen-portal-hand__portal-glow {
    position: absolute;
    inset: -28%;
    border-radius: 50%;
    background: radial-gradient(
        ellipse at center,
        color-mix(in srgb, var(--xen-portal-glow) 40%, transparent) 0%,
        transparent 72%
    );
    z-index: -1;
}

@media (prefers-reduced-motion: reduce) {
    .xen-portal-hand__track {
        transition-duration: 0.01ms;
    }
}
</style>
