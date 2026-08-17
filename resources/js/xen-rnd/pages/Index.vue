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
    --xen-rnd-bg: #0a0a0b;
    display: flex;
    flex-direction: column;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    background: var(--xen-rnd-bg);
    color: #f4f4f5;
}

.xen-rnd__header {
    flex: 0 0 auto;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
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
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: transparent;
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    color: rgba(244, 244, 245, 0.65);
    transition:
        border-color 0.2s cubic-bezier(0.16, 1, 0.3, 1),
        color 0.2s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}

.xen-rnd__icon-btn:hover {
    border-color: rgba(255, 255, 255, 0.28);
    color: #f4f4f5;
}

.xen-rnd__icon-btn:active {
    transform: scale(0.98);
}

.xen-rnd__title-block {
    text-align: center;
}

.xen-rnd__eyebrow {
    font-size: 0.625rem;
    letter-spacing: 0.42em;
    text-transform: uppercase;
    color: rgba(244, 244, 245, 0.4);
}

.xen-rnd__title {
    font-size: clamp(0.95rem, 2.6vw, 1.2rem);
    letter-spacing: 0.32em;
    text-transform: uppercase;
    font-weight: 500;
    color: #f4f4f5;
}

.xen-rnd__main {
    flex: 1 1 auto;
    min-height: 0;
    display: grid;
    grid-template-rows: minmax(0, 1fr) auto auto;
    gap: 0.65rem;
    padding: 0.5rem 1rem 1rem;
    max-width: 52rem;
    width: 100%;
    margin-inline: auto;
}

.xen-rnd__stage {
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    width: 100%;
}

.xen-rnd__meta {
    flex: 0 0 auto;
    text-align: center;
    padding-top: 0.15rem;
}

.xen-rnd__label {
    font-size: 0.95rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    color: #e4e4e7;
}

.xen-rnd__caption {
    margin-top: 0.2rem;
    font-size: 0.78rem;
    font-style: italic;
    color: rgba(244, 244, 245, 0.45);
}

.xen-rnd__picker {
    flex: 0 0 auto;
}

.xen-rnd__picker-heading {
    margin-bottom: 0.45rem;
    text-align: center;
    font-size: 0.625rem;
    letter-spacing: 0.28em;
    text-transform: uppercase;
    color: rgba(244, 244, 245, 0.35);
}

.xen-rnd__picker-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.4rem;
}

@media (min-width: 640px) {
    .xen-rnd__picker-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

.xen-rnd__pose-btn {
    border-radius: 0.65rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.025);
    padding: 0.6rem 0.7rem;
    text-align: left;
    font-size: 0.78rem;
    color: rgba(244, 244, 245, 0.6);
    transition:
        border-color 0.2s cubic-bezier(0.16, 1, 0.3, 1),
        color 0.2s cubic-bezier(0.16, 1, 0.3, 1),
        background 0.2s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}

.xen-rnd__pose-btn:hover {
    border-color: rgba(255, 255, 255, 0.18);
    color: #f4f4f5;
}

.xen-rnd__pose-btn:active {
    transform: scale(0.98);
}

.xen-rnd__pose-btn--active {
    border-color: rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.06);
    color: #fafafa;
}
</style>
