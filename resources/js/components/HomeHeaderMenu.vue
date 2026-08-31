<script setup lang="ts">
import { useHomeHeaderNav } from '@/composables/useHomeHeaderNav';
import { Link } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

const moodsWrap = ref<HTMLElement | null>(null);
const moodsOpen = ref(false);

onClickOutside(moodsWrap, () => {
    moodsOpen.value = false;
});

const {
    activeMood,
    isHomeActive,
    isLibraryActive,
    isWorldsActive,
    isAboutUsActive,
    isMoodsActive,
    moodLinks,
    homeHref,
    libraryHref,
    worldsHref,
    aboutUsHref,
} = useHomeHeaderNav();

/* Reserve underline space without shifting label vertical centering. */
const underlineSlotClass = (): string =>
    'pointer-events-none invisible absolute top-full left-1/2 mt-2 h-[3px] w-16 -translate-x-1/2 shrink-0 rounded-bl-[0.4375rem] rounded-br-[0.4375rem]';
const navItemShell =
    'relative flex h-[4.6875rem] w-fit items-center justify-center rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black';
const navStack = 'relative inline-flex items-center justify-center';
const navLabelRow =
    'relative z-[1] flex h-[2.0625rem] items-center justify-center px-[0.625rem] font-[Inter] text-[1rem] leading-none transition-colors';
const navDividerItem = 'header-nav-divider hidden h-[4.6875rem] items-center md:flex';

const inactiveText = 'text-[#D8D8D8] hover:text-primary';
const activeText = 'text-primary';

const navClass = (active: boolean): string => `${navLabelRow} ${active ? activeText : inactiveText}`;
const moodItemClass = (slug: string): string =>
    [
        'flex h-[1.875rem] w-full items-center rounded-[0.5rem] px-[0.9375rem] font-[Inter] text-[0.875rem] font-normal leading-[2.0625rem] text-white outline-none transition-colors',
        activeMood.value === slug
            ? 'border border-primary bg-cta-fill'
            : 'border border-transparent hover:border-primary hover:bg-cta-fill focus-visible:border-primary focus-visible:bg-cta-fill',
    ].join(' ');
</script>

<template>
    <nav aria-label="Primary">
        <ul class="flex h-[4.6875rem] items-center gap-1 md:gap-0">
            <li>
                <Link :href="homeHref" :class="navItemShell">
                    <span :class="navStack">
                        <span :class="navClass(isHomeActive)">Home</span>
                        <span :class="underlineSlotClass()" aria-hidden="true" />
                    </span>
                </Link>
            </li>

            <li aria-hidden="true" :class="navDividerItem">
                <span class="nav-divider" />
            </li>

            <li ref="moodsWrap" class="relative">
                <button
                    type="button"
                    :class="[navItemShell, 'cursor-pointer border-0 bg-transparent p-0', isMoodsActive || moodsOpen ? activeText : inactiveText]"
                    :aria-expanded="moodsOpen"
                    aria-haspopup="true"
                    @click="moodsOpen = !moodsOpen"
                >
                    <span :class="navStack">
                        <span
                            class="inline-flex h-[2.0625rem] items-center justify-center gap-0 px-[0.625rem] font-[Inter] text-[1rem] leading-none font-light"
                        >
                            Moods
                            <ChevronDown
                                class="h-[1.375rem] w-[1.625rem] shrink-0 text-current transition-transform"
                                :stroke-width="1.75"
                                :class="moodsOpen ? 'rotate-180' : ''"
                                style="margin-right: -8px"
                            />
                        </span>
                        <!-- Reserve bar space so label aligns with other nav items -->
                        <span :class="underlineSlotClass()" aria-hidden="true" />
                    </span>
                </button>

                <div
                    v-show="moodsOpen"
                    class="moods-dropdown absolute top-full left-1/2 z-50 mt-[0.875rem] w-[11.8125rem] -translate-x-1/2 overflow-hidden rounded-[0.9375rem] px-[0.9375rem] py-[0.9375rem]"
                    role="menu"
                >
                    <Link
                        v-for="m in moodLinks"
                        :key="m.href + m.title"
                        :href="m.href"
                        :class="moodItemClass(m.slug)"
                        role="menuitem"
                        @click="moodsOpen = false"
                    >
                        {{ m.title }}
                    </Link>
                </div>
            </li>

            <li aria-hidden="true" :class="navDividerItem">
                <span class="nav-divider" />
            </li>

            <li>
                <Link :href="libraryHref" :class="navItemShell">
                    <span :class="navStack">
                        <span :class="navClass(isLibraryActive)">Library</span>
                        <span :class="underlineSlotClass()" aria-hidden="true" />
                    </span>
                </Link>
            </li>

            <li aria-hidden="true" :class="navDividerItem">
                <span class="nav-divider" />
            </li>

            <li>
                <Link :href="worldsHref" :class="navItemShell">
                    <span :class="navStack">
                        <span :class="navClass(isWorldsActive)">Worlds</span>
                        <span :class="underlineSlotClass()" aria-hidden="true" />
                    </span>
                </Link>
            </li>

            <li aria-hidden="true" :class="navDividerItem">
                <span class="nav-divider" />
            </li>

            <li>
                <Link :href="aboutUsHref" :class="navItemShell">
                    <span :class="navStack">
                        <span :class="navClass(isAboutUsActive)">About Us</span>
                        <span :class="underlineSlotClass()" aria-hidden="true" />
                    </span>
                </Link>
            </li>
        </ul>
    </nav>
</template>

<style scoped>
.header-nav-divider {
    margin-inline: -1.25rem;
}

.nav-divider {
    position: relative;
    width: 1px;
    height: 1.25rem;
    background: linear-gradient(
        178.67deg,
        #000000 0%,
        #ffffff 50%,
        #000000 100%
    );
}
.moods-dropdown {
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

.moods-dropdown > :not(:last-child) {
    margin-bottom: 0.25rem;
}
</style>
