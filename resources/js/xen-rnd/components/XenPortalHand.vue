<script setup lang="ts">
/**
 * XEN emerge — taste-calibrated
 *
 * Matte white hole under the hand. Punch baked portal whites.
 * Clip at the exit face (mouth). Slide hand out. Rest = wrist on the hole.
 * Motion: transform only, cubic-bezier. No neon glow.
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
    clipPath: xenClip(props.gesture.portal.mouth, props.gesture.emergeFrom),
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

    try {
        const punched = await punchPortalWhite(props.gesture);
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
    ro?.disconnect();
});

defineExpose({ replay: play });
</script>

<template>
    <div ref="rootEl" class="xen" :class="`xen--${gesture.emergeFrom}`">
        <div class="xen__frame" :style="frameStyle">
            <!-- Portal hole under the hand — matte disc, no neon -->
            <div class="xen__portal" :style="portalStyle" aria-hidden="true">
                <span class="xen__portal-core" />
            </div>

            <!-- Hand clipped at exit face, slides out on top -->
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
    --xen-disc: #f4f4f5;
    --xen-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --xen-duration: 1.05s;
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
        inset 0 1px 0 rgba(255, 255, 255, 0.55),
        inset 0 -2px 6px rgba(0, 0, 0, 0.18),
        0 0 0 1px rgba(255, 255, 255, 0.08);
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
        opacity 0.18s ease;
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
