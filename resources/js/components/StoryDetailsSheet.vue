<script setup lang="ts">
import { show as storyShow } from '@/wayfinder/routes/stories';
import { storyCtaLabel, storyIsInteractive } from '@/utils/storyPreview';
import { Link, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';

export interface StorySheetData {
    id: string | number;
    title: string;
    cover: string;
    themes?: string[];
    category?: string;
    rating?: string;
    isComingSoon: boolean;
    teaser?: string | null;
    slug?: string;
    cta?: 'play' | 'continue' | 'coming-soon';
}

const props = defineProps<{
    story: StorySheetData | null;
}>();

const emit = defineEmits<{ close: [] }>();

function close() {
    emit('close');
}

const page = usePage();

// Close if the route changes while open (prevents ghost overlays after navigation).
watch(
    () => page.url,
    () => {
        if (props.story) close();
    },
);

// ── Body scroll lock & keyboard ───────────────────────────────────────────────
watch(
    () => props.story,
    (val) => {
        if (typeof document === 'undefined') return;
        if (val) {
            document.body.classList.add('overflow-hidden');
            window.addEventListener('keydown', onKeydown);
        } else {
            document.body.classList.remove('overflow-hidden');
            window.removeEventListener('keydown', onKeydown);
        }
    },
);

onUnmounted(() => {
    if (typeof document === 'undefined') return;
    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
});

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') close();
}

// ── Swipe-down-to-close (cover zone drag) ────────────────────────────────────
const sheetEl = ref<HTMLElement | null>(null);
let touchStartY = 0;
let dragging = false;

function onCoverTouchStart(e: TouchEvent) {
    const target = e.target as HTMLElement;
    if (target.closest('button')) return;
    touchStartY = e.touches[0]!.clientY;
    dragging = true;
    if (sheetEl.value) sheetEl.value.style.transition = 'none';
}

function onCoverTouchMove(e: TouchEvent) {
    if (!dragging) return;
    const delta = e.touches[0]!.clientY - touchStartY;
    if (delta > 0 && sheetEl.value) {
        sheetEl.value.style.transform = `translateY(${delta}px)`;
    }
}

function onCoverTouchEnd(e: TouchEvent) {
    if (!dragging) return;
    dragging = false;
    const delta = e.changedTouches[0]!.clientY - touchStartY;
    if (sheetEl.value) {
        sheetEl.value.style.transition = '';
        sheetEl.value.style.transform = '';
    }
    if (delta > 80) close();
}

</script>

<template>
    <Teleport to="body">
        <Transition name="sheet-overlay">
            <div
                v-if="story"
                class="fixed inset-0 z-[300] flex items-end justify-center sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="`sheet-title-${story.id}`"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/80 backdrop-blur-sm"
                    @click="close"
                />

                <!-- Panel -->
                <Transition name="sheet-panel" appear>
                    <div
                        v-if="story"
                        ref="sheetEl"
                        class="story-sheet relative z-10 flex w-full max-w-md flex-col overflow-hidden rounded-t-2xl border border-white/[0.08] bg-[#0c0c0c] will-change-transform sm:rounded-2xl"
                        @click.stop
                    >
                        <!-- Top accent glow line -->
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 z-10 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"
                            aria-hidden="true"
                        />

                        <!-- ── Cover zone: drag area + controls overlaid ──── -->
                        <div
                            class="relative shrink-0 cursor-grab active:cursor-grabbing sm:cursor-default"
                            @touchstart.passive="onCoverTouchStart"
                            @touchmove.passive="onCoverTouchMove"
                            @touchend="onCoverTouchEnd"
                        >
                            <!-- Cover image -->
                            <div class="cover-frame">
                                <img
                                    v-if="story.cover"
                                    :src="story.cover"
                                    :alt="story.title"
                                    class="absolute inset-0 size-full object-cover"
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 bg-gradient-to-br from-[#1a1a2e] via-[#0f0f1a] to-[#0c0c0c]"
                                />
                                <!-- Bottom fade into sheet background -->
                                <div
                                    class="pointer-events-none absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-[#0c0c0c] to-transparent"
                                    aria-hidden="true"
                                />
                                <!-- Top fade for controls legibility -->
                                <div
                                    class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-black/65 to-transparent"
                                    aria-hidden="true"
                                />
                            </div>

                            <!-- Drag pill indicator (mobile only) -->
                            <div
                                class="pointer-events-none absolute inset-x-0 top-0 flex justify-center pt-2.5 sm:hidden"
                                aria-hidden="true"
                            >
                                <div class="h-[0.25rem] w-9 rounded-full bg-white/30" />
                            </div>

                            <!-- Close button (top right, over cover) -->
                            <button
                                type="button"
                                class="absolute right-3 top-3 z-10 flex size-8 items-center justify-center rounded-full bg-black/55 text-white ring-1 ring-white/15 backdrop-blur-sm transition hover:bg-black/75"
                                aria-label="Close"
                                @click="close"
                            >
                                <X class="size-[1.0625rem]" :stroke-width="2.25" />
                            </button>
                        </div>

                        <!-- ── Scrollable content ─────────────────────────── -->
                        <div
                            class="flex flex-1 flex-col gap-4 overflow-y-auto overscroll-contain px-5 pb-7 pt-2 sm:px-6 sm:pb-8"
                        >
                            <!-- Title -->
                            <h2
                                :id="`sheet-title-${story.id}`"
                                class="text-[1.375rem] font-bold leading-tight tracking-tight text-white sm:text-2xl"
                            >
                                {{ story.title }}
                            </h2>

                            <!-- Themes / genres -->
                            <div
                                v-if="story.themes?.length || story.category"
                                class="flex flex-wrap items-center gap-x-1.5 gap-y-1"
                            >
                                <template v-if="story.themes?.length">
                                    <template v-for="(theme, i) in story.themes" :key="theme">
                                        <span v-if="i > 0" class="text-[0.5rem] leading-none text-white/25">●</span>
                                        <span class="text-[0.8125rem] font-medium text-primary">{{ theme }}</span>
                                    </template>
                                </template>
                                <template v-else-if="story.category">
                                    <span class="text-[0.8125rem] font-medium text-primary">{{ story.category }}</span>
                                </template>
                            </div>

                            <!-- Status + meta badges -->
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex h-6 items-center rounded-full px-2.5 text-[0.625rem] font-semibold uppercase tracking-[0.09em]"
                                    :class="
                                        story.isComingSoon
                                            ? 'border border-white/15 bg-white/5 text-white/40'
                                            : 'border border-primary/40 bg-primary/10 text-primary'
                                    "
                                >
                                    {{ story.isComingSoon ? 'Coming Soon' : 'Published' }}
                                </span>
                                <span
                                    v-if="story.rating"
                                    class="inline-flex h-6 items-center rounded-full border border-white/15 bg-white/5 px-2.5 text-[0.625rem] font-medium uppercase tracking-[0.09em] text-white/40"
                                >
                                    {{ story.rating }}
                                </span>
                                <span
                                    v-if="story.category && (story.themes?.length ?? 0) > 0"
                                    class="inline-flex h-6 items-center rounded-full border border-white/15 bg-white/5 px-2.5 text-[0.625rem] font-medium text-white/40"
                                >
                                    {{ story.category }}
                                </span>
                            </div>

                            <!-- Full description -->
                            <p
                                v-if="story.teaser"
                                class="text-[0.9375rem] leading-[1.7] text-[#9a9a9a]"
                            >
                                {{ story.teaser }}
                            </p>

                            <!-- Primary CTA -->
                            <div class="mt-1">
                                <Link
                                    v-if="storyIsInteractive(story) && story.slug"
                                    :href="storyShow(story.slug).url"
                                    class="flex h-[2.125rem] w-full items-center justify-center rounded-[0.3125rem] border-[0.5px] border-[#59c1ca] bg-[#0e1f22] px-2.5 text-[0.9rem] font-normal text-white no-underline transition-colors hover:border-[#8fcbd3] hover:bg-[#163f46]"
                                    @click="close"
                                >
                                    {{ storyCtaLabel(story) }}
                                </Link>
                                <div
                                    v-else
                                    class="flex h-[2.125rem] w-full cursor-default items-center justify-center rounded-[0.3125rem] border-[0.5px] border-[#8f8f8f] bg-transparent px-2.5 text-[0.9rem] font-normal text-[#8f8f8f]"
                                >
                                    {{ storyCtaLabel(story) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Sheet max-height & shadow ────────────────────────────────────────────── */
.story-sheet {
    max-height: min(88svh, 680px);
    box-shadow:
        0 -8px 60px rgba(0, 0, 0, 0.7),
        0 0 0 1px rgba(111, 175, 186, 0.1);
}

@media (min-width: 640px) {
    .story-sheet {
        max-height: min(90vh, 720px);
    }
}

/* ── Cover frame ─────────────────────────────────────────────────────────── */
.cover-frame {
    position: relative;
    height: 14rem;
    width: 100%;
    overflow: hidden;
}

@media (min-width: 640px) {
    .cover-frame {
        height: 16rem;
    }
}

/* ── Backdrop fade ───────────────────────────────────────────────────────── */
.sheet-overlay-enter-active,
.sheet-overlay-leave-active {
    transition: opacity 0.28s ease;
}

.sheet-overlay-enter-from,
.sheet-overlay-leave-to {
    opacity: 0;
}

/* ── Panel: mobile = slide up from bottom; desktop = scale + fade ────────── */
.sheet-panel-enter-active {
    transition:
        opacity 0.36s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}

.sheet-panel-leave-active {
    transition:
        opacity 0.22s ease,
        transform 0.24s ease;
}

.sheet-panel-enter-from {
    opacity: 0;
    transform: translateY(100%);
}

.sheet-panel-leave-to {
    opacity: 0;
    transform: translateY(40%);
}

@media (min-width: 640px) {
    .sheet-panel-enter-from {
        opacity: 0;
        transform: translateY(1.5rem) scale(0.97);
    }

    .sheet-panel-leave-to {
        opacity: 0;
        transform: translateY(0.5rem) scale(0.98);
    }
}
</style>
