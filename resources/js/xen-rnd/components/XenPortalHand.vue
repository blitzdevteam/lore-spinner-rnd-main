<script setup lang="ts">
/**
 * XEN printer-hole emerge — revised
 *
 * During motion: hand slides through a clip at the portal CENTER;
 * portal layer stays fixed (real PNG pixels).
 * After settle: show the full original so rest is exact.
 * Frame sizing fits the parent without cropping.
 */
import type { XenGesture } from '../data/gestures';
import { xenHoleClip, xenRetractTransform } from '../data/gestures';
import { splitXenLayers, type XenSplitLayers } from '../lib/splitXenLayers';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

const layers = ref<XenSplitLayers | null>(null);
const loading = ref(true);
const error = ref('');
const retracted = ref(true);
const settled = ref(false);
const layersReady = ref(false);

const handImg = ref<HTMLImageElement | null>(null);
const portalImg = ref<HTMLImageElement | null>(null);

let loadToken = 0;

const stageStyle = computed(() => ({
    '--xen-aspect': String(props.gesture.aspectRatio),
}));

const holeClipStyle = computed(() => {
    if (!layers.value || settled.value) {
        return { clipPath: 'none' };
    }
    return { clipPath: xenHoleClip(layers.value.mouth, props.gesture.emergeFrom) };
});

const trackTransform = computed(() =>
    retracted.value
        ? xenRetractTransform(props.gesture.emergeFrom, props.gesture.emergeDistance)
        : 'translate3d(0, 0, 0)',
);

const syntheticRimStyle = computed(() => {
    if (!layers.value || layers.value.portalUrl || settled.value) {
        return null;
    }
    const p = layers.value.portal;
    return {
        left: `${p.cx}%`,
        top: `${p.cy}%`,
        width: `${p.rx * 2}%`,
        height: `${p.ry * 2}%`,
        transform: 'translate(-50%, -50%)',
    };
});

function waitForImage(el: HTMLImageElement | null): Promise<void> {
    if (!el) {
        return Promise.resolve();
    }
    if (el.complete && el.naturalWidth > 0) {
        return Promise.resolve();
    }
    return new Promise((resolve) => {
        el.addEventListener('load', () => resolve(), { once: true });
        el.addEventListener('error', () => resolve(), { once: true });
    });
}

async function startEmergence(): Promise<void> {
    settled.value = false;
    retracted.value = true;
    await nextTick();
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

async function afterLayersMounted(token: number): Promise<void> {
    await nextTick();
    await Promise.all([waitForImage(handImg.value), waitForImage(portalImg.value)]);
    if (token !== loadToken) {
        return;
    }
    layersReady.value = true;
    // Match (no portal img): still ready once hand is ready
    if (!layers.value?.portalUrl) {
        layersReady.value = true;
    }
    await startEmergence();
}

async function loadLayers(): Promise<void> {
    const token = ++loadToken;
    loading.value = true;
    error.value = '';
    layersReady.value = false;
    retracted.value = true;
    settled.value = false;
    layers.value = null;

    try {
        const split = await splitXenLayers(props.gesture.src, props.gesture.emergeFrom);
        if (token !== loadToken) {
            return;
        }
        layers.value = split;
        loading.value = false;
        await afterLayersMounted(token);
    } catch (e) {
        if (token !== loadToken) {
            return;
        }
        error.value = e instanceof Error ? e.message : 'Failed to split image';
        loading.value = false;
    }
}

async function replay(): Promise<void> {
    if (!layers.value) {
        await loadLayers();
        return;
    }
    await startEmergence();
}

watch(
    () => props.gesture.id,
    () => {
        void loadLayers();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    loadToken += 1;
});

defineExpose({ replay });
</script>

<template>
    <div
        class="xen-portal-hand"
        :class="{
            'xen-portal-hand--loading': loading || (!!layers && !layersReady && !settled),
            'xen-portal-hand--settled': settled,
            [`xen-portal-hand--from-${gesture.emergeFrom}`]: true,
        }"
        :style="stageStyle"
    >
        <div class="xen-portal-hand__frame">
            <p v-if="error" class="xen-portal-hand__error">{{ error }}</p>

            <template v-if="layers">
                <img
                    v-show="settled"
                    class="xen-portal-hand__original"
                    :src="layers.originalUrl"
                    :alt="gesture.label"
                    draggable="false"
                />

                <div v-show="!settled" class="xen-portal-hand__anim">
                    <div class="xen-portal-hand__hole" :style="holeClipStyle">
                        <div
                            class="xen-portal-hand__track"
                            :style="{ transform: trackTransform }"
                            @transitionend="onTrackTransitionEnd"
                        >
                            <img
                                ref="handImg"
                                class="xen-portal-hand__layer"
                                :src="layers.handUrl"
                                :alt="gesture.label"
                                draggable="false"
                            />
                        </div>
                    </div>

                    <img
                        v-if="layers.portalUrl"
                        ref="portalImg"
                        class="xen-portal-hand__portal-layer"
                        :src="layers.portalUrl"
                        alt=""
                        draggable="false"
                    />

                    <div
                        v-else-if="syntheticRimStyle"
                        class="xen-portal-hand__synthetic-rim"
                        :style="syntheticRimStyle"
                        aria-hidden="true"
                    />
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.xen-portal-hand {
    --xen-stage-bg: #050409;
    --xen-emerge-duration: 1.2s;
    --xen-emerge-ease: cubic-bezier(0.22, 0.9, 0.28, 1);
    box-sizing: border-box;
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: 100%;
    aspect-ratio: var(--xen-aspect);
    margin: auto;
}

.xen-portal-hand__frame {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: var(--xen-stage-bg);
}

.xen-portal-hand__anim {
    position: absolute;
    inset: 0;
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

.xen-portal-hand__layer,
.xen-portal-hand__portal-layer,
.xen-portal-hand__original {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: fill;
    display: block;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
}

.xen-portal-hand__portal-layer {
    z-index: 3;
}

.xen-portal-hand__original {
    z-index: 5;
}

.xen-portal-hand--loading .xen-portal-hand__anim {
    opacity: 0;
}

.xen-portal-hand__synthetic-rim {
    position: absolute;
    z-index: 3;
    border-radius: 50%;
    background: #fff;
    box-shadow:
        0 0 10px color-mix(in srgb, #fff 55%, transparent),
        0 0 22px color-mix(in srgb, var(--color-primary-300) 25%, transparent);
    pointer-events: none;
}

.xen-portal-hand__error {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 1rem;
    color: #f87171;
    font-size: 0.875rem;
    text-align: center;
}

@media (prefers-reduced-motion: reduce) {
    .xen-portal-hand__track {
        transition-duration: 0.01ms;
    }
}
</style>
