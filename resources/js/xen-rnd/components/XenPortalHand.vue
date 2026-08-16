<script setup lang="ts">
/**
 * XEN emerge — cream opens, then the hand is pulled through
 *
 * 1. Punch baked portal + leftover crop rim
 * 2. Cream hole blooms open
 * 3. Hand feeds out of the opened cream
 * 4. Short pendulum shiver
 */
import type { XenGesture, XenPortal } from '../data/gestures';
import { xenClip, xenRetract } from '../data/gestures';
import { punchPortalWhite } from '../lib/punchPortal';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

const rootEl = ref<HTMLElement | null>(null);
const handSrc = ref('');
const portal = ref<XenPortal | null>(null);
const ready = ref(false);
const opened = ref(false);
const retracted = ref(true);
const shivering = ref(false);
const frameSize = ref({ width: 0, height: 0 });

let token = 0;
let ro: ResizeObserver | null = null;
let emergeTimer: ReturnType<typeof setTimeout> | null = null;
let shiverTimer: ReturnType<typeof setTimeout> | null = null;

const OPEN_MS = 560;
/** Beat of fully-open, empty cream so the opening reads before the hand moves. */
const HOLD_MS = 150;
const EMERGE_MS = 850;
const SHIVER_MS = 380;

const activePortal = computed(() => portal.value ?? props.gesture.portal);

const frameStyle = computed(() => {
    const { width, height } = frameSize.value;
    if (width <= 0 || height <= 0) {
        return { width: '100%', height: '100%' };
    }
    return { width: `${Math.round(width)}px`, height: `${Math.round(height)}px` };
});

const portalStyle = computed(() => {
    const p = activePortal.value;
    return {
        left: `${p.cx}%`,
        top: `${p.cy}%`,
        width: `${p.rx * 2}%`,
        height: `${p.ry * 2}%`,
    };
});

const clipStyle = computed(() => ({
    clipPath: xenClip(activePortal.value.mouth, props.gesture.emergeFrom),
}));

const handOrigin = computed(() => {
    const m = activePortal.value.mouth;
    switch (props.gesture.emergeFrom) {
        case 'bottom':
            return `50% ${m}%`;
        case 'left':
        case 'right':
            return `${m}% 50%`;
    }
});

const handTransform = computed(() =>
    retracted.value
        ? xenRetract(props.gesture.emergeFrom, props.gesture.emergeDistance)
        : 'translate3d(0, 0, 0)',
);

const handShadow = computed(() => {
    switch (props.gesture.emergeFrom) {
        case 'bottom':
            return 'drop-shadow(0 7px 6px rgba(40, 28, 18, 0.5)) drop-shadow(0 1px 0 rgba(255, 248, 235, 0.34))';
        case 'left':
            return 'drop-shadow(-6px 1px 6px rgba(40, 28, 18, 0.46)) drop-shadow(1px 0 0 rgba(255, 248, 235, 0.28))';
        case 'right':
            return 'drop-shadow(6px 1px 6px rgba(40, 28, 18, 0.46)) drop-shadow(-1px 0 0 rgba(255, 248, 235, 0.28))';
    }
});

function fit(cw: number, ch: number): void {
    const aspect = props.gesture.aspectRatio;
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

function clearTimers(): void {
    if (emergeTimer !== null) {
        clearTimeout(emergeTimer);
        emergeTimer = null;
    }
    if (shiverTimer !== null) {
        clearTimeout(shiverTimer);
        shiverTimer = null;
    }
}

function queueShiver(): void {
    if (shiverTimer !== null) {
        clearTimeout(shiverTimer);
    }
    shivering.value = false;
    shiverTimer = setTimeout(() => {
        shivering.value = true;
        shiverTimer = setTimeout(() => {
            shivering.value = false;
            shiverTimer = null;
        }, SHIVER_MS);
    }, EMERGE_MS);
}

async function play(): Promise<void> {
    clearTimers();
    shivering.value = false;
    opened.value = false;
    retracted.value = true;
    await nextTick();
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            opened.value = true;
            emergeTimer = setTimeout(() => {
                retracted.value = false;
                queueShiver();
                emergeTimer = null;
            }, OPEN_MS + HOLD_MS);
        });
    });
}

async function load(): Promise<void> {
    const t = ++token;
    ready.value = false;
    opened.value = false;
    retracted.value = true;
    shivering.value = false;
    clearTimers();
    handSrc.value = '';
    portal.value = null;

    try {
        const punched = await punchPortalWhite(props.gesture);
        if (t !== token) {
            return;
        }
        handSrc.value = punched.url;
        portal.value = punched.portal;
        ready.value = true;
        await play();
    } catch (e) {
        console.error(e);
        if (t !== token) {
            return;
        }
        handSrc.value = props.gesture.src;
        portal.value = props.gesture.portal;
        ready.value = true;
        await play();
    }
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
    clearTimers();
    ro?.disconnect();
});

defineExpose({ replay: play });
</script>

<template>
    <div
        ref="rootEl"
        class="xen"
        :class="[
            `xen--${gesture.emergeFrom}`,
            { 'xen--open': opened },
        ]"
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
                    :class="{
                        'xen__hand--in': ready && opened,
                        'xen__hand--shiver': shivering && !retracted,
                    }"
                    :src="handSrc"
                    :alt="gesture.label"
                    :style="{
                        transform: handTransform,
                        transformOrigin: handOrigin,
                        filter: ready && opened ? handShadow : undefined,
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
    --xen-ease: steps(14, end);
    --xen-duration: 0.85s;
    --xen-open: 0.56s;
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
    transform: translate(-50%, -50%) scale(0.12, 0.28);
    transform-origin: center center;
    opacity: 0.35;
    filter: blur(1.5px);
    transition:
        transform var(--xen-open) cubic-bezier(0.22, 1.15, 0.36, 1),
        opacity 0.28s ease,
        filter var(--xen-open) ease;
}

.xen--bottom .xen__portal {
    transform: translate(-50%, -50%) scale(0.22, 0.05);
}

.xen--left .xen__portal,
.xen--right .xen__portal {
    transform: translate(-50%, -50%) scale(0.05, 0.24);
}

.xen--open .xen__portal {
    transform: translate(-50%, -50%) scale(1, 1);
    opacity: 1;
    filter: blur(0);
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
    opacity: 0;
    transition:
        transform var(--xen-duration) var(--xen-ease),
        opacity 0.16s ease,
        filter 0.25s ease;
    will-change: transform;
    backface-visibility: hidden;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
}

.xen__hand--in {
    opacity: 1;
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
    0% {
        transform: translate3d(0, 0, 0) rotate(0deg);
    }
    18% {
        transform: translate3d(0, 0, 0) rotate(1.55deg);
    }
    42% {
        transform: translate3d(0, 0, 0) rotate(-1.05deg);
    }
    68% {
        transform: translate3d(0, 0, 0) rotate(0.45deg);
    }
    100% {
        transform: translate3d(0, 0, 0) rotate(0deg);
    }
}

@keyframes xen-pendulum-x {
    0% {
        transform: translate3d(0, 0, 0) rotate(0deg);
    }
    18% {
        transform: translate3d(0, 0, 0) rotate(-1.55deg);
    }
    42% {
        transform: translate3d(0, 0, 0) rotate(1.05deg);
    }
    68% {
        transform: translate3d(0, 0, 0) rotate(-0.45deg);
    }
    100% {
        transform: translate3d(0, 0, 0) rotate(0deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .xen__portal,
    .xen__hand {
        transition-duration: 0.01ms;
    }

    .xen__hand--shiver {
        animation: none;
    }
}
</style>
