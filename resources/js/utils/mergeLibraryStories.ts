import { filterVisibleLibraryStories } from '@/data/hiddenLibraryStorySlugs';
import { MOCK_LIBRARY_STORIES } from '@/data/mockLibraryStories';
import { canonicalMoodStorySlug, dedupeStoriesByCanonicalSlug } from '@/data/moodStories';
import { canonicalPlayableSlug, PLAYABLE_ALLOWLIST_SLUGS } from '@/data/playableStorySlugs';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { StoryInterface } from '@/types';

const PLAYABLE_ALLOWLIST = new Set<string>(PLAYABLE_ALLOWLIST_SLUGS);

function isPlayableAllowlistSlug(slug: string): boolean {
    const canonical = canonicalPlayableSlug(slug);

    return PLAYABLE_ALLOWLIST.has(slug) || PLAYABLE_ALLOWLIST.has(canonical);
}

/** API stories plus placeholder mocks for slugs not yet seeded on the server. */
export function mergeLibraryStoriesWithMocks(apiStories: StoryInterface[]): StoryInterface[] {
    const real = filterVisibleLibraryStories(apiStories).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));

    const realCanonicalSlugs = new Set(real.map((story) => canonicalMoodStorySlug(story.slug)));

    const extra = MOCK_LIBRARY_STORIES.filter((mock) => {
        const canonical = canonicalMoodStorySlug(mock.slug);

        if (isPlayableAllowlistSlug(mock.slug)) {
            return false;
        }

        return !realCanonicalSlugs.has(canonical);
    }).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));

    return dedupeStoriesByCanonicalSlug([...real, ...extra]);
}
