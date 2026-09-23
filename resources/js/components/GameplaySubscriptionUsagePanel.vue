<script setup lang="ts">
import type { SubscriptionUsageSummary } from '@/types';
import { pricing } from '@/wayfinder/routes';
import { Link } from '@inertiajs/vue3';
import { LucideCalendarClock, LucideSparkles, LucideVolume2, LucideZap } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    subscriptionUsage?: SubscriptionUsageSummary | null;
}>();

const subscriptionUsage = computed<SubscriptionUsageSummary | null>(() => props.subscriptionUsage ?? null);
const isComplimentary = computed(() => Boolean(subscriptionUsage.value?.is_complimentary));
const isOneTime = computed(() => Boolean(subscriptionUsage.value?.is_one_time));
const actionsRemaining = computed(() => subscriptionUsage.value?.actions.remaining ?? 0);
const actionsLimit = computed(() => subscriptionUsage.value?.actions.limit ?? 0);
const voiceMillisecondsRemaining = computed(() => Math.max(0, subscriptionUsage.value?.voice.remaining_milliseconds ?? 0));
const voiceMinutesLimit = computed(() => subscriptionUsage.value?.voice.limit_minutes ?? 0);
const voiceMillisecondsLimit = computed(() => voiceMinutesLimit.value * 60_000);
const actionsProgress = computed(() => (isComplimentary.value ? 100 : allowancePercent(actionsRemaining.value, actionsLimit.value)));
const voiceProgress = computed(() => (isComplimentary.value ? 100 : allowancePercent(voiceMillisecondsRemaining.value, voiceMillisecondsLimit.value)));
const renewalLabel = computed(() => {
    if (isOneTime.value) {
        return 'One-time free allowance';
    }

    if (isComplimentary.value) {
        return 'Complimentary access';
    }

    const periodEndsAt = subscriptionUsage.value?.period_ends_at;

    if (! periodEndsAt) {
        return null;
    }

    const date = new Date(periodEndsAt);

    return Number.isNaN(date.getTime())
        ? 'Renews at the end of your billing period'
        : `Renews ${date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`;
});

function allowancePercent(remaining: number, limit: number): number {
    if (limit <= 0) {
        return 0;
    }

    return Math.min(100, Math.max(0, (remaining / limit) * 100));
}

function formatNumber(value: number): string {
    return Math.max(0, value).toLocaleString();
}

function formatVoiceDuration(milliseconds: number): string {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1_000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return seconds === 0 ? `${minutes} min` : `${minutes} min ${seconds} sec`;
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex items-center gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl border border-primary/25 bg-primary/10 text-primary">
                <LucideSparkles class="size-4" :stroke-width="2" aria-hidden="true" />
            </span>
            <div>
                <h3 class="text-lg font-semibold text-gray-100">Subscription usage</h3>
                <p class="mt-0.5 text-xs text-gray-500">Your active plan and remaining allowance.</p>
            </div>
        </div>

        <template v-if="subscriptionUsage">
            <section class="rounded-xl border border-primary/25 bg-primary/5 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-medium tracking-wider text-primary-200 uppercase">Current plan</p>
                        <p class="mt-1 truncate text-base font-semibold text-gray-100">{{ subscriptionUsage.plan.name }}</p>
                    </div>
                    <span v-if="renewalLabel" class="flex shrink-0 items-center gap-1 text-right text-[0.6875rem] leading-relaxed text-gray-400">
                        <LucideCalendarClock class="size-3.5 text-primary/80" :stroke-width="1.75" aria-hidden="true" />
                        {{ renewalLabel }}
                    </span>
                </div>

                <div class="mt-4 grid gap-3">
                    <div class="rounded-lg border border-white/8 bg-black/10 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-sm text-gray-300">
                                <LucideZap class="size-4 text-primary" :stroke-width="2" aria-hidden="true" />
                                Actions
                            </span>
                            <strong class="text-sm font-semibold tabular-nums text-primary-100">
                                {{ isComplimentary ? 'Unlimited' : `${formatNumber(actionsRemaining)} left` }}
                            </strong>
                        </div>
                        <div
                            class="mt-3 h-1.5 overflow-hidden rounded-full bg-primary/15"
                            role="progressbar"
                            aria-label="Actions remaining"
                            :aria-valuenow="actionsProgress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuetext="`${actionsRemaining} of ${actionsLimit} actions remaining`"
                        >
                            <span
                                class="block h-full rounded-full bg-primary transition-[width] duration-300"
                                :style="{ width: `${actionsProgress}%` }"
                            />
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            {{ isComplimentary ? 'Complimentary investor access is not metered' : isOneTime ? 'One-time free allowance' : `of ${formatNumber(actionsLimit)} actions this period` }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-white/8 bg-black/10 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-sm text-gray-300">
                                <LucideVolume2 class="size-4 text-secondary-300" :stroke-width="2" aria-hidden="true" />
                                Voice generation
                            </span>
                            <strong class="text-sm font-semibold tabular-nums text-secondary-200">
                                {{ isComplimentary ? 'Unlimited' : `${formatVoiceDuration(voiceMillisecondsRemaining)} left` }}
                            </strong>
                        </div>
                        <div
                            class="mt-3 h-1.5 overflow-hidden rounded-full bg-secondary-300/15"
                            role="progressbar"
                            aria-label="Voice generation minutes remaining"
                            :aria-valuenow="voiceProgress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuetext="`${formatVoiceDuration(voiceMillisecondsRemaining)} of ${voiceMinutesLimit} voice generation minutes remaining`"
                        >
                            <span
                                class="block h-full rounded-full bg-secondary-300 transition-[width] duration-300"
                                :style="{ width: `${voiceProgress}%` }"
                            />
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            {{ isComplimentary ? 'Complimentary investor access is not metered' : isOneTime ? 'One-time free allowance' : `of ${formatNumber(voiceMinutesLimit)} minutes this period` }}
                        </p>
                    </div>
                </div>

                <Link
                    v-if="!isComplimentary"
                    :href="pricing().url"
                    class="mt-4 inline-flex h-9 w-full items-center justify-center rounded-lg border border-primary/50 bg-primary/10 px-3 text-xs font-semibold text-primary-100 transition-colors hover:border-primary hover:bg-primary/20"
                >
                    Purchase a new subscription
                </Link>
            </section>
        </template>

        <section v-else class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-sm font-medium text-gray-100">No active subscription</p>
            <p class="mt-1.5 text-xs leading-relaxed text-gray-500">
                Choose a plan to unlock solo stories, actions, and voice narration.
            </p>
            <Link
                :href="pricing().url"
                class="mt-4 inline-flex h-9 items-center justify-center rounded-lg border border-primary/50 bg-primary/10 px-3 text-xs font-semibold text-primary-100 transition-colors hover:border-primary hover:bg-primary/20"
            >
                Purchase a subscription
            </Link>
        </section>
    </div>
</template>
