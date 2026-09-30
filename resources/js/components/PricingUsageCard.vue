<script setup lang="ts">
import type { SubscriptionUsageSummary } from '@/types';
import usageLightning from '@/assets/pricing/usage-lightning.svg';
import usageSpeaker from '@/assets/pricing/usage-speaker.svg';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    usage: SubscriptionUsageSummary;
    icon: string;
    billingPortalUrl?: string | null;
}>();

const isOneTime = computed(() => Boolean(props.usage.is_one_time));
const actionsRemaining = computed(() => props.usage.actions.remaining);
const actionsLimit = computed(() => props.usage.actions.limit);
const voiceMinutesLimit = computed(() => props.usage.voice.limit_minutes);
const voiceMinutesRemaining = computed(() => Math.max(0, Math.round(props.usage.voice.remaining_milliseconds / 60_000)));
const actionsPercent = computed(() => allowancePercent(actionsRemaining.value, actionsLimit.value));
const voicePercent = computed(() => allowancePercent(voiceMinutesRemaining.value, voiceMinutesLimit.value));
const renewalLabel = computed(() => {
    if (isOneTime.value) {
        return 'One-time allowance';
    }

    const periodEndsAt = props.usage.period_ends_at;

    if (!periodEndsAt) {
        return null;
    }

    const date = new Date(periodEndsAt);

    const formatted = Number.isNaN(date.getTime())
        ? null
        : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });

    if (formatted === null) {
        return props.usage.renews === false ? 'Ends at period end' : 'Renews at period end';
    }

    return props.usage.renews === false ? `Ends ${formatted}` : `Renews ${formatted}`;
});

function allowancePercent(remaining: number, limit: number): number {
    if (limit <= 0) {
        return 0;
    }

    return Math.min(100, Math.max(0, Math.round((remaining / limit) * 100)));
}

function formatNumber(value: number): string {
    return Math.max(0, value).toLocaleString();
}
</script>

<template>
    <section class="w-full max-w-[23.8125rem] rounded-2xl border-[0.5px] border-[#373737] bg-black p-3.5" aria-label="Your usage">
        <div class="flex flex-col gap-3.5">
            <div class="flex h-[34px] items-center justify-between gap-3">
                <h1 class="font-inter text-lg leading-[26px] font-normal text-white">Your Usage</h1>
                <div class="flex h-[34px] items-center gap-1.5 rounded-[21px] bg-[#0f0f0f] px-1 py-1.5">
                    <img
                        :src="icon"
                        alt=""
                        class="size-[26px] rounded-full border-[0.2px] border-[#ffbe58] object-cover"
                        width="26"
                        height="26"
                    />
                    <p class="font-marcellus-sc text-[13px] leading-5 text-[#ffbe58] capitalize">{{ usage.plan.name }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 min-[420px]:grid-cols-2">
                <div class="rounded-lg border-[0.5px] border-[#191919] bg-black p-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex items-center gap-1.5">
                            <img :src="usageLightning" alt="" width="24" height="24" />
                            <span class="font-inter text-sm font-medium text-[#59c1ca]">Actions</span>
                        </span>
                        <p class="font-inter text-[11px] text-[#818181]">
                            <span class="text-white">{{ formatNumber(actionsRemaining) }}</span>
                            {{ ` / ${formatNumber(actionsLimit)}` }}
                        </p>
                    </div>
                    <div
                        class="mt-3.5 h-1.5 overflow-hidden rounded-full bg-[#808080]"
                        role="progressbar"
                        aria-label="Actions remaining"
                        :aria-valuenow="actionsPercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-valuetext="`${actionsRemaining} of ${actionsLimit} actions remaining`"
                    >
                        <span class="block h-full rounded-full bg-[#59c1ca]" :style="{ width: `${actionsPercent}%` }" />
                    </div>
                    <p class="mt-1 font-inter text-[11px] leading-[15px] text-[#818181]">
                        <span class="text-white">{{ actionsPercent }}%</span>
                        remaining
                    </p>
                </div>

                <div class="rounded-lg border-[0.5px] border-[#191919] bg-black p-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex items-center gap-1.5">
                            <img :src="usageSpeaker" alt="" width="24" height="24" />
                            <span class="font-inter text-sm text-[#ffbe58]">Voice</span>
                        </span>
                        <p class="font-inter text-[11px] text-[#818181]">
                            <span class="text-white">{{ `${voiceMinutesRemaining} m ` }}</span>
                            {{ `/ ${voiceMinutesLimit} m` }}
                        </p>
                    </div>
                    <div
                        class="mt-3.5 h-1.5 overflow-hidden rounded-full bg-[#808080]"
                        role="progressbar"
                        aria-label="Voice minutes remaining"
                        :aria-valuenow="voicePercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-valuetext="`${voiceMinutesRemaining} of ${voiceMinutesLimit} voice minutes remaining`"
                    >
                        <span class="block h-full rounded-full bg-[#ffbe58]" :style="{ width: `${voicePercent}%` }" />
                    </div>
                    <p class="mt-1 font-inter text-[11px] leading-[15px] text-[#818181]">
                        <span class="text-white">{{ voicePercent }}%</span>
                        remaining
                    </p>
                </div>
            </div>

            <div class="h-px w-full bg-[#373737]" />

            <div class="flex flex-wrap items-center justify-between gap-2.5">
                <p v-if="renewalLabel" class="font-inter text-[11px] leading-[15px] text-[#818181]">{{ renewalLabel }}</p>
                <div class="ml-auto flex items-center gap-2.5">
                    <Link
                        v-if="billingPortalUrl"
                        :href="billingPortalUrl"
                        method="post"
                        as="button"
                        type="button"
                        class="inline-flex h-[33px] items-center justify-center rounded-[21px] border-[0.5px] border-[#8f8f8f] px-2 text-[13px] text-[#8f8f8f]"
                    >
                        Cancel subscription
                    </Link>
                    <a
                        href="#pricing-plans"
                        class="inline-flex h-[33px] items-center justify-center rounded-[21px] bg-[#0e1f22] px-2 text-[13px] text-white"
                    >
                        Change Plan
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>
