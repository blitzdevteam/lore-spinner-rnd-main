<script setup lang="ts">
import XenPortalHand from '../components/XenPortalHand.vue';
import { XEN_GESTURES, type XenGesture } from '../data/gestures';
import { Head, router } from '@inertiajs/vue3';
import { LucideChevronLeft, LucideRotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: null });

const selectedId = ref(XEN_GESTURES[0].id);
const portalRef = ref<InstanceType<typeof XenPortalHand> | null>(null);

const activeGesture = computed<XenGesture>(
    () => XEN_GESTURES.find((g) => g.id === selectedId.value) ?? XEN_GESTURES[0],
);

function selectGesture(id: string): void {
    if (selectedId.value === id) {
        replay();
        return;
    }
    selectedId.value = id;
}

function replay(): void {
    portalRef.value?.replay();
}

function goBack(): void {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
}
</script>

<template>
    <Head title="XEN Hand R&D" />

    <div class="xen-rnd">
        <header class="xen-rnd__header">
            <button type="button" class="xen-rnd__icon-btn" @click="goBack">
                <LucideChevronLeft class="size-4" />
                Back
            </button>

            <div class="xen-rnd__title-block">
                <p class="xen-rnd__eyebrow">R&amp;D</p>
                <h1 class="xen-rnd__title">XEN Hand</h1>
            </div>

            <button type="button" class="xen-rnd__icon-btn" @click="replay">
                <LucideRotateCcw class="size-4" />
                Replay
            </button>
        </header>

        <main class="xen-rnd__main">
            <div class="xen-rnd__stage">
                <XenPortalHand
                    ref="portalRef"
                    :key="activeGesture.id"
                    :gesture="activeGesture"
                />
            </div>

            <div class="xen-rnd__meta">
                <p class="xen-rnd__label">{{ activeGesture.label }}</p>
                <p v-if="activeGesture.caption" class="xen-rnd__caption">{{ activeGesture.caption }}</p>
            </div>

            <section class="xen-rnd__picker">
                <p class="xen-rnd__picker-heading">Gestures</p>
                <div class="xen-rnd__picker-grid">
                    <button
                        v-for="gesture in XEN_GESTURES"
                        :key="gesture.id"
                        type="button"
                        class="xen-rnd__pose-btn"
                        :class="{ 'xen-rnd__pose-btn--active': gesture.id === selectedId }"
                        @click="selectGesture(gesture.id)"
                    >
                        {{ gesture.label }}
                    </button>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.xen-rnd {
    --xen-rnd-bg: #050409;
    display: flex;
    flex-direction: column;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    background: var(--xen-rnd-bg);
    color: white;
}

.xen-rnd__header {
    flex: 0 0 auto;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
}

.xen-rnd__header .xen-rnd__icon-btn:first-child {
    justify-self: start;
}

.xen-rnd__header .xen-rnd__icon-btn:last-child {
    justify-self: end;
}

.xen-rnd__icon-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 9999px;
    border: 1px solid color-mix(in srgb, white 15%, transparent);
    background: transparent;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: color-mix(in srgb, white 70%, transparent);
    transition: border-color 0.15s ease, color 0.15s ease;
}

.xen-rnd__icon-btn:hover {
    border-color: color-mix(in srgb, white 30%, transparent);
    color: white;
}

.xen-rnd__title-block {
    text-align: center;
}

.xen-rnd__eyebrow {
    font-size: 0.65rem;
    letter-spacing: 0.45em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--color-primary-300) 80%, transparent);
}

.xen-rnd__title {
    font-size: clamp(1rem, 3vw, 1.35rem);
    letter-spacing: 0.35em;
    text-transform: uppercase;
    background: linear-gradient(135deg, #9fecf7 0%, #5fdbf0 45%, #009eb5 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.xen-rnd__main {
    flex: 1 1 auto;
    min-height: 0;
    display: grid;
    grid-template-rows: minmax(0, 1fr) auto auto;
    gap: 0.75rem;
    padding: 0 1rem 1rem;
    max-width: 56rem;
    width: 100%;
    margin-inline: auto;
}

.xen-rnd__stage {
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.xen-rnd__meta {
    flex: 0 0 auto;
    text-align: center;
}

.xen-rnd__label {
    font-size: 1rem;
    font-weight: 500;
    letter-spacing: 0.04em;
    color: var(--color-primary-200);
}

.xen-rnd__caption {
    margin-top: 0.15rem;
    font-size: 0.8125rem;
    font-style: italic;
    color: color-mix(in srgb, white 55%, transparent);
}

.xen-rnd__picker {
    flex: 0 0 auto;
}

.xen-rnd__picker-heading {
    margin-bottom: 0.5rem;
    text-align: center;
    font-size: 0.6875rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: color-mix(in srgb, white 40%, transparent);
}

.xen-rnd__picker-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

@media (min-width: 640px) {
    .xen-rnd__picker-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

.xen-rnd__pose-btn {
    border-radius: 0.75rem;
    border: 1px solid color-mix(in srgb, white 10%, transparent);
    background: color-mix(in srgb, white 3%, transparent);
    padding: 0.65rem 0.75rem;
    text-align: left;
    font-size: 0.8125rem;
    color: color-mix(in srgb, white 65%, transparent);
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.xen-rnd__pose-btn:hover {
    border-color: color-mix(in srgb, white 20%, transparent);
    color: white;
}

.xen-rnd__pose-btn--active {
    border-color: color-mix(in srgb, var(--color-primary-400) 60%, transparent);
    background: color-mix(in srgb, var(--color-primary-500) 10%, transparent);
    color: var(--color-primary-100);
}
</style>
