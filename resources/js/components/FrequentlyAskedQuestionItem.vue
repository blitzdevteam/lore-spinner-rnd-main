<script setup lang="ts">
import { LucideCirclePlus, LucideMinus } from 'lucide-vue-next';

defineProps<{
    question: string;
    answer: string;
    expanded: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
}>();
</script>

<template>
    <div
        class="w-full cursor-pointer rounded-[0.875rem] border border-solid transition-[box-shadow,border-color] duration-200"
        :class="
            expanded
                ? 'flex flex-col items-center bg-[#292929] px-[0.875rem] py-6 shadow-[0_0_18.3px_rgba(111,175,186,0.1)]'
                : 'flex min-h-[4.375rem] flex-col items-center justify-center bg-[#292929] px-[0.875rem] py-[1.375rem]'
        "
        :style="expanded ? { borderColor: 'rgba(0, 198, 222, 0.6)' } : { borderColor: '#373737' }"
        role="button"
        tabindex="0"
        :aria-expanded="expanded"
        @click="emit('toggle')"
        @keydown.enter.prevent="emit('toggle')"
        @keydown.space.prevent="emit('toggle')"
    >
        <div class="flex w-full items-center gap-4 md:gap-[7.8125rem]">
            <p class="min-w-0 flex-1 text-left text-[1rem] leading-f[1.75rem] font-bold text-white">
                {{ question }}
            </p>
            <div class="flex shrink-0 items-center justify-center text-primary" :class="expanded ? 'h-7 w-[2.125rem]' : 'size-[1.6875rem]'">
                <LucideMinus v-if="expanded" class="size-7 stroke-[2]" aria-hidden="true" />
                <LucideCirclePlus v-else class="size-[1.6875rem] stroke-[1.75]" aria-hidden="true" />
            </div>
        </div>
        <div v-if="expanded" class="mt-5 flex w-full flex-col">
            <p class="w-full text-[14px] leading-[1.875rem] font-normal text-[#b6b6b6]">
                {{ answer }}
            </p>
        </div>
    </div>
</template>
