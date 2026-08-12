<script setup lang="ts">
/**
 * XEN emerge — printer / stop-motion pull
 *
 * 1. Punch baked white portal (+ clean wrist past the mouth)
 * 2. Draw matched white ellipse under the hand
 * 3. Clip at portal mouth; slide hand out in stepped frames
 * 4. Rest = wrist seated on the hole, centered like the original
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
const frameSize = ref({ width: 0, height: 0 });

let token = 0;
let ro: ResizeObserver | null = null;

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
    ro?.disconnect();
});

defineExpose({ replay: play });
</script>

<template>
    <div ref="rootEl" class="xen" :class="`xen--${gesture.emergeFrom}`">
        <div class="xen__frame" :style="frameStyle">
            <!-- Fixed hole — does not move -->
            <div class="xen__portal" :style="portalStyle" aria-hidden="true" />

            <!-- Hand clipped at exit face, feeds out like a printer -->
            <div class="xen__clip" :style="clipStyle">
                <img
                    v-if="handSrc"
                    class="xen__hand"
                    :class="{ 'xen__hand--in': ready }"
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
    --xen-void: #0a0a0b;
    --xen-disc: #f5f5f5;
    /* Stepped pull = stop-motion / printer feed */
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
    border-radius: 50%;
    background: var(--xen-disc);
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
    /* Frame is aspect-locked to the PNG — fill keeps 1:1 pixel mapping */
    object-fit: fill;
    opacity: 0;
    transition:
        transform var(--xen-duration) var(--xen-ease),
        opacity 0.12s ease;
    will-change: transform;
    backface-visibility: hidden;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
}

.xen__hand--in {
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .xen__hand {
        transition-duration: 0.01ms;
    }
}
</style>
