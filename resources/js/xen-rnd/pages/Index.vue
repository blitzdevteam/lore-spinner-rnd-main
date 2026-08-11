<script setup lang="ts">
import XenPortalHand from '../components/XenPortalHand.vue';
import { XEN_GESTURES, type XenGesture } from '../data/gestures';
import { Head } from '@inertiajs/vue3';
import { LucideChevronLeft, LucideRotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

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

    <div class="xen-rnd min-h-dvh bg-[#050409] text-white">
        <header class="xen-rnd__header flex items-center justify-between px-4 py-4 md:px-8">
            <button
                type="button"
                class="xen-rnd__icon-btn inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-2 text-sm text-white/70 transition hover:border-white/30 hover:text-white"
                @click="goBack"
            >
                <LucideChevronLeft class="size-4" />
                Back
            </button>

            <div class="text-center">
                <p class="text-[0.65rem] uppercase tracking-[0.45em] text-primary-300/80">R&amp;D</p>
                <h1 class="xen-rnd__title text-xl uppercase tracking-[0.35em] md:text-2xl">XEN Hand</h1>
            </div>

            <button
                type="button"
                class="xen-rnd__icon-btn inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-2 text-sm text-white/70 transition hover:border-white/30 hover:text-white"
                @click="replay"
            >
                <LucideRotateCcw class="size-4" />
                Replay
            </button>
        </header>

        <main class="xen-rnd__main mx-auto flex max-w-6xl flex-col items-center gap-8 px-4 pb-10 md:px-8">
            <section class="xen-rnd__stage flex w-full flex-col items-center">
                <XenPortalHand
                    ref="portalRef"
                    :key="activeGesture.id"
                    :gesture="activeGesture"
                />

                <div class="mt-6 text-center">
                    <p class="text-lg font-medium tracking-wide text-primary-200">{{ activeGesture.label }}</p>
                    <p v-if="activeGesture.caption" class="mt-1 text-sm italic text-white/55">
                        {{ activeGesture.caption }}
                    </p>
                </div>
            </section>

            <section class="xen-rnd__picker w-full max-w-3xl">
                <p class="mb-3 text-center text-xs uppercase tracking-[0.3em] text-white/40">Gestures</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                    <button
                        v-for="gesture in XEN_GESTURES"
                        :key="gesture.id"
                        type="button"
                        class="xen-rnd__pose-btn rounded-xl border px-3 py-3 text-left text-sm transition"
                        :class="
                            gesture.id === selectedId
                                ? 'border-primary-400/60 bg-primary-500/10 text-primary-100'
                                : 'border-white/10 bg-white/[0.03] text-white/65 hover:border-white/20 hover:text-white'
                        "
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
.xen-rnd__title {
    background: linear-gradient(135deg, #9fecf7 0%, #5fdbf0 45%, #009eb5 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.xen-rnd__stage {
    min-height: min(72vh, 42rem);
    justify-content: center;
}
</style>
