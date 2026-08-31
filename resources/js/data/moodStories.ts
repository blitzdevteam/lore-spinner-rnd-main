export type MoodId = 'heartfelt' | 'adventurous' | 'mysterious' | 'epic' | 'whimsical';

export const MOOD_IDS = ['heartfelt', 'adventurous', 'mysterious', 'epic', 'whimsical'] as const satisfies readonly MoodId[];

export interface MoodStoryCatalog {
    top: string[];
    secondary: string[];
}

/** Canonical slugs for mood picks (aligned with story covers / library). */
export const MOOD_STORY_CATALOG: Record<MoodId, MoodStoryCatalog> = {
    heartfelt: {
        top: ['pride-and-prejudice', 'jane-eyre', 'romeo-and-juliet', 'pjs'],
        secondary: [
            'wasteland',
            'frankenstein',
            'the-wonderful-wizard-of-oz',
            'pride-and-prejudice',
            'jane-eyre',
            'romeo-and-juliet',
            'pjs',
        ],
    },
    adventurous: {
        top: ['the-wonderful-wizard-of-oz', 'treasure-island', 'anima-machina', 'the-matrix'],
        secondary: [
            'wasteland',
            'alice-in-wonderland',
            'dracula',
            'the-adventure-of-the-speckled-band',
            'the-wonderful-wizard-of-oz',
            'treasure-island',
            'anima-machina',
            'the-matrix',
            'leagues',
        ],
    },
    mysterious: {
        top: [
            'the-adventure-of-the-speckled-band',
            'nocturne',
            'the-tell-tale-heart',
            'dracula',
        ],
        secondary: [
            'dr-jekyll-and-mr-hyde',
            'frankenstein',
            'jane-eyre',
            'anima-machina',
            'the-adventure-of-the-speckled-band',
            'nocturne',
            'the-tell-tale-heart',
            'dracula',
        ],
    },
    epic: {
        top: ['anima-machina', 'the-matrix', 'frankenstein', 'pjs'],
        secondary: [
            'the-wonderful-wizard-of-oz',
            'treasure-island',
            'leagues',
            'wasteland',
            'anima-machina',
            'the-matrix',
            'frankenstein',
            'pjs',
            'dracula',
        ],
    },
    whimsical: {
        top: ['alice-in-wonderland', 'the-wonderful-wizard-of-oz'],
        secondary: [
            'leagues',
            'anima-machina',
            'alice-in-wonderland',
            'the-wonderful-wizard-of-oz',
        ],
    },
};

/** API / legacy slug spellings → canonical mood slug. */
export const MOOD_STORY_SLUG_ALIASES: Record<string, string> = {
    '20000-leagues-under-the-sea': 'leagues',
    'alices-adventures-in-wonderland': 'alice-in-wonderland',
    'jekyll-and-hyde': 'dr-jekyll-and-mr-hyde',
    'the-strange-case-of-dr-jekyll-and-mr-hyde': 'dr-jekyll-and-mr-hyde',
};

export const MOOD_TOP_PICK_SLUGS: Record<MoodId, string[]> = Object.fromEntries(
    MOOD_IDS.map((id) => [id, MOOD_STORY_CATALOG[id].top]),
) as Record<MoodId, string[]>;

export const MOOD_SECONDARY_PICK_SLUGS: Record<MoodId, string[]> = Object.fromEntries(
    MOOD_IDS.map((id) => [id, MOOD_STORY_CATALOG[id].secondary]),
) as Record<MoodId, string[]>;

/** Slugs that belong to each mood (top + secondary), keyed by canonical slug. */
export const STORY_MOODS_BY_SLUG: Record<string, MoodId[]> = (() => {
    const bySlug: Record<string, MoodId[]> = {};

    for (const mood of MOOD_IDS) {
        const slugs = [...MOOD_STORY_CATALOG[mood].top, ...MOOD_STORY_CATALOG[mood].secondary];
        for (const slug of slugs) {
            if (!bySlug[slug]) {
                bySlug[slug] = [];
            }
            if (!bySlug[slug].includes(mood)) {
                bySlug[slug].push(mood);
            }
        }
    }

    return bySlug;
})();

export function canonicalMoodStorySlug(slug: string): string {
    return MOOD_STORY_SLUG_ALIASES[slug] ?? slug;
}

export function getMoodTopPickSlugs(mood: MoodId): string[] {
    return MOOD_TOP_PICK_SLUGS[mood] ?? [];
}

export function getMoodSecondaryPickSlugs(mood: MoodId): string[] {
    return MOOD_SECONDARY_PICK_SLUGS[mood] ?? [];
}

/** Resolve stories in catalog slug order (top picks, secondary, or custom list). */
export function selectStoriesByMoodSlugs<T extends { slug: string }>(stories: T[], slugs: string[]): T[] {
    const bySlug = new Map<string, T>();
    for (const story of stories) {
        const key = canonicalMoodStorySlug(story.slug);
        if (!bySlug.has(key)) {
            bySlug.set(key, story);
        }
    }
    return slugs.map((slug) => bySlug.get(slug)).filter((story): story is T => story != null);
}

/** Full mood story order: top picks first, then secondary-only slugs (preserves secondary order). */
export function getMoodStorySlugs(mood: MoodId): string[] {
    const { top, secondary } = MOOD_STORY_CATALOG[mood];
    const topSet = new Set(top);
    const rest = secondary.filter((slug) => !topSet.has(slug));
    return [...top, ...rest];
}

export function storyMatchesMood(storySlug: string, mood: MoodId): boolean {
    const canonical = canonicalMoodStorySlug(storySlug);
    const moods = STORY_MOODS_BY_SLUG[canonical];
    return moods?.includes(mood) ?? false;
}

/** Prefer real API rows over placeholder mocks, then canonical slug aliases. */
function preferCanonicalStory<T extends { id: number; slug: string }>(current: T, candidate: T): T {
    const currentIsMock = current.id < 0;
    const candidateIsMock = candidate.id < 0;

    if (!currentIsMock && candidateIsMock) {
        return current;
    }

    if (currentIsMock && !candidateIsMock) {
        return candidate;
    }

    const key = canonicalMoodStorySlug(current.slug);
    const currentIsCanonical = current.slug === key;
    const candidateIsCanonical = candidate.slug === key;

    if (candidateIsCanonical && !currentIsCanonical) {
        return candidate;
    }

    if (currentIsCanonical && !candidateIsCanonical) {
        return current;
    }

    return current;
}

/** Collapse alias slugs (e.g. alices-adventures-in-wonderland → alice-in-wonderland). */
export function dedupeStoriesByCanonicalSlug<T extends { id: number; slug: string }>(stories: T[]): T[] {
    const byCanonical = new Map<string, T>();

    for (const story of stories) {
        const key = canonicalMoodStorySlug(story.slug);
        const existing = byCanonical.get(key);
        byCanonical.set(key, existing ? preferCanonicalStory(existing, story) : story);
    }

    return [...byCanonical.values()];
}
