<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        title: string;
        active?: boolean;
        /** Label color when the capsule expands — matches icon accent. */
        labelTone?: 'primary' | 'secondary';
    }>(),
    { active: false, labelTone: 'primary' },
);

defineEmits<{
    click: [];
}>();
</script>

<template>
    <button
        type="button"
        class="gameplay-capsule gameplay-header-glass"
        :class="[
            { 'gameplay-capsule--active gameplay-header-glass--active': active },
            labelTone === 'secondary' && 'gameplay-capsule--label-secondary',
        ]"
        :title="title"
        :aria-label="title"
        @click="$emit('click')"
    >
        <span class="gameplay-capsule__icon" aria-hidden="true">
            <slot />
        </span>
        <span class="gameplay-capsule__label-wrap">
            <span class="gameplay-capsule__label">{{ label }}</span>
        </span>
    </button>
</template>

<style scoped>
.gameplay-capsule {
    --capsule-ease: cubic-bezier(0.22, 1, 0.36, 1);
    --capsule-duration: 0.45s;
    --capsule-accent: var(--color-primary-300);
    --capsule-active-icon-color: var(--color-secondary-300);
    --capsule-icon-filter: brightness(0) saturate(100%) invert(83%) sepia(45%) saturate(515%) hue-rotate(334deg) brightness(102%) contrast(101%);

    display: grid;
    grid-template-columns: 45px 0fr;
    align-items: center;
    height: 45px;
    padding: 0;
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    flex-shrink: 0;
    overflow: hidden;
    transition:
        grid-template-columns var(--capsule-duration) var(--capsule-ease),
        padding var(--capsule-duration) var(--capsule-ease),
        background 0.2s ease,
        box-shadow 0.2s ease;
}

.gameplay-capsule__icon {
    display: grid;
    place-items: center;
    width: 45px;
    height: 45px;
}

.gameplay-capsule__label-wrap {
    min-width: 0;
    overflow: hidden;
}

.gameplay-capsule__label {
    display: block;
    padding-left: 4px;
    padding-right: 0;
    white-space: nowrap;
    font-size: 1.125rem;
    font-weight: 500;
    line-height: 1;
    letter-spacing: 0.02em;
    color: var(--capsule-accent);
    opacity: 0;
    transform: translateX(-10px);
    transition:
        opacity 0.32s ease,
        transform 0.38s var(--capsule-ease),
        padding 0.45s var(--capsule-ease);
}

.gameplay-capsule--label-secondary {
    --capsule-accent: var(--color-secondary-300);
}

.gameplay-capsule:hover .gameplay-capsule__icon :deep(svg),
.gameplay-capsule:focus-visible .gameplay-capsule__icon :deep(svg),
.gameplay-capsule--active .gameplay-capsule__icon :deep(svg) {
    color: var(--capsule-active-icon-color);
}

.gameplay-capsule--active .gameplay-capsule__icon :deep(img) {
    filter: var(--capsule-icon-filter);
}

@media (min-width: 768px) and (hover: hover) {
    .gameplay-capsule:hover {
        grid-template-columns: 45px 1fr;
        padding-right: 14px;
    }

    .gameplay-capsule:hover .gameplay-capsule__label {
        opacity: 1;
        transform: translateX(0);
        padding-right: 2px;
        transition:
            opacity 0.4s var(--capsule-ease) 0.08s,
            transform 0.5s var(--capsule-ease) 0.05s,
            padding 0.45s var(--capsule-ease);
    }
}

.gameplay-capsule:active {
    transform: scale(0.97);
    transition:
        grid-template-columns var(--capsule-duration) var(--capsule-ease),
        padding var(--capsule-duration) var(--capsule-ease),
        background-color 0.2s ease,
        transform 0.12s ease;
}

@media (min-width: 768px) and (hover: hover) {
    .gameplay-capsule:active {
        transform: none;
    }
}
</style>
