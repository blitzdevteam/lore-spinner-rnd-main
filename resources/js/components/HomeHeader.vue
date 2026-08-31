<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.png';
import HomeHeaderMenu from '@/components/HomeHeaderMenu.vue';
import HomeHeaderMobileMenu from '@/components/HomeHeaderMobileMenu.vue';
import HomeHeaderProfile from '@/components/HomeHeaderProfile.vue';
import { index } from '@/wayfinder/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const auth = computed(() => page.props.auth);

const mobileMenuOpen = ref(false);

watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

onUnmounted(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <header class="home-header-bar relative z-20 flex min-h-[4.5rem] items-center overflow-visible md:min-h-[5.6875rem]">
        <div class="relative container w-full overflow-visible py-0">
            <div
                class="container-content grid h-[4rem] grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 overflow-visible md:h-[4.6875rem] md:grid-cols-[auto_1fr_auto] md:gap-x-[3.125rem]"
            >
                <Link
                    :href="index().url"
                    class="logo-link min-w-0 shrink justify-self-start pr-1 ring-offset-2 ring-offset-black outline-none focus-visible:ring-2 focus-visible:ring-primary md:shrink-0 md:pr-0"
                >
                    <img
                        :src="mainLogo"
                        alt="LoreSpinner"
                        class="brand-logo select-none"
                        width="286"
                        height="56"
                        decoding="async"
                    />
                </Link>

                <div class="hidden min-h-[4.6875rem] min-w-0 items-center justify-center justify-self-center md:flex md:translate-y-[5px]">
                    <HomeHeaderMenu />
                </div>

                <div class="flex h-full shrink-0 items-center justify-end gap-2 self-center justify-self-end md:gap-3">
                    <HomeHeaderProfile class="hidden md:flex" />

                    <button
                        type="button"
                        class="mobile-menu-trigger grid size-11 place-items-center rounded-full border border-white/15 text-white transition-colors hover:border-primary/60 hover:bg-white/5 md:hidden"
                        :aria-expanded="mobileMenuOpen"
                        aria-controls="home-mobile-menu"
                        aria-label="Open menu"
                        @click="mobileMenuOpen = true"
                    >
                        <Menu class="size-5" :stroke-width="2" />
                    </button>

                    <HomeHeaderProfile v-if="auth !== null" compact class="md:hidden" />
                </div>
            </div>
        </div>

        <HomeHeaderMobileMenu id="home-mobile-menu" v-model:visible="mobileMenuOpen" />
    </header>
</template>

<style scoped>
.home-header-bar {
    background: rgba(0, 0, 0);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}

.brand-logo {
    display: block;
    width: auto;
    max-width: min(100%, 6.825rem);
    height: 1.625rem;
    object-fit: contain;
    object-position: left center;
}

@media (min-width: 48rem) {
    .brand-logo {
        max-width: min(100%, 11.375rem);
        height: 2.275rem;
    }
}

.logo-link {
    display: inline-flex;
    align-items: center;
    align-self: center;
}
</style>
