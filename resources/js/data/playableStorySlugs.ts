/** Mirrors `GameController::LAUNCH_SLUGS` — stories available to play at launch. */
export const PLAYABLE_STORY_SLUGS = [
    'the-wonderful-wizard-of-oz',
    'the-adventure-of-the-speckled-band',
    'the-masque-of-the-red-death',
    'treasure-island',
    'dr-jekyll-and-mr-hyde',
    'wasteland',
    'pjs',
    'anima-machina',
    'nocturne',
    'i-love-lucy-job-switching',
    'the-matrix',
    'momotaro-the-peach-boy',
] as const;

export type PlayableStorySlug = (typeof PLAYABLE_STORY_SLUGS)[number];

export function isStoryPlayable(slug: string): boolean {
    return (PLAYABLE_STORY_SLUGS as readonly string[]).includes(slug);
}
