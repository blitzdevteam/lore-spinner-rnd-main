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

export type LaunchPlayableSlug = (typeof LAUNCH_PLAYABLE_SLUGS)[number];

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
