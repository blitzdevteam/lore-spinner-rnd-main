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
        <nav v-bind="$attrs" class="flex items-center gap-3 self-center md:gap-4" aria-label="Account">
            <Link
                :href="login.create().url"
                class="inline-flex h-9 items-center justify-center whitespace-nowrap px-1 font-[Inter] text-[0.9375rem] leading-none font-normal text-[#D8D8D8] transition-colors hover:text-primary focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
            >
                Log in
            </Link>
            <Link
                :href="register.create().url"
                class="inline-flex h-9 items-center justify-center rounded-full border border-primary bg-cta-fill px-4 font-[Inter] text-[0.8125rem] leading-none font-medium tracking-[0.02em] whitespace-nowrap text-cta-text transition-colors hover:border-primary-300 hover:bg-cta-hover active:bg-cta-active focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
            >
                Sign up
            </Link>
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
