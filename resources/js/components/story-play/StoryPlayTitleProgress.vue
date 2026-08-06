<script setup lang="ts">
defineProps<{
    title: string;
    /** Progress percent 0–100. Defaults to 0. */
    progressPercent?: number;
}>();
</script>

<template>
    <div class="flex w-full min-w-0 items-center justify-between gap-3">
        <h1
            class="min-w-0 font-['Inter',sans-serif] text-[1.25rem] font-semibold not-italic leading-[1.625rem] text-white md:[word-break:break-word]"
        >
            {{ title }}
        </h1>
        <div
            class="relative flex h-7 w-12 shrink-0 items-center justify-center overflow-hidden rounded-[1.6875rem]"
            style="box-shadow: 0px 4px 80px rgba(0, 0, 0, 0.2)"
            :aria-label="`${Math.round(Math.min(100, Math.max(0, progressPercent ?? 0)))}% complete`"
        >
            <span aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-[inherit] bg-white/[0.04]" />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[inherit] bg-[rgba(30,30,30,0.25)] backdrop-blur-[3px] mix-blend-plus-lighter"
            />
            <span
                class="relative z-[1] font-['Inter',sans-serif] text-[0.8125rem] font-semibold not-italic text-[#59c1ca]"
            >
                {{ String(Math.round(Math.min(100, Math.max(0, progressPercent ?? 0)))).padStart(2, '0') }}%
            </span>
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[inherit]"
                style="
                    box-shadow:
                        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
                        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
                "
            />
        </div>
    </div>
</template>
