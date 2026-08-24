<script setup lang="ts">
/**
 * XEN emerge — cream portal tracks the silhouette coming through it.
 *
 * 1. Cream blooms from a slit to the wrist (rest) opening.
 * 2. Hand feeds out; the portal follows the precomputed cross-section curve,
 *    growing for a wide payload (platter, card) and shrinking back to the wrist.
 * 3. Short pendulum shiver at the end.
 *
 * Pose assets are pre-keyed (transparent, no baked portal), so there is no
 * runtime punching — the cream hole is drawn behind the hand.
 */
import type { XenEmergeFrom, XenGesture } from '../data/gestures';
import { xenClip, xenRetract } from '../data/gestures';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

const rootEl = ref<HTMLElement | null>(null);
const handSrc = ref('');
const ready = ref(false);

/** 0 = retracted, 1 = fully emerged. */
const progress = ref(0);
/** Portal opening (half-size along the long axis), in % of frame. */
const opening = ref(0);
const shivering = ref(false);
const frameSize = ref({ width: 0, height: 0 });

let token = 0;
let ro: ResizeObserver | null = null;
let rafId: number | null = null;
let shiverTimer: ReturnType<typeof setTimeout> | null = null;

const OPEN_MS = 460;
const FEED_MS = 820;
const SHIVER_MS = 380;
const PRINTER_STEPS = 14;
/** Thin axis is this fraction of the long axis (ellipse aspect). */
const THIN_RATIO = 0.42;
/** Extra cream padding around the silhouette, in % of frame. */
const PAD = 1.4;

const activeGesture = computed(() => props.gesture);
const portal = computed(() => activeGesture.value.portal);
const from = computed(() => activeGesture.value.emergeFrom);

const frameStyle = computed(() => {
    const { width, height } = frameSize.value;
    if (width <= 0 || height <= 0) {
        return { width: '100%', height: '100%' };
    }
    return { width: `${Math.round(width)}px`, height: `${Math.round(height)}px` };
});

const clipStyle = computed(() => ({
    clipPath: xenClip(activeGesture.value.mouth, from.value),
}));

const handOrigin = computed(() => {
    const m = activeGesture.value.mouth;
    switch (from.value) {
        case 'bottom':
            return `50% ${m}%`;
        case 'left':
        case 'right':
            return `${m}% 50%`;
    }
});

const handTransform = computed(() => {
    const d = activeGesture.value.emergeDistance * (1 - progress.value);
    return xenRetract(from.value, d);
});

const portalStyle = computed(() => {
    const p = portal.value;
    const long = opening.value + PAD;
    const thin = Math.max(opening.value, p.rest) * THIN_RATIO + PAD * 0.5;
    if (from.value === 'bottom') {
        return {
            left: `${p.cx}%`,
            top: `${p.cy}%`,
            width: `${long * 2}%`,
            height: `${thin * 2}%`,
        };
    }
    return {
        left: `${p.cx}%`,
        top: `${p.cy}%`,
        width: `${thin * 2}%`,
        height: `${long * 2}%`,
    };
});

const handShadow = computed(() => {
    switch (from.value) {
        case 'bottom':
            return 'drop-shadow(0 7px 6px rgba(40, 28, 18, 0.5)) drop-shadow(0 1px 0 rgba(255, 248, 235, 0.34))';
        case 'left':
            return 'drop-shadow(-6px 1px 6px rgba(40, 28, 18, 0.46)) drop-shadow(1px 0 0 rgba(255, 248, 235, 0.28))';
        case 'right':
            return 'drop-shadow(6px 1px 6px rgba(40, 28, 18, 0.46)) drop-shadow(-1px 0 0 rgba(255, 248, 235, 0.28))';
    }
});

function fit(cw: number, ch: number): void {
    const aspect = activeGesture.value.aspectRatio;
    if (cw <= 0 || ch <= 0 || aspect <= 0) {
        return;
    }
    let width = cw;
    let height = width / aspect;
    if (height > ch) {
        height = ch;
        width = height * aspect;
    }
    frameSize.value = { width, height };
}

function sampleCurve(p: number): number {
    const curve = portal.value.curve;
    if (curve.length === 0) {
        return portal.value.rest;
    }
    const idx = p * (curve.length - 1);
    const a = Math.floor(idx);
    const b = Math.min(curve.length - 1, a + 1);
    const v = curve[a] + (curve[b] - curve[a]) * (idx - a);
    return Math.max(portal.value.rest, v);
}

function cancelRaf(): void {
    if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
    }
}

function clearShiver(): void {
    if (shiverTimer !== null) {
        clearTimeout(shiverTimer);
        shiverTimer = null;
    }
}

function play(): void {
    cancelRaf();
    clearShiver();
    shivering.value = false;
    progress.value = 0;
    opening.value = 0;
    ready.value = true;

    const t = ++token;
    const start = performance.now();

    function frame(now: number): void {
        if (t !== token) {
            return;
        }
        const elapsed = now - start;
        if (elapsed < OPEN_MS) {
            // Bloom: slit -> rest opening, hand still hidden.
            const p = elapsed / OPEN_MS;
            const eased = 1 - Math.pow(1 - p, 3);
            opening.value = portal.value.rest * eased;
            progress.value = 0;
            rafId = requestAnimationFrame(frame);
            return;
        }
        const feedElapsed = elapsed - OPEN_MS;
        if (feedElapsed < FEED_MS) {
            const p = feedElapsed / FEED_MS;
            // Smooth portal tracking; stepped hand for the printer feel.
            opening.value = sampleCurve(p);
            progress.value = Math.round(p * PRINTER_STEPS) / PRINTER_STEPS;
            rafId = requestAnimationFrame(frame);
            return;
        }
        // Settle.
        progress.value = 1;
        opening.value = portal.value.rest;
        shiverTimer = setTimeout(() => {
            shivering.value = true;
            shiverTimer = setTimeout(() => {
                shivering.value = false;
                shiverTimer = null;
            }, SHIVER_MS);
        }, 60);
    }

    rafId = requestAnimationFrame(frame);
}

async function load(): Promise<void> {
    const t = ++token;
    ready.value = false;
    progress.value = 0;
    opening.value = 0;
    shivering.value = false;
    cancelRaf();
    clearShiver();
    handSrc.value = activeGesture.value.src;
    if (t !== token) {
        return;
    }
    await new Promise((r) => requestAnimationFrame(r));
    if (t !== token) {
        return;
    }
    play();
}

watch(
    () => props.gesture.id,
    () => {
        if (rootEl.value) {
            const r = rootEl.value.getBoundingClientRect();
            fit(r.width, r.height);
        }
        void load();
    },
    { immediate: true },
);

onMounted(() => {
    if (!rootEl.value) {
        return;
    }
    ro = new ResizeObserver((entries) => {
        const e = entries[0];
        if (e) {
            fit(e.contentRect.width, e.contentRect.height);
        }
    });
    ro.observe(rootEl.value);
    const r = rootEl.value.getBoundingClientRect();
    fit(r.width, r.height);
});

onBeforeUnmount(() => {
    token += 1;
    cancelRaf();
    clearShiver();
    ro?.disconnect();
});

defineExpose({ replay: play });
</script>

<template>
    <div
        ref="rootEl"
        class="xen"
        :class="[`xen--${from}`, { 'xen--ready': ready }]"
    >
        <div class="xen__frame" :style="frameStyle">
            <div class="xen__portal" :style="portalStyle" aria-hidden="true">
                <span class="xen__portal-core" />
                <span class="xen__portal-contact" />
            </div>

            <div class="xen__clip" :style="clipStyle">
                <img
                    v-if="handSrc"
                    class="xen__hand"
                    :class="{ 'xen__hand--shiver': shivering && progress >= 1 }"
                    :src="handSrc"
                    :alt="gesture.label"
                    :style="{
                        transform: handTransform,
                        transformOrigin: handOrigin,
                        filter: ready ? handShadow : undefined,
                    }"
                    draggable="false"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.xen {
    --xen-void: #0a0a0b;
    --xen-cream: #f3ebe0;
    --xen-cream-hi: #fffaf3;
    width: 100%;
    height: 100%;
    min-height: 12rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.xen__frame {
    position: relative;
    flex: 0 0 auto;
    overflow: hidden;
    background: var(--xen-void);
}

.xen__portal {
    position: absolute;
    z-index: 1;
    pointer-events: none;
    transform: translate(-50%, -50%);
    transform-origin: center center;
    will-change: width, height;
}

.xen__portal-core {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background:
        radial-gradient(
            ellipse at 35% 30%,
            var(--xen-cream-hi) 0%,
            var(--xen-cream) 52%,
            #e4d8c8 100%
        );
    box-shadow:
        inset 0 1px 0 rgba(255, 252, 245, 0.92),
        inset 0 -5px 12px rgba(60, 40, 20, 0.18),
        inset 0 0 0 1px rgba(255, 248, 235, 0.4),
        0 2px 8px rgba(0, 0, 0, 0.42),
        0 0 22px rgba(243, 235, 224, 0.18);
}

.xen__portal-contact {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    background: radial-gradient(
        ellipse at center,
        rgba(50, 32, 18, 0.4) 0%,
        rgba(50, 32, 18, 0.14) 45%,
        transparent 74%
    );
}

.xen--bottom .xen__portal-contact {
    left: 16%;
    right: 16%;
    top: 4%;
    height: 58%;
}

.xen--left .xen__portal-contact {
    top: 18%;
    bottom: 18%;
    right: 2%;
    width: 62%;
}

.xen--right .xen__portal-contact {
    top: 18%;
    bottom: 18%;
    left: 2%;
    width: 62%;
}

.xen__clip {
    position: absolute;
    inset: 0;
    z-index: 2;
}

.xen__hand {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: fill;
    will-change: transform;
    backface-visibility: hidden;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
}

.xen__hand--shiver {
    animation: xen-pendulum 0.38s cubic-bezier(0.33, 1.2, 0.4, 1) both;
}

.xen--bottom .xen__hand--shiver {
    animation-name: xen-pendulum-y;
}

.xen--left .xen__hand--shiver,
.xen--right .xen__hand--shiver {
    animation-name: xen-pendulum-x;
}

@keyframes xen-pendulum-y {
    0%   { transform: translate3d(0, 0, 0) rotate(0deg); }
    18%  { transform: translate3d(0, 0, 0) rotate(1.55deg); }
    42%  { transform: translate3d(0, 0, 0) rotate(-1.05deg); }
    68%  { transform: translate3d(0, 0, 0) rotate(0.45deg); }
    100% { transform: translate3d(0, 0, 0) rotate(0deg); }
}

@keyframes xen-pendulum-x {
    0%   { transform: translate3d(0, 0, 0) rotate(0deg); }
    18%  { transform: translate3d(0, 0, 0) rotate(-1.55deg); }
    42%  { transform: translate3d(0, 0, 0) rotate(1.05deg); }
    68%  { transform: translate3d(0, 0, 0) rotate(-0.45deg); }
    100% { transform: translate3d(0, 0, 0) rotate(0deg); }
}

@media (prefers-reduced-motion: reduce) {
    .xen__hand--shiver {
        animation: none;
    }
}
</style>
