<script setup lang="ts">
import { computed } from 'vue';

const model = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        label: string;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const isOn = computed(() => model.value === 'On');

function toggle(): void {
    if (props.disabled) {
        return;
    }

    model.value = isOn.value ? 'Off' : 'On';
}
</script>

<template>
    <button
        type="button"
        role="switch"
        class="inline-flex items-center gap-1.5 rounded-full focus-visible:ring-2 focus-visible:ring-[#59c1ca] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
        :aria-checked="isOn"
        :aria-label="label"
        :disabled="disabled"
        @click="toggle"
    >
        <span class="text-xs leading-none" :class="isOn ? 'text-white' : 'text-[#7e7e7e]'">
            {{ isOn ? 'On' : 'Off' }}
        </span>
        <span
            class="relative h-[30px] w-[55px] shrink-0 rounded-full border border-[#303030] transition-colors"
            :class="isOn ? 'bg-[#59c1ca]' : 'bg-[#232323]'"
            aria-hidden="true"
        >
            <span
                class="absolute top-[5px] size-5 rounded-full transition-[left,background-color]"
                :class="isOn ? 'left-[30px] bg-[#1c1c1c]' : 'left-[5px] bg-[#3f3f3f]'"
            />
        </span>
    </button>
</template>
