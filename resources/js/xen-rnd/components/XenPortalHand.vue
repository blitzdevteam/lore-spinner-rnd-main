<script setup lang="ts">
/**
 * XEN emerge — printer pull + contact depth + tip-fan settle
 *
 * 1. Punch baked white portal
 * 2. Draw matched white ellipse (rim highlight + contact shadow)
 * 3. Clip at portal mouth; stepped slide out
 * 4. Short pendulum shiver from the wrist base (tip fans)
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
const retracted = ref(true);
const shivering = ref(false);
const frameSize = ref({ width: 0, height: 0 });

let token = 0;
let ro: ResizeObserver | null = null;
let shiverTimer: ReturnType<typeof setTimeout> | null = null;

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
        transform: 'translate(-50%, -50%)',
    };
});

const clipStyle = computed(() => ({
    clipPath: xenClip(activePortal.value.mouth, props.gesture.emergeFrom),
}));

/** Pivot at the wrist / portal mouth so the tip fans. */
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
    // Soft cast into the hole where wrist meets portal
    switch (props.gesture.emergeFrom) {
        case 'bottom':
            return 'drop-shadow(0 5px 4px rgba(0, 0, 0, 0.55)) drop-shadow(0 1px 0 rgba(255, 255, 255, 0.18))';
        case 'left':
            return 'drop-shadow(-4px 1px 4px rgba(0, 0, 0, 0.5)) drop-shadow(1px 0 0 rgba(255, 255, 255, 0.14))';
        case 'right':
            return 'drop-shadow(4px 1px 4px rgba(0, 0, 0, 0.5)) drop-shadow(-1px 0 0 rgba(255, 255, 255, 0.14))';
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

function clearShiverTimer(): void {
    if (shiverTimer !== null) {
        clearTimeout(shiverTimer);
        shiverTimer = null;
    }
}

function queueShiver(): void {
    clearShiverTimer();
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
    clearShiverTimer();
    shivering.value = false;
    retracted.value = true;
    await nextTick();
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            retracted.value = false;
            queueShiver();
        });
    });
}

async function load(): Promise<void> {
    const t = ++token;
    ready.value = false;
    retracted.value = true;
    shivering.value = false;
    clearShiverTimer();
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
    clearShiverTimer();
    ro?.disconnect();
});

defineExpose({ replay: play });
</script>

<template>
    <div ref="rootEl" class="xen" :class="`xen--${gesture.emergeFrom}`">
        <div class="xen__frame" :style="frameStyle">
            <!-- Fixed hole — rim highlight + contact shadow into the mouth -->
            <div class="xen__portal" :style="portalStyle" aria-hidden="true">
                <span class="xen__portal-core" />
                <span class="xen__portal-contact" />
            </div>

            <!-- Hand clipped at exit face, feeds out like a printer -->
            <div class="xen__clip" :style="clipStyle">
                <img
                    v-if="handSrc"
                    class="xen__hand"
                    :class="{
                        'xen__hand--in': ready,
                        'xen__hand--shiver': shivering && !retracted,
                    }"
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
    --xen-disc: #f4f4f5;
    --xen-ease: steps(14, end);
    --xen-duration: 0.85s;
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
}

.xen__portal-core {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--xen-disc);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.72),
        inset 0 -3px 7px rgba(0, 0, 0, 0.22),
        inset 0 0 0 1px rgba(255, 255, 255, 0.08),
        0 1px 2px rgba(0, 0, 0, 0.35);
}

/* Soft pool of shade where the wrist sits in the hole */
.xen__portal-contact {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    background: radial-gradient(
        ellipse at center,
        rgba(0, 0, 0, 0.42) 0%,
        rgba(0, 0, 0, 0.18) 42%,
        transparent 72%
    );
}

.xen--bottom .xen__portal-contact {
    left: 18%;
    right: 18%;
    top: 8%;
    height: 55%;
}

.xen--left .xen__portal-contact {
    top: 22%;
    bottom: 22%;
    right: 6%;
    width: 58%;
}

.xen--right .xen__portal-contact {
    top: 22%;
    bottom: 22%;
    left: 6%;
    width: 58%;
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
        opacity 0.12s ease,
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

/* Quick clock-pendulum settle — base locked at mouth, tip fans */
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
    .xen__hand {
        transition-duration: 0.01ms;
    }

    .xen__hand--shiver {
        animation: none;
    }
}
</style>
