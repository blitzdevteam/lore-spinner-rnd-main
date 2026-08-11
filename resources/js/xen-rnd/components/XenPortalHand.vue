<script setup lang="ts">
/**
 * Hand through a hole
 *
 * - Cover the baked white portal in the PNG.
 * - Draw a fixed white portal (the hole).
 * - Clip at the portal center so the hand can only exist outside the hole.
 * - Slide the image from retracted → 0 so it feeds through like a printer.
 * - Rest at translate(0): wrist sits in the center of the portal — same as the originals.
 * - Never stretch: stage aspect = PNG aspect, object-fit: contain.
 */
import type { XenGesture, XenPortal } from '../data/gestures';
import { xenHoleClip, xenRetractTransform } from '../data/gestures';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

const retracted = ref(true);
const imageReady = ref(false);

const stageStyle = computed(() => ({
    '--xen-aspect': String(props.gesture.aspectRatio),
}));

function ellipseStyle(p: XenPortal, scale = 1) {
    return {
        left: `${p.cx}%`,
        top: `${p.cy}%`,
        width: `${p.rx * 2 * scale}%`,
        height: `${p.ry * 2 * scale}%`,
        transform: 'translate(-50%, -50%)',
    };
}

const covers = computed(() => {
    const list: XenPortal[] = [props.gesture.portal];
    if (props.gesture.coverExtra) {
        list.push(...props.gesture.coverExtra);
    }
    return list;
});

const holeClipStyle = computed(() => ({
    clipPath: xenHoleClip(props.gesture.portal, props.gesture.emergeFrom),
}));

const trackTransform = computed(() =>
    retracted.value
        ? xenRetractTransform(props.gesture.emergeFrom, props.gesture.emergeDistance)
        : 'translate3d(0, 0, 0)',
);

const rimStyle = computed(() => ellipseStyle(props.gesture.portal));

async function playEmergence(): Promise<void> {
    retracted.value = true;
    await nextTick();
    if (!imageReady.value) {
        return;
    }
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            retracted.value = false;
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
        retracted.value = true;
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
        :class="{
            'xen-portal-hand--loading': !imageReady,
            [`xen-portal-hand--from-${gesture.emergeFrom}`]: true,
        }"
        :style="stageStyle"
    >
        <div class="xen-portal-hand__stage">
            <!-- Fixed hole clip: hand only visible on the exit side of the portal center -->
            <div class="xen-portal-hand__hole" :style="holeClipStyle">
                <div class="xen-portal-hand__track" :style="{ transform: trackTransform }">
                    <img
                        :key="gesture.id"
                        :src="gesture.src"
                        :alt="gesture.label"
                        class="xen-portal-hand__image"
                        draggable="false"
                        @load="onImageLoad"
                    />

                    <!-- Clip the baked white portal(s) out of the PNG -->
                    <div
                        v-for="(cover, i) in covers"
                        :key="i"
                        class="xen-portal-hand__cover"
                        :style="ellipseStyle(cover, 1.1)"
                        aria-hidden="true"
                    />
                </div>
            </div>

            <!-- Our portal — the hole the hand comes through (always on) -->
            <div class="xen-portal-hand__rim" :style="rimStyle" aria-hidden="true">
                <div class="xen-portal-hand__rim-core" />
                <div class="xen-portal-hand__rim-glow" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.xen-portal-hand {
    --xen-stage-bg: #050409;
    --xen-emerge-duration: 1.15s;
    --xen-emerge-ease: cubic-bezier(0.25, 0.9, 0.3, 1);
    height: 100%;
    width: auto;
    max-width: 100%;
    max-height: 100%;
    aspect-ratio: var(--xen-aspect);
    margin-inline: auto;
}

.xen-portal-hand__stage {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: var(--xen-stage-bg);
}

.xen-portal-hand__hole {
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
    object-fit: contain;
    object-position: center center;
    display: block;
    user-select: none;
    -webkit-user-drag: none;
}

.xen-portal-hand--loading .xen-portal-hand__hole,
.xen-portal-hand--loading .xen-portal-hand__rim {
    opacity: 0;
}

.xen-portal-hand__cover {
    position: absolute;
    z-index: 2;
    border-radius: 50%;
    background: var(--xen-stage-bg);
    pointer-events: none;
}

.xen-portal-hand__rim {
    position: absolute;
    z-index: 3;
    pointer-events: none;
}

.xen-portal-hand__rim-core {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #fff;
    box-shadow:
        0 0 0 1px color-mix(in srgb, #fff 80%, #000 20%),
        inset 0 -1px 4px color-mix(in srgb, #000 12%, transparent);
}

.xen-portal-hand__rim-glow {
    position: absolute;
    inset: -30%;
    border-radius: 50%;
    background: radial-gradient(
        ellipse at center,
        color-mix(in srgb, #fff 40%, transparent) 0%,
        color-mix(in srgb, var(--color-primary-300) 18%, transparent) 40%,
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
