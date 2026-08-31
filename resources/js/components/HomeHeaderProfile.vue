<script setup lang="ts">
import { logout } from '@/wayfinder/routes/user/authentication';
import login from '@/wayfinder/routes/user/authentication/login';
import register from '@/wayfinder/routes/user/authentication/register';
import { DEFAULT_PROFILE_AVATAR, resolvePublicStorageUrl } from '@/utils/publicStorageUrl';
import { Link, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

defineOptions({ inheritAttrs: false });

withDefaults(
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

const profileWrap = ref<HTMLElement | null>(null);
const dropdownOpen = ref(false);

function onAvatarError(event: Event) {
    const img = event.target as HTMLImageElement | null;
    if (!img || img.src.endsWith(DEFAULT_PROFILE_AVATAR)) {
        return;
    }
    img.src = DEFAULT_PROFILE_AVATAR;
}

onClickOutside(profileWrap, () => {
    dropdownOpen.value = false;
});
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
                :aria-expanded="dropdownOpen"
                aria-haspopup="true"
                @click="dropdownOpen = !dropdownOpen"
            >
                <img
                    :src="avatarSrc"
                    alt=""
                    class="object-cover"
                    :class="[
                        'cursor-pointer rounded-full border-2 border-primary outline-2 outline-transparent transition hover:brightness-110',
                        compact ? 'size-11' : 'size-[2.8125rem]',
                    ]"
                    @error="onAvatarError"
                />
            </button>

            <div
                v-show="dropdownOpen"
                class="profile-dropdown absolute top-full right-0 z-50 mt-3 min-w-[10.5rem] overflow-hidden rounded-[0.9375rem] px-4 py-3"
                role="menu"
            >
                <p class="px-1 py-2 text-sm text-gray-300">@{{ auth.username }}</p>
                <Link
                    :method="logout().method"
                    :href="logout().url"
                    class="block w-full rounded-[0.5rem] px-1 py-2 text-left text-sm font-normal text-red-500 transition-colors hover:bg-red-700/10"
                    role="menuitem"
                    @click="dropdownOpen = false"
                >
                    Logout
                </Link>
            </div>
        </div>
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
        linear-gradient(180deg, rgba(111, 175, 186, 0.2) 1.34%, rgba(102, 102, 102, 0) 12.75%),
        linear-gradient(0deg, rgba(2, 3, 3, 0.58), rgba(2, 3, 3, 0.58)), rgba(23, 26, 27, 0.86);
    box-shadow:
        0 4px 5rem rgba(0, 0, 0, 0.2),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
}
</style>
