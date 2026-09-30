<script setup lang="ts">
import type { SubscriptionUsageSummary } from '@/types';
import AchievementsModal from '@/components/AchievementsModal.vue';
import { pricing } from '@/wayfinder/routes';
import { logout } from '@/wayfinder/routes/user/authentication';
import login from '@/wayfinder/routes/user/authentication/login';
import register from '@/wayfinder/routes/user/authentication/register';
import { DEFAULT_PROFILE_AVATAR, resolvePublicStorageUrl } from '@/utils/publicStorageUrl';
import { Link, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { ArrowRight, ChevronDown, Sparkles, Trophy, Volume2, Zap } from 'lucide-vue-next';
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
const isComplimentary = computed(() => Boolean(subscriptionUsage.value?.is_complimentary));
const isOneTime = computed(() => Boolean(subscriptionUsage.value?.is_one_time));
const planName = computed(() => subscriptionUsage.value?.plan.name ?? null);
const avatarSrc = computed(() => resolvePublicStorageUrl(auth.value?.avatar));
const profileName = computed(() => auth.value?.full_name?.trim() || auth.value?.username || 'Player');
const profileHandle = computed(() => {
    const username = auth.value?.username;

    return username ? `@${username}` : auth.value?.email ?? '';
});
const actionsRemaining = computed(() => subscriptionUsage.value?.actions.remaining ?? 0);
const actionsLimit = computed(() => subscriptionUsage.value?.actions.limit ?? 0);
const voiceMillisecondsRemaining = computed(() => Math.max(0, subscriptionUsage.value?.voice.remaining_milliseconds ?? 0));
const voiceMinutesLimit = computed(() => subscriptionUsage.value?.voice.limit_minutes ?? 0);
const voiceMillisecondsLimit = computed(() => voiceMinutesLimit.value * 60_000);
const actionsProgress = computed(() => (isComplimentary.value ? 100 : allowancePercent(actionsRemaining.value, actionsLimit.value)));
const voiceProgress = computed(() => (isComplimentary.value ? 100 : allowancePercent(voiceMillisecondsRemaining.value, voiceMillisecondsLimit.value)));
const renewalLabel = computed(() => {
    if (isOneTime.value) {
        return 'One-time free';
    }

    if (isComplimentary.value) {
        return 'Complimentary';
    }

    const periodEndsAt = subscriptionUsage.value?.period_ends_at;

    if (! periodEndsAt) {
        return null;
    }

    const date = new Date(periodEndsAt);

    const prefix = subscriptionUsage.value?.renews === false ? 'Ends' : 'Renews';

    return Number.isNaN(date.getTime())
        ? `${prefix} at the end of your billing period`
        : `${prefix} ${date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`;
});

const profileWrap = ref<HTMLElement | null>(null);
const dropdownOpen = ref(false);
const achievementsOpen = ref(false);

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

    return seconds === 0 ? `${minutes}m` : `${minutes}m ${seconds}s`;
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

function openAchievements(): void {
    closeDropdown();
    achievementsOpen.value = true;
}

onClickOutside(profileWrap, closeDropdown);
</script>

<template>
    <template v-if="auth === null">
        <nav v-bind="$attrs" class="header-auth" aria-label="Account">
            <div class="header-auth__group">
                <Link :href="login.create().url" class="header-auth__btn header-auth__btn--login">
                    Log in
                </Link>
                <Link :href="register.create().url" class="header-auth__btn header-auth__btn--signup">
                    Sign up
                </Link>
            </div>
        </nav>
    </template>
    <template v-else>
        <div ref="profileWrap" v-bind="$attrs" class="relative">
            <button
                type="button"
                class="group inline-flex items-center rounded-[0.85rem] outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                :aria-expanded="dropdownOpen"
                aria-haspopup="dialog"
                :aria-label="dropdownOpen ? 'Close profile menu' : 'Open profile menu'"
                @click="dropdownOpen = !dropdownOpen"
            >
                <span class="relative z-10 shrink-0 rounded-full">
                    <img
                        :src="avatarSrc"
                        :alt="`${profileName} profile`"
                        class="cursor-pointer rounded-full border-2 border-primary object-cover outline-2 outline-transparent transition group-hover:brightness-110"
                        :class="
                            props.compact
                                ? 'size-11'
                                : 'size-[2.8125rem]'
                        "
                        @error="onAvatarError"
                    />
                </span>
                <ChevronDown
                    v-if="!props.compact"
                    class="ml-1 size-3.5 text-[#a9b4b8] transition-transform group-hover:text-primary lg:ml-0"
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
                                    {{ isComplimentary ? 'Unlimited' : formatNumber(actionsRemaining) }}
                                    <span v-if="!isComplimentary" class="text-xs font-medium text-[#95a1a4]">left</span>
                                </p>
                                <p class="mt-1 text-[0.6875rem] text-[#849094]">
                                    {{ isComplimentary ? 'Complimentary access' : isOneTime ? 'One-time free allowance' : `of ${formatNumber(actionsLimit)} actions` }}
                                </p>
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
                                    {{ isComplimentary ? 'Unlimited' : formatVoiceDuration(voiceMillisecondsRemaining) }}
                                </p>
                                <p class="mt-1 text-[0.6875rem] text-[#849094]">
                                    {{ isComplimentary ? 'Complimentary access' : isOneTime ? 'One-time free allowance' : `of ${formatNumber(voiceMinutesLimit)} min` }}
                                </p>
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
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm font-normal text-[#f1f4f2] transition-colors hover:bg-white/5"
                            @click="openAchievements"
                        >
                            <Trophy class="size-4 text-primary-200" :stroke-width="2" />
                            Achievements
                        </button>
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
        <AchievementsModal :open="achievementsOpen" @close="achievementsOpen = false" />
    </template>
</template>

<style scoped>
.header-auth {
    display: flex;
    align-items: center;
}

.header-auth__group {
    display: inline-flex;
    align-items: center;
    gap: 0.125rem;
    padding: 0.1875rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(17, 24, 27, 0.85);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

.header-auth__btn {
    display: inline-flex;
    height: 2.125rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5625rem;
    padding: 0 0.875rem;
    font-family: Inter, sans-serif;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    letter-spacing: 0.01em;
    white-space: nowrap;
    text-decoration: none;
    transition:
        color 150ms ease,
        background-color 150ms ease,
        border-color 150ms ease,
        box-shadow 150ms ease,
        transform 150ms ease;
}

.header-auth__btn:focus-visible {
    outline: 2px solid var(--color-primary, #59c1ca);
    outline-offset: 2px;
}

.header-auth__btn--login {
    color: rgb(200, 206, 209);
}

.header-auth__btn--login:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.05);
}

.header-auth__btn--signup {
    border: 1px solid rgba(89, 193, 202, 0.35);
    background: linear-gradient(180deg, rgba(89, 193, 202, 0.24) 0%, rgba(22, 63, 70, 0.95) 100%);
    color: #fff;
    box-shadow:
        0 0 0 1px rgba(89, 193, 202, 0.1),
        0 4px 16px rgba(89, 193, 202, 0.12);
}

.header-auth__btn--signup:hover {
    border-color: rgba(143, 203, 211, 0.55);
    background: linear-gradient(180deg, rgba(89, 193, 202, 0.32) 0%, rgba(30, 80, 88, 0.98) 100%);
}

.header-auth__btn--signup:active {
    transform: scale(0.98);
}

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
