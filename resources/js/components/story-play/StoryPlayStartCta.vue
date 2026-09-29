<script setup lang="ts">
withDefaults(
    defineProps<{
        label?: string;
        variant?: 'primary' | 'secondary';
        /** When true, renders the gray Coming Soon card-style CTA (non-interactive). */
        comingSoon?: boolean;
    }>(),
    {
        label: 'Start Story',
        variant: 'primary',
        comingSoon: false,
    },
);

defineEmits<{
    click: [];
}>();
</script>

<template>
    <button
        v-if="!comingSoon"
        type="button"
        class="flex h-14 w-full items-center justify-center rounded-xl border-[0.5px] px-6 font-['Inter',sans-serif] text-[18px] font-medium text-white not-italic transition-colors"
        :class="
            variant === 'secondary'
                ? 'border-[#8f8f8f] bg-transparent hover:border-white/70 hover:bg-white/[0.04] active:bg-white/[0.08]'
                : 'border-[#59c1ca] bg-[#0e1f22] hover:border-[#8fcbd3] hover:bg-[#163f46] active:bg-[#0a1618]'
        "
        @click="$emit('click')"
    >
        {{ label }}
    </button>
    <div
        v-else
        class="flex h-14 w-full cursor-not-allowed items-center justify-center rounded-xl border-[0.5px] border-[#8f8f8f] bg-transparent px-6 font-['Inter',sans-serif] text-xl font-medium text-[#8f8f8f] not-italic select-none"
        aria-disabled="true"
    >
        Coming Soon
    </div>
</template>
