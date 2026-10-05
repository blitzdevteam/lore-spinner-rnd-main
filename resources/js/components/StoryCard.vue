<script setup lang="ts">
import StoryCardCover from '@/components/StoryCardCover.vue';
import { StoryStatusEnum } from '@/types/enum';
import { show } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export type StoryCardCta = 'play' | 'continue' | 'read-again' | 'coming-soon';

const props = withDefaults(
    defineProps<{
        title: string;
        coverImage?: string | null;
        slug: string;
        status?: StoryStatusEnum | string;
        mood?: string;
        teaser?: string | null;
        themes?: string[];
        isComingSoon?: boolean;
        cta?: StoryCardCta;
        dimmed?: boolean;
        /** Desktop: hover reveal + direct links. Touch/mobile: tap cover/title to preview. */
        isDesktopHover?: boolean;
    }>(),
    {
        coverImage: null,
        status: undefined,
        mood: undefined,
        teaser: null,
        themes: () => [],
        isComingSoon: false,
        cta: undefined,
        dimmed: false,
        isDesktopHover: true,
    },
);

const emit = defineEmits<{ preview: [] }>();

const storyUrl = computed(() => show(props.slug).url);

const isPublished = computed(() => {
    if (props.isComingSoon) return false;
    return props.status === StoryStatusEnum.PUBLISHED || props.status === 'published';
});

const resolvedCta = computed((): StoryCardCta => {
    if (props.cta) return props.cta;
    if (props.isComingSoon || !isPublished.value) return 'coming-soon';
    return 'play';
});

const ctaLabel = computed(() => {
    switch (resolvedCta.value) {
        case 'continue':
            return 'Continue';
        case 'read-again':
            return 'Read Again';
        case 'coming-soon':
            return 'Coming Soon';
        default:
            return 'Play';
    }
});

const isInteractive = computed(() => resolvedCta.value !== 'coming-soon');

const hasReveal = computed(() => props.themes.length > 0 || Boolean(props.teaser));

const coverComponent = computed(() => {
    if (isInteractive.value) return Link;
    if (!props.isDesktopHover) return 'button';
    return 'div';
});

const infoComponent = computed(() => {
    if (props.isDesktopHover) return 'div';
    if (isInteractive.value) return Link;
    return 'button';
});

function onCoverActivate(): void {
    if (!props.isDesktopHover && !isInteractive.value) {
        emit('preview');
    }
}

function onInfoActivate(): void {
    if (!props.isDesktopHover && !isInteractive.value) {
        emit('preview');
    }
}
</script>

<template>
    <article
        class="story-card group min-w-0 transition-opacity duration-200"
        :class="dimmed ? 'opacity-[0.35]' : 'opacity-100'"
        :data-mood="mood || undefined"
    >
        <div class="story-card__frame">
            <component
                :is="coverComponent"
                :href="isInteractive ? storyUrl : undefined"
                type="button"
                class="story-card__cover-link block min-w-0 border-0 bg-transparent p-0 text-left outline-none"
                :class="!isDesktopHover && 'cursor-pointer'"
                :aria-label="!isDesktopHover ? (isInteractive ? `Open ${title}` : `Preview ${title}`) : undefined"
                @click="onCoverActivate"
            >
                <div class="story-card__cover-wrap p-2.5">
                    <StoryCardCover :src="coverImage" :title="title" :zoom-on-hover="false" />

                    <div v-if="hasReveal && isDesktopHover" class="story-card__cover-hover" aria-hidden="true">
                        <div class="story-card__cover-hover-inner">
                            <div v-if="themes.length" class="story-card__themes">
                                <template v-for="(theme, i) in themes" :key="theme">
                                    <span v-if="i > 0" class="story-card__theme-dot" aria-hidden="true">•</span>
                                    <span class="story-card__theme">{{ theme }}</span>
                                </template>
                            </div>
                            <p v-if="teaser" class="story-card__teaser">{{ teaser }}</p>
                        </div>
                    </div>
                </div>
            </component>

            <div class="story-card__body">
                <component
                    :is="infoComponent"
                    :href="!isDesktopHover && isInteractive ? storyUrl : undefined"
                    type="button"
                    class="story-card__info min-w-0 border-0 bg-transparent p-0 text-left outline-none"
                    :class="!isDesktopHover && 'cursor-pointer'"
                    :aria-label="!isDesktopHover ? (isInteractive ? `Open ${title}` : `Preview ${title}`) : undefined"
                    @click="onInfoActivate"
                >
                    <h3 class="story-card__title">{{ title }}</h3>
                </component>

                <div class="story-card__cta">
                    <Link v-if="isInteractive" :href="storyUrl" class="story-card__btn story-card__btn--active">
                        {{ ctaLabel }}
                    </Link>
                    <div v-else class="story-card__btn story-card__btn--disabled">
                        {{ ctaLabel }}
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>

<style scoped>
.story-card__frame {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: var(--story-card-radius);
    border: var(--story-card-border);
    background: var(--story-card-bg);
    box-shadow: var(--story-card-shadow);
    transition:
        box-shadow var(--story-card-hover-duration) var(--story-card-ease),
        border-color var(--story-card-hover-duration) var(--story-card-ease);
}

@media (min-width: 1024px) {
    .story-card.group:hover {
        position: relative;
        z-index: 2;
    }

    .group:hover .story-card__frame {
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: var(--story-card-shadow-hover);
    }
}

.story-card__cover-link {
    text-decoration: none;
}

.story-card__cover-wrap {
    position: relative;
}

.story-card__cover-hover {
    pointer-events: none;
    position: absolute;
    inset-inline: 0.0625rem;
    bottom: 0.0625rem;
    z-index: 1;
    display: flex;
    align-items: flex-end;
    padding: 1.5rem 0.75rem 0.75rem;
    border-radius: 0 0 10px 10px;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 15%, rgba(0, 0, 0, 0.55) 60%, transparent 100%);
    opacity: 0;
    transform: translateY(6px);
    transition:
        opacity var(--story-card-hover-duration) var(--story-card-ease),
        transform var(--story-card-hover-duration) var(--story-card-ease);
}

.story-card__cover-hover-inner {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
}

@media (min-width: 1024px) {
    .group:hover .story-card__cover-hover {
        opacity: 1;
        transform: translateY(0);
    }
}

.story-card__body {
    display: flex;
    flex-direction: column;
    gap: var(--story-card-body-gap);
    padding: var(--story-card-body-pad-y) var(--story-card-body-pad-x);
}

.story-card__info {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
}

.story-card__title {
    margin: 0;
    font-size: var(--story-card-title-size);
    font-weight: 700;
    line-height: 1.3;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.story-card__themes {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.375rem;
}

.story-card__theme-dot {
    font-size: 0.625rem;
    line-height: 1;
    color: rgba(255, 255, 255, 0.28);
}

.story-card__theme {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--color-primary, #6fafba);
}

.story-card__cover-hover .story-card__theme {
    color: #7ec8d4;
}

.story-card__teaser {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.52);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
    text-overflow: ellipsis;
}

.story-card__cover-hover .story-card__teaser {
    color: rgba(255, 255, 255, 0.88);
    -webkit-line-clamp: 4;
}

.story-card__cta {
    flex-shrink: 0;
}

.story-card__btn {
    display: flex;
    box-sizing: border-box;
    height: 2.125rem;
    width: 100%;
    align-items: center;
    justify-content: center;
    border-radius: var(--story-card-btn-radius);
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1;
    text-decoration: none;
    transition:
        border-color 180ms ease,
        background-color 180ms ease,
        color 180ms ease,
        transform 180ms ease;
}

.story-card__btn--active {
    border: 0.5px solid #59c1ca;
    background: #0e1f22;
    color: var(--story-card-btn-text);
}

.story-card__btn--active:hover {
    border-color: #8fcbd3;
    background: #163f46;
    transform: translateY(-1px);
}

.story-card__btn--active:active {
    background: var(--story-card-btn-bg-active);
    transform: translateY(0);
}

.story-card__btn--disabled {
    cursor: default;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.42);
}
</style>
