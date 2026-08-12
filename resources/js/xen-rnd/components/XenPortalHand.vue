<script setup lang="ts">
/**
 * XEN printer-hole emerge
 *
 * Frame is sized with ResizeObserver so it never collapses to 0×0
 * (absolute-positioned layers don't give intrinsic size).
 */
import type { XenGesture } from '../data/gestures';
import { xenHoleClip, xenRetractTransform } from '../data/gestures';
import { splitXenLayers, type XenSplitLayers } from '../lib/splitXenLayers';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

const rootEl = ref<HTMLElement | null>(null);
const frameEl = ref<HTMLElement | null>(null);
const handImg = ref<HTMLImageElement | null>(null);
const portalImg = ref<HTMLImageElement | null>(null);

const layers = ref<XenSplitLayers | null>(null);
const loading = ref(true);
const error = ref('');
const retracted = ref(true);
const settled = ref(false);
const layersReady = ref(false);
const frameSize = ref({ width: 0, height: 0 });

let loadToken = 0;
let settleTimer: ReturnType<typeof setTimeout> | null = null;
let resizeObserver: ResizeObserver | null = null;

const frameStyle = computed(() => {
    const { width, height } = frameSize.value;
    if (width <= 0 || height <= 0) {
        return { width: '100%', height: '100%' };
    }
    return {
        width: `${Math.round(width)}px`,
        height: `${Math.round(height)}px`,
    };
});

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

/** Always have something to show — original while loading / on failure. */
const showOriginal = computed(() => settled.value || !layers.value || !!error.value);

function fitFrame(containerW: number, containerH: number): void {
    const aspect = props.gesture.aspectRatio;
    if (containerW <= 0 || containerH <= 0 || !Number.isFinite(aspect) || aspect <= 0) {
        return;
    }
    let width = containerW;
    let height = width / aspect;
    if (height > containerH) {
        height = containerH;
        width = height * aspect;
    }
    frameSize.value = { width, height };
}

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

function clearSettleTimer(): void {
    if (settleTimer) {
        clearTimeout(settleTimer);
        settleTimer = null;
    }
}

async function startEmergence(): Promise<void> {
    clearSettleTimer();
    settled.value = false;
    retracted.value = true;
    await nextTick();
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            retracted.value = false;
            // Fallback if transitionend never fires
            clearSettleTimer();
            settleTimer = setTimeout(() => {
                settled.value = true;
            }, 1400);
        });
    });
}

function onTrackTransitionEnd(event: TransitionEvent): void {
    if (event.propertyName !== 'transform') {
        return;
    }
    if (!retracted.value) {
        clearSettleTimer();
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
    clearSettleTimer();

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
        console.error('XEN split failed', e);
        error.value = e instanceof Error ? e.message : 'Failed to split image';
        loading.value = false;
        settled.value = true; // show original fallback via gesture.src in template
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
        // Re-fit in case aspect changed
        if (rootEl.value) {
            const rect = rootEl.value.getBoundingClientRect();
            fitFrame(rect.width, rect.height);
        }
        void loadLayers();
    },
    { immediate: true },
);

onMounted(() => {
    if (!rootEl.value) {
        return;
    }
    resizeObserver = new ResizeObserver((entries) => {
        const entry = entries[0];
        if (!entry) {
            return;
        }
        fitFrame(entry.contentRect.width, entry.contentRect.height);
    });
    resizeObserver.observe(rootEl.value);
    const rect = rootEl.value.getBoundingClientRect();
    fitFrame(rect.width, rect.height);
});

onBeforeUnmount(() => {
    loadToken += 1;
    clearSettleTimer();
    resizeObserver?.disconnect();
    resizeObserver = null;
});

defineExpose({ replay });
</script>

<template>
    <div
        ref="rootEl"
        class="xen-portal-hand"
        :class="{
            'xen-portal-hand--loading': loading,
            [`xen-portal-hand--from-${gesture.emergeFrom}`]: true,
        }"
    >
        <div ref="frameEl" class="xen-portal-hand__frame" :style="frameStyle">
            <!-- Fallback / settled: always-visible original -->
            <img
                v-show="showOriginal"
                class="xen-portal-hand__original"
                :src="layers?.originalUrl ?? gesture.src"
                :alt="gesture.label"
                draggable="false"
            />

            <p v-if="error" class="xen-portal-hand__error">{{ error }}</p>

            <div v-if="layers && !settled" class="xen-portal-hand__anim" :class="{ 'is-ready': layersReady }">
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
        </div>
    </div>
</template>

<style scoped>
.xen-portal-hand {
    --xen-stage-bg: #050409;
    --xen-emerge-duration: 1.2s;
    --xen-emerge-ease: cubic-bezier(0.22, 0.9, 0.28, 1);
    box-sizing: border-box;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 12rem;
}

.xen-portal-hand__frame {
    position: relative;
    flex: 0 0 auto;
    overflow: hidden;
    background: var(--xen-stage-bg);
}

.xen-portal-hand__anim {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.xen-portal-hand__anim.is-ready {
    opacity: 1;
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
    z-index: 2;
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
    left: 0;
    right: 0;
    bottom: 0.75rem;
    z-index: 6;
    padding: 0 1rem;
    color: #f87171;
    font-size: 0.75rem;
    text-align: center;
}

@media (prefers-reduced-motion: reduce) {
    .xen-portal-hand__track {
        transition-duration: 0.01ms;
    }
}
</style>
