import { filterVisibleLibraryStories } from '@/data/hiddenLibraryStorySlugs';
import { MOCK_LIBRARY_STORIES } from '@/data/mockLibraryStories';
import { canonicalMoodStorySlug, dedupeStoriesByCanonicalSlug } from '@/data/moodStories';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { StoryInterface } from '@/types';

/** API stories plus placeholder mocks for slugs not yet seeded on the server. */
export function mergeLibraryStoriesWithMocks(apiStories: StoryInterface[]): StoryInterface[] {
    const real = filterVisibleLibraryStories(apiStories).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));

    const realCanonicalSlugs = new Set(real.map((story) => canonicalMoodStorySlug(story.slug)));

    const extra = MOCK_LIBRARY_STORIES.filter(
        (mock) => !realCanonicalSlugs.has(canonicalMoodStorySlug(mock.slug)),
    ).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));

    return dedupeStoriesByCanonicalSlug([...real, ...extra]);
}
