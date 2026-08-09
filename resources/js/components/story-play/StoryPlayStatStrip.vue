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
    <div class="relative w-full shrink-0 overflow-hidden rounded-xl bg-white/[0.04]">
        <div class="hidden lg:flex h-[4.375rem] items-center justify-center gap-4 px-1 pt-[5px]">
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

        <div class="lg:hidden flex flex-col">
            <div class="flex h-[4.375rem] items-center justify-center gap-4 px-1 pt-[5px]">
                <template v-for="(item, idx) in items.slice(0, 2)" :key="item.label">
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
                        v-if="idx < 1"
                        aria-hidden="true"
                        class="h-[4.375rem] w-px shrink-0 self-center bg-white/20"
                    />
                </template>
            </div>

            <div
                aria-hidden="true"
                class="h-px w-full bg-white/20"
            />

            <div class="flex h-[4.375rem] items-center justify-center gap-4 px-1 pt-[5px]">
                <template v-for="(item, idx) in items.slice(2, 4)" :key="item.label">
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
                        v-if="idx < 1"
                        aria-hidden="true"
                        class="h-[4.375rem] w-px shrink-0 self-center bg-white/20"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
