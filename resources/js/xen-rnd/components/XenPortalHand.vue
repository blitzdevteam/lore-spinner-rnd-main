<script setup lang="ts">
/**
 * Synced printer-hole emerge
 *
 * Runtime-split each PNG into:
 *   - hand layer (portal whites punched transparent)
 *   - portal layer (only the white hole, fixed)
 *
 * Rest = hand @ 0 + portal = exact original.
 * Motion = hand slides through a clip at the portal mouth; portal stays put.
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
const handReady = ref(false);
const portalReady = ref(false);

let loadToken = 0;

const stageStyle = computed(() => ({
    '--xen-aspect': String(props.gesture.aspectRatio),
}));

const holeClipStyle = computed(() => {
    if (!layers.value) {
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
    if (!layers.value || layers.value.portalUrl) {
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

const bothReady = computed(() => {
    if (!layers.value) {
        return false;
    }
    if (layers.value.portalUrl) {
        return handReady.value && portalReady.value;
    }
    return handReady.value;
});

async function playEmergence(): Promise<void> {
    retracted.value = true;
    await nextTick();
    if (!bothReady.value) {
        return;
    }
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            retracted.value = false;
        });
    });
}

async function loadLayers(): Promise<void> {
    const token = ++loadToken;
    loading.value = true;
    error.value = '';
    handReady.value = false;
    portalReady.value = false;
    retracted.value = true;
    layers.value = null;

    try {
        const split = await splitXenLayers(props.gesture.src, props.gesture.emergeFrom);
        if (token !== loadToken) {
            return;
        }
        layers.value = split;
        loading.value = false;
    } catch (e) {
        if (token !== loadToken) {
            return;
        }
        error.value = e instanceof Error ? e.message : 'Failed to split image';
        loading.value = false;
    }
}

function onHandLoad(): void {
    handReady.value = true;
    void playEmergence();
}

function onPortalLoad(): void {
    portalReady.value = true;
    void playEmergence();
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

defineExpose({
    replay: playEmergence,
});
</script>

<template>
    <div
        class="xen-portal-hand"
        :class="{
            'xen-portal-hand--loading': loading || !bothReady,
            [`xen-portal-hand--from-${gesture.emergeFrom}`]: true,
        }"
        :style="stageStyle"
    >
        <div class="xen-portal-hand__stage">
            <p v-if="error" class="xen-portal-hand__error">{{ error }}</p>

            <template v-if="layers">
                <!-- Hand feeds through the hole -->
                <div class="xen-portal-hand__hole" :style="holeClipStyle">
                    <div class="xen-portal-hand__track" :style="{ transform: trackTransform }">
                        <img
                            class="xen-portal-hand__layer"
                            :src="layers.handUrl"
                            :alt="gesture.label"
                            draggable="false"
                            @load="onHandLoad"
                        />
                    </div>
                </div>

                <!-- Portal stays fixed — exact pixels from the original -->
                <img
                    v-if="layers.portalUrl"
                    class="xen-portal-hand__portal-layer"
                    :src="layers.portalUrl"
                    alt=""
                    draggable="false"
                    @load="onPortalLoad"
                />

                <!-- Synthetic rim only when the art has no baked portal -->
                <div
                    v-else-if="syntheticRimStyle"
                    class="xen-portal-hand__synthetic-rim"
                    :style="syntheticRimStyle"
                    aria-hidden="true"
                />
            </template>
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

.xen-portal-hand__layer,
.xen-portal-hand__portal-layer {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center center;
    display: block;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
}

.xen-portal-hand__portal-layer {
    z-index: 3;
}

.xen-portal-hand--loading .xen-portal-hand__hole,
.xen-portal-hand--loading .xen-portal-hand__portal-layer,
.xen-portal-hand--loading .xen-portal-hand__synthetic-rim {
    opacity: 0;
}

.xen-portal-hand__synthetic-rim {
    position: absolute;
    z-index: 3;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 0 12px color-mix(in srgb, #fff 50%, transparent);
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
