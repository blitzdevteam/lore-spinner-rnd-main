<script lang="ts">
/** Required so consumer `class` merges onto `<Link>` / native roots (Vue + dynamic `<component>` fallthrough is unreliable). */
export default {
    inheritAttrs: false,
};
</script>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, useAttrs } from 'vue';

const attrs = useAttrs();

type Severity =
    | 'primary'
    | 'primary-muted-outline'
    | 'primary-outline'
    | 'secondary'
    | 'secondary-muted-outline'
    | 'muted'
    | 'gray-muted'
    | 'glass'
    | 'header-glass'
    | 'muted-glass'
    | 'primary-glass'
    | 'transparent';
type ButtonType = 'internal-link' | 'external-link' | 'button' | 'submit' | 'span';

const props = withDefaults(
    defineProps<{
        severity?: Severity;
        iconOnly?: boolean;
        type?: ButtonType;
        href?: string;
        processing?: boolean;
        disabled?: boolean;
    }>(),
    {
        severity: 'primary',
        type: 'button',
        iconOnly: false,
        href: '',
        processing: false,
        disabled: false,
    },
);

const emits = defineEmits<{
    click: [ev: MouseEvent];
    submit: [ev: SubmitEvent];
}>();

const componentTagMap: Record<ButtonType, string | typeof Link> = {
    'internal-link': Link,
    'external-link': 'a',
    span: 'span',
    submit: 'button',
    button: 'button',
};

const severityClasses: Record<Severity, string> = {
    primary: 'bg-cta-fill text-cta-text !border-0 outline-primary-500/20',
    'primary-muted-outline': 'bg-primary-800/20 text-cta-text border-cta-border/75 text-primary-300 outline-primary-500/20',
    'primary-outline': 'bg-[#0e1f22] text-white border-primary outline-primary/20',
    secondary: 'bg-secondary-400 text-black outline-secondary-400/30 border-transparent',
    'secondary-muted-outline': 'bg-secondary-300/20 text-black border-secondary-300/75 text-secondary-300 outline-secondary-200/20',
    muted: 'bg-gray-900 text-gray-300 font-normal outline-gray-500/15 border-transparent',
    'gray-muted': 'bg-gray-700 text-gray-300 font-normal outline-gray-500/15 border-transparent',
    'muted-glass': 'bg-muted-glass-effect overflow-hidden',
    glass: 'overflow-hidden border-transparent shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]',
    'header-glass': 'gameplay-header-glass overflow-hidden',
    'primary-glass': 'bg-glass-effect overflow-hidden',
    transparent: 'bg-transparent text-primary outline-transparent border-transparent',
};

const hoverClasses: Partial<Record<Severity, string>> = {
    primary: 'hover:bg-cta-hover active:bg-cta-active focus:outline-4 hover:outline-4',
    glass: 'hover:scale-110 active:scale-95',
    'header-glass': 'hover:scale-105 active:scale-95',
    'muted-glass': 'hover:scale-110 hover:bg-white/20',
    'primary-glass': 'hover:scale-110 hover:bg-white/20',
    transparent: 'hover:bg-primary-50/10 hover:outline-primary-200/30',
};

const isDisabled = computed(() => props.disabled || props.processing);
const isLink = computed(() => props.type === 'internal-link' || props.type === 'external-link');
const isButtonType = computed(() => props.type === 'submit' || props.type === 'button');
const isPrimaryGlass = computed(() => props.severity === 'primary-glass');
const isGlass = computed(() => props.severity === 'glass');
const isHeaderGlass = computed(() => props.severity === 'header-glass');

const componentTag = computed(() => componentTagMap[props.type]);

const componentClass = computed(() => {
    const base = 'relative justify-center flex items-center transition-all border';
    const rounded = props.iconOnly ? 'rounded-full grid place-items-center' : 'rounded-xl px-4';
    const size = props.iconOnly ? '!size-9' : 'h-12';
    const severity = severityClasses[props.severity];

    if (isDisabled.value) {
        return `${base} ${size} ${severity} ${rounded} cursor-not-allowed opacity-60 outline-none pointer-events-none`;
    }

    const hover = hoverClasses[props.severity] ?? 'hover:outline-4 focus:outline-4';
    return `${base} ${size} ${severity} ${rounded} cursor-pointer outline-0 ${hover}`;
});

const glassSpanClass = computed(() => {
    const base = 'absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2 bg-primary-glass-effect';
    return props.iconOnly ? `${base} rounded-full w-8/10 h-8/10` : `${base} rounded-[calc(0.75rem-2px)] w-[calc(100%-1px)] h-[calc(100%-1px)]`;
});

/** Merge internal styles with caller `class` (fixes SSR/hydration + Inertia `<Link>` styling). */
const mergedClass = computed(() => [componentClass.value, attrs.class]);

const mergedRootBindings = computed(() => {
    const raw = attrs as Record<string, unknown>;
    const rest: Record<string, unknown> = {};
    for (const key of Object.keys(raw)) {
        if (key === 'class') {
            continue;
        }
        rest[key] = raw[key];
    }

    return {
        ...rest,
        href: isLink.value ? props.href : undefined,
        disabled: isButtonType.value && isDisabled.value,
        class: mergedClass.value,
    };
});

const handleClick = (event: MouseEvent) => {
    if (!isDisabled.value) emits('click', event);
};

const handleSubmit = (event: SubmitEvent) => {
    if (!isDisabled.value) emits('submit', event);
};
</script>

<template>
    <component :is="componentTag" v-bind="mergedRootBindings" @click="handleClick" @submit="handleSubmit">
        <LoaderCircle v-if="processing" class="animate-spin opacity-50" />
        <template v-else-if="isHeaderGlass">
            <div class="relative z-10">
                <slot />
            </div>
        </template>
        <template v-else-if="isGlass">
            <!-- Figma-accurate layered glass background -->
            <span aria-hidden class="pointer-events-none absolute inset-0 rounded-[inherit]">
                <span class="absolute inset-0 rounded-[inherit] bg-[rgba(255,255,255,0.04)]" />
                <span class="absolute inset-0 rounded-[inherit] bg-[#33333330] mix-blend-plus-lighter backdrop-blur-[3px]" />
            </span>
            <!-- Inset highlight border -->
            <span
                aria-hidden
                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
            />
            <!-- Content -->
            <div class="relative z-10">
                <slot />
            </div>
        </template>
        <template v-else-if="isPrimaryGlass">
            <span :class="glassSpanClass" />
            <div class="z-5">
                <slot />
            </div>
        </template>
        <slot v-else />
    </component>
</template>
