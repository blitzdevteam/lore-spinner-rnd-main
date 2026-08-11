<script setup lang="ts">
import type { XenGesture, XenPortalRect } from '../data/gestures';
import { xenEmergeClipPath, xenEmergeStartTransform } from '../data/gestures';
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

/** Printer slot — fixed on stage; hand only visible after passing this edge. */
const emergeWindowStyle = computed(() => ({
    clipPath: xenEmergeClipPath(props.gesture.portal, props.gesture.emergeFrom),
}));

const trackTransform = computed(() =>
    emerged.value
        ? 'translate3d(0, 0, 0)'
        : xenEmergeStartTransform(props.gesture.emergeFrom, props.gesture.emergeDistance),
);

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
            <!-- Fixed printer slot: hand reveals only past the portal exit edge -->
            <div class="xen-portal-hand__emerge-window" :style="emergeWindowStyle">
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
            </div>

            <!-- Portal rim stays fixed; covers the wrist seam at rest -->
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
    --xen-emerge-duration: 1.2s;
    --xen-emerge-ease: cubic-bezier(0.33, 1, 0.68, 1);
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

.xen-portal-hand__emerge-window {
    position: absolute;
    inset: 0;
    z-index: 1;
}

.xen-portal-hand__track {
    position: absolute;
    inset: 0;
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

.xen-portal-hand--loading .xen-portal-hand__emerge-window {
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
        0 0 12px color-mix(in srgb, var(--xen-portal-glow) 45%, transparent),
        inset 0 -1px 3px color-mix(in srgb, #000 8%, transparent);
}

.xen-portal-hand__portal-glow {
    position: absolute;
    inset: -24%;
    border-radius: 50%;
    background: radial-gradient(
        ellipse at center,
        color-mix(in srgb, var(--xen-portal-glow) 35%, transparent) 0%,
        transparent 70%
    );
    z-index: -1;
}

@media (prefers-reduced-motion: reduce) {
    .xen-portal-hand__track {
        transition-duration: 0.01ms;
    }
}
</style>
