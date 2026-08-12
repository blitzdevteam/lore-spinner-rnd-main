<script setup lang="ts">
/**
 * Simple XEN emerge
 *
 * - Cut white portal out of the PNG
 * - Draw a white ellipse sized to match
 * - Hand on top, clipped at portal center, slides out
 */
import type { XenGesture } from '../data/gestures';
import { xenClip, xenRetract } from '../data/gestures';
import { punchPortalWhite } from '../lib/punchPortal';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

const rootEl = ref<HTMLElement | null>(null);
const handSrc = ref('');
const ready = ref(false);
const retracted = ref(true);
const frameSize = ref({ width: 0, height: 0 });

let token = 0;
let settleTimer: ReturnType<typeof setTimeout> | null = null;
let ro: ResizeObserver | null = null;

const frameStyle = computed(() => {
    const { width, height } = frameSize.value;
    if (width <= 0 || height <= 0) {
        return { width: '100%', height: '100%' };
    }
    return { width: `${Math.round(width)}px`, height: `${Math.round(height)}px` };
});

const portalStyle = computed(() => {
    const p = props.gesture.portal;
    return {
        left: `${p.cx}%`,
        top: `${p.cy}%`,
        width: `${p.rx * 2}%`,
        height: `${p.ry * 2}%`,
        transform: 'translate(-50%, -50%)',
    };
});

const clipStyle = computed(() => ({
    clipPath: xenClip(props.gesture.portal, props.gesture.emergeFrom),
}));

const handTransform = computed(() =>
    retracted.value
        ? xenRetract(props.gesture.emergeFrom, props.gesture.emergeDistance)
        : 'translate3d(0, 0, 0)',
);

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

async function play(): Promise<void> {
    if (settleTimer) {
        clearTimeout(settleTimer);
        settleTimer = null;
    }
    retracted.value = true;
    await nextTick();
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            retracted.value = false;
        });
    });
}

async function load(): Promise<void> {
    const t = ++token;
    ready.value = false;
    retracted.value = true;
    handSrc.value = '';

    try {
        const punched = await punchPortalWhite(
            props.gesture.src,
            props.gesture.emergeFrom,
            props.gesture.portal,
        );
        if (t !== token) {
            return;
        }
        handSrc.value = punched;
        ready.value = true;
        await play();
    } catch (e) {
        console.error(e);
        if (t !== token) {
            return;
        }
        // Fallback: show original unpunched
        handSrc.value = props.gesture.src;
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
    if (settleTimer) {
        clearTimeout(settleTimer);
    }
    ro?.disconnect();
});

defineExpose({ replay: play });
</script>

<template>
    <div ref="rootEl" class="xen">
        <div class="xen__frame" :style="frameStyle">
            <!-- 1. White portal ellipse (under the hand) -->
            <div class="xen__portal" :style="portalStyle" aria-hidden="true" />

            <!-- 2. Hand on top, clipped at portal center, slides out -->
            <div class="xen__clip" :style="clipStyle">
                <img
                    v-if="handSrc"
                    class="xen__hand"
                    :class="{ 'xen__hand--ready': ready }"
                    :src="handSrc"
                    :alt="gesture.label"
                    :style="{ transform: handTransform }"
                    draggable="false"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.xen {
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
    background: #050409;
}

.xen__portal {
    position: absolute;
    z-index: 1;
    border-radius: 50%;
    background: #fff;
    box-shadow:
        0 0 12px rgba(255, 255, 255, 0.55),
        0 0 28px color-mix(in srgb, var(--color-primary-300) 30%, transparent);
    pointer-events: none;
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
        transform 1.15s cubic-bezier(0.22, 0.9, 0.28, 1),
        opacity 0.2s ease;
    will-change: transform;
    backface-visibility: hidden;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
}

.xen__hand--ready {
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .xen__hand {
        transition-duration: 0.01ms;
    }
}
</style>
