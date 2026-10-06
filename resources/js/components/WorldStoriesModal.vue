<script setup lang="ts">
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { WorldInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    world: WorldInterface | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const isDesktopHover = useDesktopStoryPreview();
const hoveredStoryId = ref<number | null>(null);
const stories = computed(() => props.world?.stories ?? []);
const storyCountLabel = computed(() => {
    const count = props.world?.stories_count ?? stories.value.length;

    return count === 1 ? '1 story' : `${count} stories`;
});

function close(): void {
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

function isPlayable(slug: string): boolean {
    const story = stories.value.find((item) => item.slug === slug);

    return Boolean(story?.status?.value === StoryStatusEnum.PUBLISHED && isStoryPlayable(story));
}

watch(
    () => page.url,
    () => {
        if (props.world) {
            close();
        }
    },
);

watch(
    () => props.world,
    (world) => {
        if (typeof document === 'undefined') {
            return;
        }

        if (world) {
            document.body.classList.add('overflow-hidden');
            window.addEventListener('keydown', onKeydown);
        } else {
            document.body.classList.remove('overflow-hidden');
            window.removeEventListener('keydown', onKeydown);
        }
    },
);

onUnmounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="world-modal-overlay">
            <div
                v-if="world"
                class="fixed inset-0 z-[300] flex items-end justify-center sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="`world-modal-title-${world.id}`"
            >
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="close" />

                <Transition name="world-modal-panel" appear>
                    <div
                        v-if="world"
                        class="world-modal relative z-10 flex w-full max-w-5xl flex-col overflow-hidden rounded-t-2xl border border-white/[0.08] bg-black sm:rounded-2xl"
                        @click.stop
                    >
                        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                            <img
                                v-if="world.background"
                                :src="world.background"
                                alt=""
                                class="absolute inset-0 size-full object-cover object-center"
                            />
                            <div
                                v-else
                                class="absolute inset-0 bg-gradient-to-br from-[#1a1a2e] via-[#0f0f1a] to-[#0c0c0c]"
                            />
                            <div class="world-modal__shade absolute inset-0" />
                        </div>

                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 z-20 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"
                            aria-hidden="true"
                        />

                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 z-20 flex justify-center pt-2.5 sm:hidden"
                            aria-hidden="true"
                        >
                            <div class="h-[0.25rem] w-9 rounded-full bg-white/30" />
                        </div>

                        <button
                            type="button"
                            class="absolute top-3 right-3 z-20 flex size-8 items-center justify-center rounded-full bg-black/55 text-white ring-1 ring-white/15 backdrop-blur-sm transition hover:bg-black/75"
                            aria-label="Close"
                            @click="close"
                        >
                            <X class="size-[1.0625rem]" :stroke-width="2.25" />
                        </button>

                        <div class="world-modal__scroll relative z-[1] min-h-0 flex-1 overflow-y-auto overscroll-contain">
                            <div class="world-modal__hero flex flex-col justify-end gap-3 px-5 pb-2 sm:px-7 sm:pb-3">
                                <div class="flex flex-col gap-2">
                                    <p class="text-[0.6875rem] font-medium tracking-[0.16em] text-white/70 uppercase">
                                        {{ storyCountLabel }}
                                    </p>
                                    <h2
                                        :id="`world-modal-title-${world.id}`"
                                        class="font-marcellus-sc text-[1.75rem] leading-none text-white sm:text-[2.25rem]"
                                    >
                                        {{ world.title }}
                                    </h2>
                                    <p v-if="world.tagline" class="max-w-xl text-[0.8125rem] font-light text-white/85">
                                        {{ world.tagline }}
                                    </p>
                                </div>

                                <div v-if="world.tags.length" class="flex flex-wrap items-center gap-2">
                                    <span
                                        v-for="tag in world.tags"
                                        :key="tag"
                                        class="flex h-[1.875rem] items-center rounded-[5px] border-[0.5px] border-[#373737] bg-black/80 px-2 text-[0.8125rem] font-light text-[#8f8f8f]"
                                    >
                                        {{ tag }}
                                    </span>
                                </div>
                            </div>

                            <section class="world-modal__sheet">
                                <div class="world-modal__veil" aria-hidden="true" />

                                <div class="world-modal__sheet-body">
                                    <p v-if="world.description" class="world-modal__copy max-w-2xl text-sm leading-relaxed text-[#d2d2d2]">
                                        {{ world.description }}
                                    </p>

                                    <div class="flex min-w-0 flex-col gap-3">
                                        <h3 class="world-modal__copy text-sm font-medium tracking-wide text-white">Available stories</h3>

                                        <div
                                            v-if="stories.length"
                                            class="world-modal__stories-slider"
                                            role="list"
                                            aria-label="Available stories"
                                        >
                                            <HomePortraitStoryCard
                                                v-for="story in stories"
                                                :key="story.id"
                                                class="world-modal__story-slide"
                                                :title="story.title"
                                                :cover="resolveStoryCover(story.slug, story.cover)"
                                                :themes="getStoryDescriptorThemes(story.slug)"
                                                :teaser="story.teaser"
                                                :playable="isPlayable(story.slug)"
                                                :slug="story.slug"
                                                :focused="isDesktopHover && hoveredStoryId === story.id"
                                                :is-desktop-hover="true"
                                                :expand-on-hover="isDesktopHover"
                                                @mouseenter="hoveredStoryId = story.id"
                                                @mouseleave="hoveredStoryId = null"
                                            />
                                        </div>
                                        <p v-else class="text-sm text-[#8f8f8f]">This world has no published stories yet.</p>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.world-modal {
    height: min(92svh, 820px);
    box-shadow:
        0 -8px 60px rgba(0, 0, 0, 0.7),
        0 0 0 1px rgba(111, 175, 186, 0.1);
}

.world-modal__hero {
    min-height: 15.5rem;
}

.world-modal__hero p,
.world-modal__hero h2 {
    text-shadow: 0 1px 14px rgb(0 0 0 / 0.72);
}

.world-modal__shade {
    background-image:
        linear-gradient(to top, rgba(0, 0, 0, 0.06) 0%, rgba(0, 0, 0, 0.03) 42%, rgba(0, 0, 0, 0.12) 100%),
        linear-gradient(105deg, rgba(0, 0, 0, 0.2) 8%, rgba(0, 0, 0, 0) 48%);
}

.world-modal__scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.22) transparent;
}

.world-modal__sheet {
    position: relative;
    margin-top: -4rem;
}

.world-modal__veil {
    pointer-events: none;
    position: absolute;
    inset: 0;
    top: -12rem;
    background: linear-gradient(
        to bottom,
        rgb(0 0 0 / 0) 0,
        rgb(0 0 0 / 0) 9rem,
        rgb(0 0 0 / 0.12) 12rem,
        rgb(0 0 0 / 0.28) 15rem,
        rgb(0 0 0 / 0.4) 100%
    );
}

.world-modal__sheet-body {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 5.5rem 1.25rem 1.75rem;
}

.world-modal__copy {
    text-shadow: 0 1px 12px rgb(0 0 0 / 0.85);
}

.world-modal__stories-slider {
    --story-portrait-card-width: min(11.25rem, 42vw);
    display: flex;
    align-items: stretch;
    flex-wrap: nowrap;
    gap: 0.625rem;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -ms-overflow-style: none;
    margin-inline: -1.25rem;
    padding-inline: 1.25rem;
    padding-bottom: 0.25rem;
}

.world-modal__stories-slider::-webkit-scrollbar {
    display: none;
}

.world-modal__story-slide {
    flex: 0 0 var(--story-portrait-card-width, 11.25rem);
    scroll-snap-align: start;
}

@media (min-width: 640px) {
    .world-modal {
        height: min(90vh, 860px);
    }

    .world-modal__hero {
        min-height: 18rem;
    }

    .world-modal__sheet {
        margin-top: -5rem;
    }

    .world-modal__veil {
        top: -14rem;
        background: linear-gradient(
            to bottom,
            rgb(0 0 0 / 0) 0,
            rgb(0 0 0 / 0) 11rem,
            rgb(0 0 0 / 0.12) 14rem,
            rgb(0 0 0 / 0.28) 17rem,
            rgb(0 0 0 / 0.4) 100%
        );
    }

    .world-modal__sheet-body {
        padding: 6.5rem 1.75rem 2rem;
    }

    .world-modal__stories-slider {
        --story-portrait-card-width: 11.25rem;
        margin-inline: -1.75rem;
        padding-inline: 1.75rem;
    }
}

.world-modal-overlay-enter-active,
.world-modal-overlay-leave-active {
    transition: opacity 0.28s ease;
}

.world-modal-overlay-enter-from,
.world-modal-overlay-leave-to {
    opacity: 0;
}

.world-modal-panel-enter-active {
    transition:
        opacity 0.36s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}

.world-modal-panel-leave-active {
    transition:
        opacity 0.22s ease,
        transform 0.24s ease;
}

.world-modal-panel-enter-from {
    opacity: 0;
    transform: translateY(100%);
}

.world-modal-panel-leave-to {
    opacity: 0;
    transform: translateY(40%);
}

@media (min-width: 640px) {
    .world-modal-panel-enter-from {
        opacity: 0;
        transform: translateY(1.5rem) scale(0.97);
    }

    .world-modal-panel-leave-to {
        opacity: 0;
        transform: translateY(0.5rem) scale(0.98);
    }
}

@media (prefers-reduced-motion: reduce) {
    .world-modal-overlay-enter-active,
    .world-modal-overlay-leave-active,
    .world-modal-panel-enter-active,
    .world-modal-panel-leave-active {
        transition: none;
    }
}
</style>
