<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        action?: string;
        count?: number;
        href?: string;
    }>(),
    {
        action: 'View All',
    },
);

const showAction = computed(() => !!props.href);

const actionLabel = computed(() => {
    if (props.count != null) {
        return `${props.action} (${props.count})`;
    }
    return props.action;
});
</script>

<template>
    <header class="section-header @container">
        <div v-if="showAction || $slots.action" class="section-header__title-row">
            <h2 class="home-section-title section-header__title">{{ title }}</h2>
            <slot name="action">
                <Link v-if="showAction" :href="href!" class="section-header__action group">
                    <span class="section-header__action-text">{{ actionLabel }}</span>
                    <ChevronRight
                        class="section-header__action-icon"
                        :stroke-width="2.25"
                        aria-hidden="true"
                    />
                </Link>
            </slot>
        </div>
        <h2 v-else class="home-section-title section-header__title--solo">{{ title }}</h2>
        <p v-if="subtitle" class="home-section-description section-header__subtitle">{{ subtitle }}</p>
    </header>
</template>

<style scoped>
.section-header {
    --section-header-title-size: 1.175rem;
    --section-header-title-leading: 1.1;
    --section-header-row-height: calc(var(--section-header-title-size) * var(--section-header-title-leading));
    --section-header-action-size: 0.875rem;

    container-type: inline-size;
    container-name: section-header;
    display: flex;
    width: 100%;
    min-width: 0;
    flex-direction: column;
    gap: 0.625rem;
}

@media (min-width: 768px) {
    .section-header {
        --section-header-title-size: 1.325rem;
    }
}

.section-header__title-row {
    display: flex;
    width: 100%;
    min-width: 0;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    column-gap: 0.75rem;
    row-gap: 0.375rem;
    min-height: var(--section-header-row-height);
}

.section-header__title,
.section-header__title--solo {
    min-width: 0;
    margin: 0;
    padding: 0;
    font-size: var(--section-header-title-size);
    line-height: var(--section-header-title-leading);
}

.section-header__title {
    flex: 1 1 calc(100% - 6.5rem);
    max-width: calc(100% - 6.5rem);
}

.section-header__subtitle {
    margin: 0;
}

.section-header__action {
    position: relative;
    display: inline-flex;
    height: var(--section-header-row-height);
    flex-shrink: 0;
    align-items: center;
    gap: 0.1875rem;
    font-size: var(--section-header-action-size);
    font-weight: 500;
    line-height: 1;
    letter-spacing: 0.01em;
    color: var(--color-primary, #00d4aa);
    white-space: nowrap;
    text-decoration: none;
    transition: opacity 150ms ease, transform 150ms ease;
}

.section-header__action::before {
    content: '';
    position: absolute;
    inset: -0.625rem -0.375rem;
}

.section-header__action:hover {
    opacity: 0.8;
}

.section-header__action:active {
    opacity: 0.7;
    transform: scale(0.98);
}

.section-header__action-text {
    line-height: 1;
}

.section-header__action-icon {
    width: 1em;
    height: 1em;
    flex-shrink: 0;
    transition: transform 150ms ease;
}

.section-header__action:hover .section-header__action-icon {
    transform: translateX(2px);
}

@container section-header (max-width: 20rem) {
    .section-header__title-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.375rem;
        min-height: 0;
    }

    .section-header__title {
        flex: 1 1 auto;
        max-width: 100%;
    }

    .section-header__action {
        height: auto;
        min-height: 2.75rem;
        align-self: flex-start;
    }
}
</style>
