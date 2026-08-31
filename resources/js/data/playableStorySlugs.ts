import type { StoryInterface } from '@/types';

/**
 * Mirrors `config/stories.php` launch + boiled playable slugs.
 * Actual playability also requires `adaptation_status === completed` (see `is_playable` on API stories).
 */
export const LAUNCH_PLAYABLE_SLUGS = [
    'the-wonderful-wizard-of-oz',
    'peter-pan',
    'the-adventure-of-the-speckled-band',
    'a-scandal-in-bohemia',
    'the-adventure-of-the-blue-carbuncle',
    'dracula',
    'frankenstein',
    'dr-jekyll-and-mr-hyde',
    'the-tell-tale-heart',
    'pride-and-prejudice',
    'emma',
    'around-the-world-in-eighty-days',
    'journey-to-the-center-of-the-earth',
    'the-three-musketeers',
    'robin-hood',
    'the-invisible-man',
    'sir-gawain-and-the-green-knight',
    'treasure-island',
] as const;

/** Already-boiled RnD titles — playable when `is_playable` is true from the API. */
export const BOILED_PLAYABLE_SLUGS = [
    'dr-jekyll-and-mr-hyde',
    'the-strange-case-of-dr-jekyll-and-mr-hyde',
    'momotaro-the-peach-boy',
    'the-unwritten-ones',
] as const;

export const PLAYABLE_ALLOWLIST_SLUGS = [
    ...LAUNCH_PLAYABLE_SLUGS,
    ...BOILED_PLAYABLE_SLUGS,
] as const;

/** Mirrors `config/stories.php` playable_slug_aliases. */
export const PLAYABLE_SLUG_ALIASES: Record<string, string> = {
    'the-strange-case-of-dr-jekyll-and-mr-hyde': 'dr-jekyll-and-mr-hyde',
    'jekyll-and-hyde': 'dr-jekyll-and-mr-hyde',
};

export type LaunchPlayableSlug = (typeof LAUNCH_PLAYABLE_SLUGS)[number];

const PLAYABLE_ORDER_INDEX = new Map<string, number>(
    [...new Set(PLAYABLE_ALLOWLIST_SLUGS)].map((slug, index) => [slug, index]),
);

export function canonicalPlayableSlug(slug: string): string {
    return PLAYABLE_SLUG_ALIASES[slug] ?? slug;
}

export function playableOrderIndex(slug: string): number {
    const canonical = canonicalPlayableSlug(slug);

    return PLAYABLE_ORDER_INDEX.get(canonical) ?? PLAYABLE_ORDER_INDEX.get(slug) ?? Number.MAX_SAFE_INTEGER;
}

export function compareStoriesByPlayableOrder(
    a: Pick<StoryInterface, 'slug' | 'title' | 'is_playable'>,
    b: Pick<StoryInterface, 'slug' | 'title' | 'is_playable'>,
): number {
    const aPlayable = a.is_playable === true ? 0 : 1;
    const bPlayable = b.is_playable === true ? 0 : 1;

    if (aPlayable !== bPlayable) {
        return aPlayable - bPlayable;
    }

    const rank = playableOrderIndex(a.slug) - playableOrderIndex(b.slug);

    if (rank !== 0) {
        return rank;
    }

    return a.title.localeCompare(b.title, undefined, { sensitivity: 'base' });
}

export function compareWorldsByPlayableOrder(
    a: { title: string; position?: number; stories?: Pick<StoryInterface, 'slug' | 'is_playable'>[] },
    b: { title: string; position?: number; stories?: Pick<StoryInterface, 'slug' | 'is_playable'>[] },
): number {
    const playableRank = (world: typeof a): number =>
        Math.min(
            Number.MAX_SAFE_INTEGER,
            ...(world.stories ?? [])
                .filter((story) => story.is_playable)
                .map((story) => playableOrderIndex(story.slug)),
        );

    const rank = playableRank(a) - playableRank(b);

    if (rank !== 0) {
        return rank;
    }

    const position = (a.position ?? 0) - (b.position ?? 0);

    if (position !== 0) {
        return position;
    }

    return a.title.localeCompare(b.title, undefined, { sensitivity: 'base' });
}

type PlayableStoryInput = string | Pick<StoryInterface, 'slug' | 'is_playable'>;

export function isStoryPlayable(input: PlayableStoryInput): boolean {
    if (typeof input !== 'string' && input.is_playable !== undefined) {
        return input.is_playable;
    }

    // Slug-only callers (mood mocks, static config) cannot know boil status.
    return false;
}

/** @deprecated Use LAUNCH_PLAYABLE_SLUGS */
export const REVIEW_PLAYABLE_SLUGS = LAUNCH_PLAYABLE_SLUGS;

/** @deprecated Use LAUNCH_PLAYABLE_SLUGS */
export const PLAYABLE_STORY_SLUGS = LAUNCH_PLAYABLE_SLUGS;

export type PlayableStorySlug = LaunchPlayableSlug;
export type ReviewPlayableSlug = LaunchPlayableSlug;
