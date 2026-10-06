import defaultLibraryBanner from '@/assets/banner-stories.webp';
import adventurousBanner from '@/assets/mood/AdventurousBanner.webp';
import epicBanner from '@/assets/mood/EpicBanner.webp';
import heartfeltBanner from '@/assets/mood/HeartfeltBanner.webp';
import mysteriousBanner from '@/assets/mood/MysteriousBanner.webp';
import whimsicalBanner from '@/assets/mood/WhimsicalBanner.webp';

import { MOOD_IDS, type MoodId } from '@/data/moodStories';

export type { MoodId };

export interface MoodBannerConfig {
    mood: MoodId;
    banner: string;
    title: string;
    subtitle: string;
    label: string;
    /** Optional focal point for object-fit: cover cropping. */
    objectPosition?: string;
}

export interface ResolvedMoodBanner {
    mood: MoodId | null;
    banner: string;
    title: string;
    subtitle: string;
    label: string;
    objectPosition: string;
    isFallback: boolean;
}

export { MOOD_IDS };

export const DEFAULT_LIBRARY_BANNER = defaultLibraryBanner;

export const DEFAULT_LIBRARY_HERO = {
    banner: defaultLibraryBanner,
    title: 'Story Library',
    subtitle: 'Discover branching worlds waiting to be explored',
    label: 'Library',
    objectPosition: 'center',
} as const;

/** Per-mood hero configuration — add new moods here. */
export const MOOD_BANNER_CONFIGS: Record<MoodId, MoodBannerConfig> = {
    heartfelt: {
        mood: 'heartfelt',
        banner: heartfeltBanner,
        label: 'Heartfelt',
        title: 'Heartfelt Stories',
        subtitle: 'Love. Loss. Hope.',
        objectPosition: 'center 35%',
    },
    adventurous: {
        mood: 'adventurous',
        banner: adventurousBanner,
        label: 'Adventurous',
        title: 'Adventurous Stories',
        subtitle: 'Discovery. Danger. Wonder.',
        objectPosition: 'center 40%',
    },
    mysterious: {
        mood: 'mysterious',
        banner: mysteriousBanner,
        label: 'Mysterious',
        title: 'Mysterious Stories',
        subtitle: 'Secrets. Questions. Obsession.',
        objectPosition: 'center 45%',
    },
    epic: {
        mood: 'epic',
        banner: epicBanner,
        label: 'Epic',
        title: 'Epic Stories',
        subtitle: 'Legends. Conflict. Destiny.',
        objectPosition: 'center 50%',
    },
    whimsical: {
        mood: 'whimsical',
        banner: whimsicalBanner,
        label: 'Whimsical',
        title: 'Whimsical Stories',
        subtitle: 'Curiosity. Imagination. Delight.',
        objectPosition: 'center 40%',
    },
};

/**
 * Aliases from URL params, DB labels, or human-readable names → canonical mood id.
 * Unknown aliases resolve to null and use the library fallback banner.
 */
const MOOD_ALIASES: Record<string, MoodId> = {
    heart_felt: 'heartfelt',
    emotional: 'heartfelt',
    romance: 'heartfelt',
    romantic: 'heartfelt',
    drama: 'heartfelt',
    dramatic: 'heartfelt',
    inspirational: 'heartfelt',

    adventure: 'adventurous',
    explorer: 'adventurous',
    fantasy_adventure: 'adventurous',
    historical_adventure: 'adventurous',

    mystery: 'mysterious',
    noir: 'mysterious',
    supernatural: 'mysterious',
    supernatural_thriller: 'mysterious',
    thriller: 'mysterious',

    heroic: 'epic',
    legendary: 'epic',
    fantasy: 'epic',
    dark_fantasy: 'epic',
    military_drama: 'epic',

    playful: 'whimsical',
    wonder: 'whimsical',
    surreal: 'whimsical',
};

export { STORY_MOODS_BY_SLUG, storyMatchesMood } from '@/data/moodStories';

/** Collapse common genre spellings to a stable snake_case key before mood lookup. */
const MOOD_KEY_ALIASES: Record<string, string> = {
    science_fiction: 'sci_fi',
    sci_fi: 'sci_fi',
    scifi: 'sci_fi',
    sci_fi_fiction: 'sci_fi',
    action_thriller: 'action_thriller',
    techno_thriller: 'techno_thriller',
    dark_fantasy: 'dark_fantasy',
    military_drama: 'military_drama',
    fantasy_adventure: 'fantasy_adventure',
    historical_adventure: 'historical_adventure',
    supernatural_thriller: 'supernatural_thriller',
};

/** Normalize free-form mood strings to a canonical key (`sci_fi`, `heartfelt`, …). */
export function normalizeMoodKey(input: string): string {
    const key = input
        .trim()
        .toLowerCase()
        .replace(/&/g, ' and ')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_+/g, '_');

    return MOOD_KEY_ALIASES[key] ?? key;
}

/** Map a mood string to a known MoodId, or null when no banner exists. */
export function normalizeMood(input: string | null | undefined): MoodId | null {
    if (!input?.trim()) return null;

    const key = normalizeMoodKey(input);

    if (key in MOOD_BANNER_CONFIGS) {
        return key as MoodId;
    }

    if (key in MOOD_ALIASES) {
        return MOOD_ALIASES[key]!;
    }

    return null;
}

export function getMoodBannerConfig(mood: string | null | undefined): ResolvedMoodBanner {
    const normalized = normalizeMood(mood);

    if (normalized) {
        const config = MOOD_BANNER_CONFIGS[normalized];
        return {
            mood: normalized,
            banner: config.banner,
            title: config.title,
            subtitle: config.subtitle,
            label: config.label,
            objectPosition: config.objectPosition ?? 'center',
            isFallback: false,
        };
    }

    return {
        mood: null,
        banner: DEFAULT_LIBRARY_HERO.banner,
        title: DEFAULT_LIBRARY_HERO.title,
        subtitle: DEFAULT_LIBRARY_HERO.subtitle,
        label: DEFAULT_LIBRARY_HERO.label,
        objectPosition: DEFAULT_LIBRARY_HERO.objectPosition,
        isFallback: true,
    };
}

export function getMoodNavLinks(storiesIndexUrl: string): { title: string; slug: MoodId; href: string }[] {
    return MOOD_IDS.map((id) => ({
        title: MOOD_BANNER_CONFIGS[id].label,
        slug: id,
        href: `${storiesIndexUrl}?mood=${id}`,
    }));
}
