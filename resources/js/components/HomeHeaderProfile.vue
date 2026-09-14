<script setup lang="ts">
import type { SubscriptionUsageSummary } from '@/types';
import { pricing } from '@/wayfinder/routes';
import { logout } from '@/wayfinder/routes/user/authentication';
import login from '@/wayfinder/routes/user/authentication/login';
import { DEFAULT_PROFILE_AVATAR, resolvePublicStorageUrl } from '@/utils/publicStorageUrl';
import { Link, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { ArrowRight, ChevronDown, Sparkles, Volume2, Zap } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const page = usePage();

const auth = computed(() => page.props.auth);
const subscriptionUsage = computed<SubscriptionUsageSummary | null>(() => auth.value?.subscription_usage ?? null);
const planName = computed(() => subscriptionUsage.value?.plan.name ?? null);
const avatarSrc = computed(() => resolvePublicStorageUrl(auth.value?.avatar));
const profileName = computed(() => auth.value?.full_name?.trim() || auth.value?.username || 'Player');
const profileHandle = computed(() => {
    const username = auth.value?.username;

    return username ? `@${username}` : auth.value?.email ?? '';
});
const actionsRemaining = computed(() => subscriptionUsage.value?.actions.remaining ?? 0);
const actionsLimit = computed(() => subscriptionUsage.value?.actions.limit ?? 0);
const voiceMinutesRemaining = computed(() => {
    const milliseconds = subscriptionUsage.value?.voice.remaining_milliseconds ?? 0;

    return Math.max(0, Math.ceil(milliseconds / 60_000));
});
const voiceMinutesLimit = computed(() => subscriptionUsage.value?.voice.limit_minutes ?? 0);
const actionsProgress = computed(() => allowancePercent(actionsRemaining.value, actionsLimit.value));
const voiceProgress = computed(() => allowancePercent(voiceMinutesRemaining.value, voiceMinutesLimit.value));
const renewalLabel = computed(() => {
    const periodEndsAt = subscriptionUsage.value?.period_ends_at;

    if (! periodEndsAt) {
        return null;
    }

    const date = new Date(periodEndsAt);

    return Number.isNaN(date.getTime())
        ? 'Renews at the end of your billing period'
        : `Renews ${date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`;
});

const profileWrap = ref<HTMLElement | null>(null);
const dropdownOpen = ref(false);

function allowancePercent(remaining: number, limit: number): number {
    if (limit <= 0) {
        return 0;
    }

    return Math.min(100, Math.max(0, (remaining / limit) * 100));
}

function formatNumber(value: number): string {
    return Math.max(0, value).toLocaleString();
}

function onAvatarError(event: Event): void {
    const img = event.target as HTMLImageElement | null;
    if (!img || img.src.endsWith(DEFAULT_PROFILE_AVATAR)) {
        return;
    }

    img.src = DEFAULT_PROFILE_AVATAR;
}

function closeDropdown(): void {
    dropdownOpen.value = false;
}

onClickOutside(profileWrap, closeDropdown);
</script>

<template>
    <template v-if="auth === null">
        <Link
            v-bind="$attrs"
            :href="login.create().url"
            class="flex h-10 items-center justify-center whitespace-nowrap px-4 text-base font-normal leading-none text-secondary-300 transition-colors hover:text-secondary-200"
        >
            Log in/Sign Up
        </Link>
    </template>
    <template v-else>
        <div ref="profileWrap" v-bind="$attrs" class="relative flex items-center gap-2">
            <div
                v-if="planName && !props.compact"
                :title="planName"
                class="hidden w-[11.5rem] flex-col gap-1.5 rounded-[0.75rem] border border-primary/20 bg-primary/5 px-3 py-2 shadow-[0_0_1.5rem_rgba(101,216,232,0.08)] lg:flex"
            >
                <div class="flex min-w-0 items-center gap-1.5">
                    <Sparkles class="size-3 shrink-0 text-primary" :stroke-width="2" aria-hidden="true" />
                    <span class="truncate font-[Inter] text-[0.6875rem] font-medium tracking-[0.04em] text-primary-100">
                        {{ planName }}
                    </span>
                </div>

                <div class="grid gap-1" aria-label="Remaining subscription allowances">
                    <div class="flex items-center gap-1.5">
                        <Zap class="size-2.5 shrink-0 text-primary" :stroke-width="2" aria-hidden="true" />
                        <div
                            class="h-1.5 flex-1 overflow-hidden rounded-full bg-primary/20"
                            role="progressbar"
                            aria-label="Actions remaining"
                            :aria-valuenow="actionsProgress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuetext="`${actionsRemaining} of ${actionsLimit} actions remaining`"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-[width] duration-300"
                                :style="{ width: `${actionsProgress}%` }"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Volume2 class="size-2.5 shrink-0 text-secondary-300" :stroke-width="2" aria-hidden="true" />
                        <div
                            class="h-1.5 flex-1 overflow-hidden rounded-full bg-secondary-300/20"
                            role="progressbar"
                            aria-label="Voice generation minutes remaining"
                            :aria-valuenow="voiceProgress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuetext="`${voiceMinutesRemaining} of ${voiceMinutesLimit} voice generation minutes remaining`"
                        >
                            <div
                                class="h-full rounded-full bg-secondary-300 transition-[width] duration-300"
                                :style="{ width: `${voiceProgress}%` }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <button
                type="button"
                class="group inline-flex items-center gap-1 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                :aria-expanded="dropdownOpen"
                aria-haspopup="dialog"
                :aria-label="dropdownOpen ? 'Close profile menu' : 'Open profile menu'"
                @click="dropdownOpen = !dropdownOpen"
            >
                <img
                    :src="avatarSrc"
                    :alt="`${profileName} profile`"
                    class="cursor-pointer rounded-full border-2 border-primary object-cover outline-2 outline-transparent transition group-hover:brightness-110"
                    :class="props.compact ? 'size-11' : 'size-[2.8125rem]'"
                    @error="onAvatarError"
                />
                <ChevronDown
                    v-if="!props.compact"
                    class="size-3.5 text-[#a9b4b8] transition-transform group-hover:text-primary"
                    :class="dropdownOpen ? 'rotate-180' : ''"
                    :stroke-width="2"
                    aria-hidden="true"
                />
            </button>

            <Transition name="profile-menu">
                <section
                    v-show="dropdownOpen"
                    class="profile-dropdown absolute top-full right-0 z-50 mt-3 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-[1rem] p-4"
                    role="dialog"
                    aria-label="Account and subscription"
                    @keydown.escape="closeDropdown"
                >
                    <div class="flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-[Inter] text-sm font-semibold text-[#f1f4f2]">{{ profileName }}</p>
                            <p v-if="profileHandle" class="mt-0.5 truncate text-xs text-[#98a2a5]">{{ profileHandle }}</p>
                        </div>
                        <span
                            v-if="subscriptionUsage"
                            class="shrink-0 rounded-full border border-primary/30 bg-primary/10 px-2 py-1 text-[0.625rem] font-semibold tracking-[0.12em] text-primary-100 uppercase"
                        >
                            Active plan
                        </span>
                    </div>

                    <div v-if="subscriptionUsage" class="subscription-summary mt-4 rounded-[0.875rem] border border-primary/25 p-3.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[0.625rem] font-semibold tracking-[0.14em] text-primary-200 uppercase">Subscription</p>
                                <p class="mt-1 truncate font-[Inter] text-base font-semibold text-white">{{ planName }}</p>
                            </div>
                            <p v-if="renewalLabel" class="max-w-24 text-right text-[0.6875rem] leading-relaxed text-[#a4b0b3]">{{ renewalLabel }}</p>
                        </div>

                        <div class="mt-3.5 grid grid-cols-2 gap-2.5">
                            <div class="allowance-card rounded-xl border border-white/8 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="grid size-7 place-items-center rounded-lg bg-primary/10 text-primary">
                                        <Zap class="size-3.5" :stroke-width="2" />
                                    </span>
                                    <span class="text-[0.625rem] font-medium text-[#aab4b7]">Actions</span>
                                </div>
                                <p class="mt-3 text-lg font-semibold leading-none tabular-nums text-[#f2faf9]">
                                    {{ formatNumber(actionsRemaining) }}
                                    <span class="text-xs font-medium text-[#95a1a4]">left</span>
                                </p>
                                <p class="mt-1 text-[0.6875rem] text-[#849094]">of {{ formatNumber(actionsLimit) }} actions</p>
                                <div class="mt-3 h-1 overflow-hidden rounded-full bg-white/10">
                                    <span class="block h-full rounded-full bg-primary transition-[width] duration-300" :style="{ width: `${actionsProgress}%` }" />
                                </div>
                            </div>

                            <div class="allowance-card rounded-xl border border-white/8 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="grid size-7 place-items-center rounded-lg bg-secondary-300/10 text-secondary-200">
                                        <Volume2 class="size-3.5" :stroke-width="2" />
                                    </span>
                                    <span class="text-[0.625rem] font-medium text-[#aab4b7]">Voice</span>
                                </div>
                                <p class="mt-3 text-lg font-semibold leading-none tabular-nums text-[#f2faf9]">
                                    {{ formatNumber(voiceMinutesRemaining) }}
                                    <span class="text-xs font-medium text-[#95a1a4]">min</span>
                                </p>
                                <p class="mt-1 text-[0.6875rem] text-[#849094]">of {{ formatNumber(voiceMinutesLimit) }} min</p>
                                <div class="mt-3 h-1 overflow-hidden rounded-full bg-white/10">
                                    <span class="block h-full rounded-full bg-secondary-300 transition-[width] duration-300" :style="{ width: `${voiceProgress}%` }" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="subscription-empty mt-4 rounded-[0.875rem] border border-white/10 p-4">
                        <span class="grid size-8 place-items-center rounded-full border border-primary/30 bg-primary/10 text-primary">
                            <Sparkles class="size-4" :stroke-width="2" />
                        </span>
                        <p class="mt-3 font-[Inter] text-sm font-semibold text-[#f1f4f2]">No active subscription</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-[#a4b0b3]">
                            Choose a plan to unlock solo stories, actions, and voice narration.
                        </p>
                        <Link
                            :href="pricing().url"
                            class="mt-4 flex h-9 items-center justify-center gap-1.5 rounded-lg border border-primary/60 bg-primary/10 px-3 text-xs font-semibold text-primary-100 transition-colors hover:border-primary hover:bg-primary/20"
                            @click="closeDropdown"
                        >
                            Explore plans
                            <ArrowRight class="size-3.5" :stroke-width="2" />
                        </Link>
                    </div>

                    <div class="mt-4 border-t border-white/10 pt-2">
                        <Link
                            :method="logout().method"
                            :href="logout().url"
                            class="flex w-full items-center rounded-lg px-2 py-2 text-left text-sm font-normal text-red-400 transition-colors hover:bg-red-500/10 hover:text-red-300"
                            @click="closeDropdown"
                        >
                            Logout
                        </Link>
                    </div>
                </section>
            </Transition>
        </div>
    </template>
</template>

<style scoped>
.profile-dropdown {
    background:
        linear-gradient(180deg, rgba(111, 175, 186, 0.24) 1.34%, rgba(102, 102, 102, 0) 18%),
        linear-gradient(0deg, rgba(2, 3, 3, 0.74), rgba(2, 3, 3, 0.74)), rgba(23, 26, 27, 0.94);
    box-shadow:
        0 1.5rem 4rem rgba(0, 0, 0, 0.38),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.subscription-summary {
    background: linear-gradient(135deg, rgba(91, 213, 228, 0.14), rgba(17, 24, 24, 0.42));
}

.subscription-empty,
.allowance-card {
    background: rgba(255, 255, 255, 0.035);
}

.profile-menu-enter-active,
.profile-menu-leave-active {
    transition:
        opacity 0.16s ease,
        transform 0.16s ease;
}

.profile-menu-enter-from,
.profile-menu-leave-to {
    opacity: 0;
    transform: translateY(-0.35rem) scale(0.98);
}
</style>
