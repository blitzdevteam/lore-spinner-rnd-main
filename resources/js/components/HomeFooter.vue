<script setup lang="ts">
import logoTag from '@/assets/logo/logo-tag.png';
import { index } from '@/wayfinder/routes';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { index as dashboardIndex } from '@/wayfinder/routes/user/dashboard';
import { Link, usePage } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import { computed } from 'vue';

interface FooterLink {
    label: string;
    href: string;
}

interface FooterColumn {
    title: string;
    links: FooterLink[];
    wide?: boolean;
}

const navigate: FooterColumn = {
    title: 'Navigate',
    links: [
        { label: 'Home', href: index().url },
        { label: 'Library', href: storiesIndex().url },
        { label: 'Profile', href: dashboardIndex().url },
        { label: 'About Us', href: '/about-us' },
    ],
};

const moods: FooterColumn = {
    title: 'Moods',
    links: [
        { label: 'Heartfelt', href: `${storiesIndex().url}?mood=heartfelt` },
        { label: 'Adventurous', href: `${storiesIndex().url}?mood=adventurous` },
        { label: 'Mysterious', href: `${storiesIndex().url}?mood=mysterious` },
        { label: 'Epic', href: `${storiesIndex().url}?mood=epic` },
        { label: 'Whimsical', href: `${storiesIndex().url}?mood=whimsical` },
    ],
};

const contactEmail = 'contact@lorespinner.com';
const page = usePage();

const getInTouch: FooterColumn = {
    title: 'Get in touch',
    wide: true,
    links: [{ label: contactEmail, href: `mailto:${contactEmail}` }],
};

const linkColumns: FooterColumn[] = [navigate, moods];

function isNativeAnchor(href: string): boolean {
    return href.startsWith('http://') || href.startsWith('https://') || href.startsWith('mailto:') || href === '#';
}

function getUrlParts(url: string): { path: string; mood: string | null } {
    const [path = '/', query = ''] = url.split('?');

    return {
        path: path || '/',
        mood: new URLSearchParams(query).get('mood'),
    };
}

const currentPath = computed(() => getUrlParts(page.url).path);
const currentMood = computed(() => getUrlParts(page.url).mood);

function isFooterLinkActive(link: FooterLink): boolean {
    if (isNativeAnchor(link.href)) {
        return false;
    }

    const { path, mood } = getUrlParts(link.href);

    if (path === index().url) {
        return currentPath.value === '/';
    }

    if (path === storiesIndex().url && mood !== null) {
        return currentPath.value === storiesIndex().url && currentMood.value === mood;
    }

    if (path === storiesIndex().url) {
        return currentPath.value === storiesIndex().url && currentMood.value === null;
    }

    if (path === dashboardIndex().url) {
        return currentPath.value.startsWith(dashboardIndex().url);
    }

    return currentPath.value === path;
}

const linkRowClass =
    'flex flex-col gap-[0.625rem] font-light text-[0.8125rem] leading-[1.125rem] tracking-[-0.48px] text-white sm:text-[0.75rem] sm:leading-[0.9375rem]';
const headingClass = 'text-[0.9375rem] font-semibold leading-normal tracking-[-0.64px] text-white sm:text-[1rem]';
</script>

<template>
    <footer class="relative bg-[#0f0f0f] text-white">
        <div class="container">
            <div class="container-content pt-12 pb-10 sm:pt-16 sm:pb-12 lg:pt-[4.625rem] lg:pb-[5.8125rem]">
                <div class="flex flex-col gap-10 lg:flex-row lg:items-start lg:justify-between lg:gap-16">
                    <div class="flex w-full max-w-[27.8125rem] shrink-0 flex-col items-center text-center">
                        <img
                            :src="logoTag"
                            alt="LoreSpinner"
                            class="footer-logo mx-auto block w-auto object-contain"
                            width="800"
                            height="180"
                            decoding="async"
                        />
                        <p
                            class="mt-3 max-w-[34ch] text-[0.8125rem] leading-[1.45] font-light tracking-[-0.52px] text-white/90 sm:mt-2.5 sm:max-w-none sm:leading-[100%] lg:mt-[0.6875rem] !ml-[35px]"
                        >
                            You’re not just listening to a story. You’re inside one.
                        </p>
                    </div>

                    <nav
                        aria-label="Footer"
                        class="grid w-full grid-cols-2 gap-x-6 gap-y-10 border-t border-white/10 pt-10 sm:gap-x-8 lg:flex lg:w-auto lg:flex-nowrap lg:justify-end lg:gap-x-6 lg:border-0 lg:pt-0"
                    >
                        <div v-for="col in linkColumns" :key="col.title" class="flex min-w-0 flex-col gap-[1.0625rem]">
                            <p :class="headingClass">
                                {{ col.title }}
                            </p>
                            <ul :class="linkRowClass">
                                <li v-for="link in col.links" :key="link.label">
                                    <a
                                        v-if="isNativeAnchor(link.href)"
                                        :href="link.href"
                                        class="block transition-colors hover:text-[#8e8e8e]"
                                        :class="isFooterLinkActive(link) ? 'text-primary' : 'text-white'"
                                        :rel="link.href.startsWith('http') ? 'noopener noreferrer' : undefined"
                                        :target="link.href.startsWith('http') ? '_blank' : undefined"
                                    >
                                        {{ link.label }}
                                    </a>
                                    <Link
                                        v-else
                                        :href="link.href"
                                        class="block transition-colors hover:text-primary"
                                        :class="isFooterLinkActive(link) ? 'text-primary' : 'text-white'"
                                    >
                                        {{ link.label }}
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <div
                            class="col-span-2 flex flex-col gap-[1.0625rem] rounded-2xl border border-white/10 bg-white/[0.03] p-4 sm:col-span-1 sm:rounded-none sm:border-0 sm:bg-transparent sm:p-0 lg:w-[10rem]"
                        >
                            <p :class="headingClass">
                                {{ getInTouch.title }}
                            </p>
                            <ul :class="linkRowClass">
                                <li v-for="link in getInTouch.links" :key="link.label">
                                    <a
                                        :href="link.href"
                                        class="inline-flex max-w-full items-start gap-2.5 break-words transition-colors hover:text-primary sm:block sm:gap-0"
                                    >
                                        <Mail class="mt-0.5 size-4 shrink-0 text-primary sm:hidden" :stroke-width="2" aria-hidden="true" />
                                        <span>{{ link.label }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </div>

                <div class="mt-10 border-t border-white/10 pt-6 sm:mt-14 lg:mt-[8rem] lg:border-0 lg:pt-0">
                    <p
                        class="text-center text-[0.8125rem] leading-normal font-normal tracking-[-0.56px] text-[#8e8e8e] sm:text-[0.875rem] lg:text-right"
                    >
                        © Copyright 2026. Lorespinner Inc. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.footer-logo {
    height: 3.5rem;
    max-width: min(100%, 16rem);
}

@media (min-width: 40rem) {
    .footer-logo {
        height: 5.25rem;
        max-width: min(100%, 24rem);
    }
}

@media (min-width: 48rem) {
    .footer-logo {
        height: 6rem;
        max-width: min(100%, 27.5rem);
    }
}

@media (min-width: 64rem) {
    .footer-logo {
        height: 7rem;
        max-width: min(100%, 29.5rem);
    }
}
</style>
