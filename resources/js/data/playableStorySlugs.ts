import type { StoryInterface } from '@/types';

/**
 * Mirrors `config/stories.php` review_playable_slugs — approved for boil/review.
 * Actual playability also requires `adaptation_status === completed` (see `is_playable` on API stories).
 */
export const REVIEW_PLAYABLE_SLUGS = [
    'the-adventure-of-the-speckled-band',
    'the-adventure-of-the-blue-carbuncle',
    'a-scandal-in-bohemia',
    'pride-and-prejudice',
    'treasure-island',
    'dr-jekyll-and-mr-hyde',
    'the-tell-tale-heart',
] as const;

export type ReviewPlayableSlug = (typeof REVIEW_PLAYABLE_SLUGS)[number];

type PlayableStoryInput = string | Pick<StoryInterface, 'slug' | 'is_playable'>;

export function isStoryPlayable(input: PlayableStoryInput): boolean {
    if (typeof input !== 'string' && input.is_playable !== undefined) {
        return input.is_playable;
    }

    // Slug-only callers (mood mocks, static config) cannot know boil status.
    return false;
}

/** @deprecated Use REVIEW_PLAYABLE_SLUGS — kept for imports that referenced the old constant name. */
export const PLAYABLE_STORY_SLUGS = REVIEW_PLAYABLE_SLUGS;

export type PlayableStorySlug = ReviewPlayableSlug;
