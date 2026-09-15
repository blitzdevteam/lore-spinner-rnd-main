<script setup lang="ts">
import AchievementsModal from '@/components/AchievementsModal.vue';
import { logout } from '@/wayfinder/routes/user/authentication';
import login from '@/wayfinder/routes/user/authentication/login';
import register from '@/wayfinder/routes/user/authentication/register';
import { DEFAULT_PROFILE_AVATAR, resolvePublicStorageUrl } from '@/utils/publicStorageUrl';
import { Link, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { ChevronDown, Trophy } from 'lucide-vue-next';
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
const avatarSrc = computed(() => resolvePublicStorageUrl(auth.value?.avatar));
const profileName = computed(() => auth.value?.full_name?.trim() || auth.value?.username || 'Player');
const profileHandle = computed(() => {
    const username = auth.value?.username;

    return username ? `@${username}` : auth.value?.email ?? '';
});
const profileWrap = ref<HTMLElement | null>(null);
const dropdownOpen = ref(false);
const achievementsOpen = ref(false);

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
