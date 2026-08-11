<script setup lang="ts">
import type { XenGesture } from '../data/gestures';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    gesture: XenGesture;
}>();

const emerged = ref(false);
const imageReady = ref(false);

const portalStyle = computed(() => {
    const g = props.gesture;
    const base = {
        width: `${g.portalWidth}%`,
        height: `${g.portalHeight}%`,
    };

    if (g.portalSide === 'bottom') {
        return {
            ...base,
            left: '50%',
            bottom: `${g.portalInset}%`,
            transform: 'translateX(-50%)',
        };
    }

    if (g.portalSide === 'left') {
        return {
            ...base,
            left: `${g.portalInset}%`,
            top: '50%',
            transform: 'translateY(-50%)',
        };
    }

    return {
        ...base,
        right: `${g.portalInset}%`,
        top: '50%',
        transform: 'translateY(-50%)',
    };
});

const handStyle = computed(() => {
    const g = props.gesture;
    const offset = emerged.value ? 0 : g.travel * g.direction;

    if (g.portalSide === 'bottom') {
        return {
            left: `calc(50% + ${g.handNudgeX}%)`,
            bottom: `${g.portalInset}%`,
            transform: `translate(-50%, ${offset}%)`,
        };
    }

    if (g.portalSide === 'left') {
        const x = emerged.value ? 0 : -g.travel * g.direction;
        return {
            left: `${g.portalInset}%`,
            top: `calc(50% + ${g.handNudgeY}%)`,
            transform: `translate(${x}%, -50%)`,
        };
    }

    const x = emerged.value ? 0 : g.travel * g.direction;
    return {
        right: `${g.portalInset}%`,
        top: `calc(50% + ${g.handNudgeY}%)`,
        transform: `translate(${-x}%, -50%)`,
    };
});

async function playEmergence(): Promise<void> {
    emerged.value = false;
    await nextTick();
    if (!imageReady.value) {
        return;
    }
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            emerged.value = true;
        });
    });
}

function onImageLoad(): void {
    imageReady.value = true;
    void playEmergence();
}

watch(
    () => props.gesture.id,
    () => {
        imageReady.value = false;
        emerged.value = false;
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
        :class="[
            `xen-portal-hand--${gesture.portalSide}`,
            { 'xen-portal-hand--emerged': emerged },
        ]"
    >
        <div class="xen-portal-hand__stage">
            <div class="xen-portal-hand__mask">
                <img
                    :key="gesture.id"
                    :src="gesture.src"
                    :alt="gesture.label"
                    class="xen-portal-hand__image"
                    :style="handStyle"
                    draggable="false"
                    @load="onImageLoad"
                />
            </div>

            <div class="xen-portal-hand__portal" :style="portalStyle" aria-hidden="true">
                <div class="xen-portal-hand__portal-rim" />
                <div class="xen-portal-hand__portal-glow" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.xen-portal-hand {
    --xen-portal-white: #ffffff;
    --xen-portal-glow: color-mix(in srgb, var(--color-primary-300) 35%, white);
    --xen-emerge-duration: 1.05s;
    --xen-emerge-ease: cubic-bezier(0.18, 0.92, 0.22, 1);
    width: min(92vw, 52rem);
    aspect-ratio: 1;
    position: relative;
}

.xen-portal-hand__stage {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.xen-portal-hand__mask {
    position: absolute;
    inset: 0;
    overflow: hidden;
}

.xen-portal-hand__image {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: contain;
    transform-origin: center center;
    transition: transform var(--xen-emerge-duration) var(--xen-emerge-ease);
    will-change: transform;
}

/* Bottom portal: hide baked ellipse; our rim stays fixed at the slit */
.xen-portal-hand--bottom .xen-portal-hand__image {
    object-position: center bottom;
    mask-image: linear-gradient(to top, transparent 0%, transparent 5%, #000 9%, #000 100%);
}

.xen-portal-hand--left .xen-portal-hand__image {
    object-position: left center;
    mask-image: linear-gradient(to right, transparent 0%, transparent 5%, #000 9%, #000 100%);
}

.xen-portal-hand--right .xen-portal-hand__image {
    object-position: right center;
    mask-image: linear-gradient(to left, transparent 0%, transparent 5%, #000 9%, #000 100%);
}

.xen-portal-hand__portal {
    position: absolute;
    z-index: 3;
    pointer-events: none;
}

.xen-portal-hand__portal-rim {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--xen-portal-white);
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--xen-portal-white) 80%, #000 20%),
        0 0 18px color-mix(in srgb, var(--xen-portal-glow) 55%, transparent),
        inset 0 -2px 8px color-mix(in srgb, #000 12%, transparent);
}

.xen-portal-hand__portal-glow {
    position: absolute;
    inset: -35%;
    border-radius: 50%;
    background: radial-gradient(
        ellipse at center,
        color-mix(in srgb, var(--xen-portal-glow) 45%, transparent) 0%,
        transparent 70%
    );
    z-index: -1;
}

.xen-portal-hand:not(.xen-portal-hand--emerged) .xen-portal-hand__portal-rim {
    animation: xen-portal-breathe 1.05s var(--xen-emerge-ease) forwards;
}

@keyframes xen-portal-breathe {
    0% {
        transform: scale(0.92);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .xen-portal-hand__image {
        transition-duration: 0.01ms;
    }

    .xen-portal-hand:not(.xen-portal-hand--emerged) .xen-portal-hand__portal-rim {
        animation: none;
    }
}
</style>
