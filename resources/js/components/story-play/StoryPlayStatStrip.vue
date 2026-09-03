<script setup lang="ts">
export interface StoryPlayStatValuePart {
    text: string;
    muted?: boolean;
}

export interface StoryPlayStatItem {
    label: string;
    value?: string;
    valueParts?: StoryPlayStatValuePart[];
}

defineProps<{
    items: StoryPlayStatItem[];
}>();
</script>

<template>
    <div class="w-full shrink-0">
        <!-- Desktop: single stat strip -->
        <div class="relative hidden w-full overflow-hidden rounded-xl bg-white/[0.04] lg:block">
            <div class="flex h-[4.375rem] items-center justify-center gap-4 px-1 pt-[5px]">
                <template v-for="(item, idx) in items" :key="item.label">
                    <div
                        class="flex h-[4.375rem] min-w-0 flex-1 flex-col items-center justify-center rounded-lg p-2.5 text-center font-['Inter',sans-serif] leading-7 text-white not-italic"
                    >
                        <p class="w-full shrink-0 text-[0.9375rem] font-semibold">{{ item.label }}</p>
                        <p v-if="item.valueParts?.length" class="w-full shrink-0 font-medium leading-7">
                            <span
                                v-for="(part, partIdx) in item.valueParts"
                                :key="`${item.label}-${partIdx}`"
                                :class="
                                    part.muted
                                        ? 'text-[0.625rem] leading-7 text-[#8f8f8f]'
                                        : 'text-[0.875rem] leading-7'
                                "
                            >{{ part.text }}</span>
                        </p>
                        <p v-else class="w-full shrink-0 break-words text-[0.875rem] font-medium leading-7 line-clamp-2" :title="item.value">
                            {{ item.value }}
                        </p>
                    </div>
                    <div
                        v-if="idx < items.length - 1"
                        aria-hidden="true"
                        class="h-[4.375rem] w-px shrink-0 self-center bg-white/20"
                    />
                </template>
            </div>
        </div>

        <!-- Mobile: 2×2 glass boxes -->
        <div class="grid grid-cols-2 gap-2.5 lg:hidden">
            <div
                v-for="item in items"
                :key="item.label"
                class="relative h-[68px] overflow-hidden rounded-xl shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]"
            >
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-xl bg-white/[0.04] backdrop-blur-[3px]" />
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 rounded-[inherit]"
                    style="
                        box-shadow:
                            inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
                            inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
                    "
                />
                <div class="relative z-[1] flex h-full flex-col items-center justify-center gap-[3px] px-[7px] py-2.5 text-center font-['Inter',sans-serif] text-white not-italic">
                    <p class="w-full text-[13px] leading-[26px] font-medium uppercase">{{ item.label }}</p>
                    <p v-if="item.valueParts?.length" class="w-full font-normal leading-[1.5]">
                        <span
                            v-for="(part, partIdx) in item.valueParts"
                            :key="`${item.label}-mobile-${partIdx}`"
                            :class="part.muted ? 'text-[10px] text-[#8f8f8f]' : 'text-[12px]'"
                        >{{ part.text }}</span>
                    </p>
                    <p v-else class="w-full text-[12px] leading-[1.5] font-normal break-words line-clamp-1" :title="item.value">
                        {{ item.value }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
