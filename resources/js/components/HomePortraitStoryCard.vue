<script setup lang="ts">
import StoryCardCover from '@/components/StoryCardCover.vue';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, withDefaults } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        cover: string;
        themes: string[];
        mood?: string;
        genre?: string;
        teaser?: string | null;
        playable: boolean;
        slug?: string;
        focused: boolean;
        isDesktopHover: boolean;
        /** Carousel hover reveal — disable in grid layouts. */
        expandOnHover?: boolean;
        showCta?: boolean;
    }>(),
    {
        expandOnHover: true,
        mood: undefined,
        genre: undefined,
        showCta: true,
    },
);

const emit = defineEmits<{ preview: [] }>();

const storyUrl = computed(() => (props.playable && props.slug ? storyShow(props.slug).url : undefined));

const ctaLabel = computed(() => (props.playable ? 'Play' : 'Coming Soon'));
</script>

<template>
    <component
        :is="isDesktopHover ? (storyUrl ? Link : 'div') : storyUrl ? Link : 'button'"
        :href="storyUrl"
        type="button"
        class="hp-card group block h-full min-w-0 border-0 bg-transparent p-0 text-left outline-none"
        :class="[
            expandOnHover && focused && 'hp-card--focused',
            !expandOnHover && 'hp-card--static',
            !isDesktopHover ? 'cursor-pointer' : storyUrl ? 'cursor-pointer no-underline' : 'cursor-default',
        ]"
        :aria-label="storyUrl ? `Open ${title}` : `Preview ${title}`"
        @click="!isDesktopHover && !storyUrl && emit('preview')"
    >
        <div class="hp-card__frame">
            <div class="hp-card__cover-wrap p-1">
                <StoryCardCover :src="cover" :title="title" />

                <div class="hp-card__image-reveal" :class="expandOnHover && focused && 'hp-card__image-reveal--open'" aria-hidden="true">
                    <div class="hp-card__image-reveal-inner">
                        <div v-if="themes.length" class="hp-card__themes">
                            <template v-for="(theme, i) in themes" :key="theme">
                                <span v-if="i > 0" class="hp-card__theme-dot" aria-hidden="true">•</span>
                                <span class="hp-card__theme">{{ theme }}</span>
                            </template>
                        </div>
                        <p v-if="teaser" class="hp-card__teaser">{{ teaser }}</p>
                    </div>
                </div>
            </div>

            <div class="hp-card__body">
                <div class="hp-card__info">
                    <p class="hp-card__title">{{ title }}</p>
                </div>

                <div v-if="showCta" class="hp-card__cta" :class="playable ? 'hp-card__cta--active' : 'hp-card__cta--disabled'">
                    {{ ctaLabel }}
                </div>
            </div>
        </div>
    </component>
</template>

<style scoped>
.hp-card {
    text-decoration: none;
}

.hp-card__frame {
    display: flex;
    height: 100%;
    width: var(--story-portrait-card-width, min(13.5rem, 75vw));
    flex-direction: column;
    overflow: hidden;
    border-radius: var(--story-card-radius);
    border: var(--story-card-border);
    background: var(--story-card-bg);
    box-shadow: var(--story-card-shadow);
    transition:
        transform 200ms ease,
        box-shadow 200ms ease;
}

.hp-card__cover-wrap {
    position: relative;
}

.hp-card__image-reveal {
    position: absolute;
    inset-inline: 0.0625rem;
    bottom: 0.0625rem;
    z-index: 2;
    display: flex;
    align-items: flex-end;
    padding: 1.5rem 0.5rem 0.5rem;
    border-radius: 0 0 10px 10px;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 15%, rgba(0, 0, 0, 0.55) 60%, transparent 100%);
    opacity: 0;
    transform: translateY(6px);
    pointer-events: none;
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}

.hp-card__image-reveal--open {
    opacity: 1;
    transform: translateY(0);
}

.hp-card__image-reveal-inner {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-height: 0;
}

.hp-card__body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--story-card-body-gap);
    padding: 6px;
    min-height: 0;
}

.hp-card__info {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
}

.hp-card__title {
    margin: 0;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.3;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hp-card__themes {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.375rem;
}

.hp-card__theme-dot {
    font-size: 0.625rem;
    line-height: 1;
    color: rgba(255, 255, 255, 0.28);
}

.hp-card__theme {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--color-primary, #6fafba);
}

.hp-card__teaser {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.52);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.hp-card__cta {
    display: flex;
    box-sizing: border-box;
    height: var(--story-card-btn-height);
    width: 100%;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: var(--story-card-btn-radius);
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1;
    margin-top: auto;
}

.hp-card__cta--active {
    height: 2.125rem;
    padding: 0 0.625rem;
    border: 0.5px solid #59c1ca;
    border-radius: 0.3125rem;
    background: #0e1f22;
    color: var(--story-card-btn-text);
    font-size: 0.9rem;
    font-weight: 400;
    transition:
        border-color 180ms ease,
        background-color 180ms ease,
        transform 180ms ease;
}

.hp-card__cta--active:hover {
    border-color: #8fcbd3;
    background: #163f46;
    transform: translateY(-1px);
}

.hp-card__cta--active:active {
    background: var(--story-card-btn-bg-active);
    transform: translateY(0);
}

.hp-card__cta--disabled {
    height: 2.125rem;
    padding: 0 0.625rem;
    border: 0.5px solid #8f8f8f;
    border-radius: 0.3125rem;
    background: transparent;
    color: #8f8f8f;
    font-size: 0.9rem;
    font-weight: 400;
}

@media (min-width: 768px) {
    .hp-card__frame {
        width: var(--story-portrait-card-width, 13.5rem);
    }
}

@media (min-width: 1024px) {
    .hp-card--focused .hp-card__frame {
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: var(--story-card-shadow-hover), var(--story-card-shadow-glow);
    }

    .hp-card--static:hover .hp-card__frame {
        border-color: rgba(255, 255, 255, 0.08);
        box-shadow: var(--story-card-shadow-hover), var(--story-card-shadow-glow);
    }
}
</style>
