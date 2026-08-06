import { getMoodNavLinks, normalizeMood } from '@/data/moodBanners';
import { index } from '@/wayfinder/routes';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useHomeHeaderNav() {
    const page = usePage();

    const pageUrl = computed(() => page.url);

    const activeMood = computed(() => {
        const query = pageUrl.value.split('?')[1] ?? '';
        return new URLSearchParams(query).get('mood');
    });

    const normalizedActiveMood = computed(() => normalizeMood(activeMood.value));

    const isHomeActive = computed(() => {
        const p = pageUrl.value.split('?')[0];
        return p === '/' || p === '';
    });

    const isLibraryActive = computed(() => {
        const p = pageUrl.value.split('?')[0];
        return p === storiesIndex().url && !activeMood.value;
    });

    const isAboutUsActive = computed(() => pageUrl.value.split('?')[0] === '/about-us');

    const isMoodsActive = computed(() => activeMood.value !== null);

    const moodLinks = getMoodNavLinks(storiesIndex().url);

    return {
        activeMood,
        normalizedActiveMood,
        isHomeActive,
        isLibraryActive,
        isAboutUsActive,
        isMoodsActive,
        moodLinks,
        homeHref: index().url,
        libraryHref: storiesIndex().url,
        aboutUsHref: '/about-us',
    };
}
