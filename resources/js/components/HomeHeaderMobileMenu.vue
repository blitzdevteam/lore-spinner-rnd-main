<script setup lang="ts">
import { useHomeHeaderNav } from '@/composables/useHomeHeaderNav';
import login from '@/wayfinder/routes/user/authentication/login';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const visible = defineModel<boolean>('visible', { default: false });

const page = usePage();
const auth = computed(() => page.props.auth);

const { activeMood, isHomeActive, isLibraryActive, isAboutUsActive, isMoodsActive, moodLinks, homeHref, libraryHref, aboutUsHref } =
    useHomeHeaderNav();

const moodsExpanded = ref(false);

watch(visible, (open) => {
    if (!open) moodsExpanded.value = false;
});

function closeMenu(): void {
    visible.value = false;
}

const navLinkClass = (active: boolean): string =>
    [
        'flex min-h-[3.25rem] items-center rounded-xl px-4 font-[Inter] text-[1.0625rem] font-medium leading-none transition-colors',
        active ? 'bg-cta-fill text-primary' : 'text-[#c8ced1] hover:bg-white/5 hover:text-white',
    ].join(' ');

const moodLinkClass = (slug: string): string =>
    [
        'flex min-h-[2.75rem] items-center rounded-lg px-4 font-[Inter] text-[0.9375rem] font-normal transition-colors',
        activeMood.value === slug ? 'border border-primary/60 bg-cta-fill text-primary' : 'text-[#a8aeb1] hover:bg-white/5 hover:text-white',
    ].join(' ');
</script>

<template>
    <Teleport to="body">
        <Transition name="mobile-menu-backdrop">
            <button
                v-if="visible"
                type="button"
                class="mobile-menu-backdrop fixed inset-0 z-[60] border-0 bg-black/70 backdrop-blur-[2px] md:hidden"
                aria-label="Close menu"
                @click="closeMenu"
            />
        </Transition>

        <Transition name="mobile-menu-panel">
            <aside
                v-if="visible"
                class="mobile-menu-panel fixed inset-y-0 right-0 z-[70] flex w-[min(100vw,340px)] flex-col border-l border-white/10 bg-[#0a0c0d] shadow-[-12px_0_48px_rgba(0,0,0,0.45)] md:hidden"
                aria-label="Mobile navigation"
            >
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                    <p class="font-[Inter] text-sm font-medium tracking-[0.18em] text-[#7E7E7E] uppercase">Menu</p>
                    <button
                        type="button"
                        class="grid size-10 place-items-center rounded-full text-white transition-colors hover:bg-white/10"
                        aria-label="Close menu"
                        @click="closeMenu"
                    >
                        <X class="size-5" :stroke-width="2" />
                    </button>
                </div>

                <nav aria-label="Primary" class="flex-1 overflow-y-auto px-4 py-5">
                    <ul class="flex flex-col gap-1">
                        <li>
                            <Link :href="homeHref" :class="navLinkClass(isHomeActive)" @click="closeMenu">Home</Link>
                        </li>

                        <li>
                            <button
                                type="button"
                                :class="[
                                    navLinkClass(isMoodsActive || moodsExpanded),
                                    'w-full justify-between gap-3 border-0 bg-transparent text-left',
                                ]"
                                :aria-expanded="moodsExpanded"
                                @click="moodsExpanded = !moodsExpanded"
                            >
                                <span>Moods</span>
                                <ChevronDown
                                    class="size-5 shrink-0 transition-transform"
                                    :class="moodsExpanded ? 'rotate-180' : ''"
                                    :stroke-width="2"
                                />
                            </button>

                            <ul
                                v-show="moodsExpanded"
                                class="mt-1 flex flex-col gap-1 overflow-hidden rounded-xl border border-white/8 bg-white/[0.03] p-2"
                            >
                                <li v-for="m in moodLinks" :key="m.href + m.title">
                                    <Link :href="m.href" :class="moodLinkClass(m.slug)" @click="closeMenu">
                                        {{ m.title }}
                                    </Link>
                                </li>
                            </ul>
                        </li>

                        <li>
                            <Link :href="libraryHref" :class="navLinkClass(isLibraryActive)" @click="closeMenu"> Library </Link>
                        </li>

                        <li>
                            <Link :href="aboutUsHref" :class="navLinkClass(isAboutUsActive)" @click="closeMenu"> About Us </Link>
                        </li>
                    </ul>
                </nav>

                <div v-if="auth === null" class="border-t border-white/10 p-4">
                    <Link
                        :href="login.create().url"
                        class="flex h-12 w-full items-center justify-center rounded-full border border-primary bg-cta-fill text-sm font-medium text-[#f3f4ee] transition-colors hover:border-primary-300 hover:bg-cta-hover"
                        @click="closeMenu"
                    >
                        Log in / Sign Up
                    </Link>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>

<style scoped>
.mobile-menu-backdrop-enter-active,
.mobile-menu-backdrop-leave-active {
    transition: opacity 0.25s ease;
}

.mobile-menu-backdrop-enter-from,
.mobile-menu-backdrop-leave-to {
    opacity: 0;
}

.mobile-menu-panel-enter-active,
.mobile-menu-panel-leave-active {
    transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.mobile-menu-panel-enter-from,
.mobile-menu-panel-leave-to {
    transform: translateX(100%);
}
</style>
