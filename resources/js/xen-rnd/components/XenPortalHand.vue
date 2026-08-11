<script setup lang="ts">
/**
 * Printer-slot emerge
 *
 * Finish = original PNG, unstretched.
 * During motion: fixed hole at the portal mouth; image feeds through it.
 * After the slide settles, chrome drops so the original art shows clean.
 */
import type { XenGesture } from '../data/gestures';
import { xenHoleClip, xenRetractTransform } from '../data/gestures';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

/** Image is still behind the hole. */
const retracted = ref(true);
/** Slide finished — show clean original (no overlay chrome). */
const settled = ref(false);
const imageReady = ref(false);

const portal = computed(() => props.gesture.portal);

const stageStyle = computed(() => ({
    '--xen-aspect': String(props.gesture.aspectRatio),
}));

const animating = computed(() => imageReady.value && !settled.value);

const holeClipStyle = computed(() => {
    if (!animating.value || !portal.value) {
        return { clipPath: 'none' };
    }
    return { clipPath: xenHoleClip(portal.value.mouth, props.gesture.emergeFrom) };
});

const trackTransform = computed(() =>
    retracted.value
        ? xenRetractTransform(props.gesture.emergeFrom, props.gesture.emergeDistance)
        : 'translate3d(0, 0, 0)',
);

const portalRimStyle = computed(() => {
    const p = portal.value;
    if (!p) {
        return null;
    }
    return {
        left: `${p.cx}%`,
        top: `${p.cy}%`,
        width: `${p.rx * 2}%`,
        height: `${p.ry * 2}%`,
        transform: 'translate(-50%, -50%)',
    };
});

const portalCoverStyle = computed(() => {
    const p = portal.value;
    if (!p || !animating.value) {
        return null;
    }
    return {
        left: `${p.cx}%`,
        top: `${p.cy}%`,
        width: `${p.rx * 2 * 1.12}%`,
        height: `${p.ry * 2 * 1.12}%`,
        transform: 'translate(-50%, -50%)',
    };
});

async function playEmergence(): Promise<void> {
    settled.value = false;
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

function onTrackTransitionEnd(event: TransitionEvent): void {
    if (event.propertyName !== 'transform') {
        return;
    }
    if (!retracted.value) {
        settled.value = true;
    }
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
        settled.value = false;
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
            'xen-portal-hand--settled': settled,
            'xen-portal-hand--loading': !imageReady,
            [`xen-portal-hand--from-${gesture.emergeFrom}`]: true,
        }"
        :style="stageStyle"
    >
        <div class="xen-portal-hand__stage">
            <div class="xen-portal-hand__hole" :style="holeClipStyle">
                <div
                    class="xen-portal-hand__track"
                    :style="{ transform: trackTransform }"
                    @transitionend="onTrackTransitionEnd"
                >
                    <img
                        :key="gesture.id"
                        :src="gesture.src"
                        :alt="gesture.label"
                        class="xen-portal-hand__image"
                        draggable="false"
                        @load="onImageLoad"
                    />

                    <div
                        v-if="portalCoverStyle"
                        class="xen-portal-hand__cover"
                        :style="portalCoverStyle"
                        aria-hidden="true"
                    />
                </div>
            </div>

            <div
                v-if="animating && portalRimStyle"
                class="xen-portal-hand__rim"
                :style="portalRimStyle"
                aria-hidden="true"
            />
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

.xen-portal-hand--loading .xen-portal-hand__hole {
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
    border-radius: 50%;
    background: #fff;
    box-shadow:
        0 0 10px color-mix(in srgb, #fff 45%, transparent),
        0 0 22px color-mix(in srgb, var(--color-primary-300) 25%, transparent);
    pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
    .xen-portal-hand__track {
        transition-duration: 0.01ms;
    }
}
</style>
