import { resolveStoryCover } from '@/data/storyCoverBySlug';
import type { ComingSoonStoryInterface, StoryInterface } from '@/types';

export interface HomepageFeaturedStory {
    slug: string;
    title: string;
    teaser: string;
}

/** Homepage hero, in carousel order. Ultrawide art exists for Speckled Band only. */
export const HOMEPAGE_CAROUSEL_SLUGS = [
    'the-adventure-of-the-speckled-band',
    'dracula',
    'pride-and-prejudice',
    'robin-hood',
] as const;

/** Homepage New Stories row, in display order. */
export const HOMEPAGE_NEW_STORIES: HomepageFeaturedStory[] = [
    {
        slug: 'peter-pan',
        title: 'Peter and Wendy',
        teaser: 'Fly with Peter, protect the Lost Boys, and decide what growing up really costs.',
    },
    {
        slug: 'frankenstein',
        title: 'Frankenstein',
        teaser: 'Lightning, ambition, and a creature who refuses to stay buried in the dark.',
    },
    {
        slug: 'emma',
        title: 'Emma',
        teaser: 'Clever, confident, and convinced she sees what others miss — matchmake, misread, and repair the social web of Highbury.',
    },
    {
        slug: 'the-invisible-man',
        title: 'The Invisible Man',
        teaser: 'Invisibility, science gone wrong, and the terror of being watched by no one you can find.',
    },
    {
        slug: 'the-three-musketeers',
        title: 'The Three Musketeers',
        teaser: 'Swords, loyalty, and intrigue in the court of the Sun King.',
    },
    {
        slug: 'sir-gawain-and-the-green-knight',
        title: 'Sir Gawain and the Green Knight',
        teaser: 'Green knights, oaths, and the year that will define a hero.',
    },
    {
        slug: 'around-the-world-in-eighty-days',
        title: 'Around the World in Eighty Days',
        teaser: 'Race the clock around the globe before the wager runs out.',
    },
    {
        slug: 'the-tell-tale-heart',
        title: 'The Tell-Tale Heart',
        teaser: "As guilt begins to twist reality around him, a man struggles to silence the terrifying sound he cannot escape: the beating of a dead man's heart.",
    },
];

/** Homepage Coming Soon row, in display order. */
export const HOMEPAGE_COMING_SOON: HomepageFeaturedStory[] = [
    {
        slug: 'the-marvelous-land-of-oz',
        title: 'The Marvelous Land of Oz',
        teaser: 'Oz is in revolt, armies march, and impossible companions join the cause.',
    },
    {
        slug: 'sense-and-sensibility',
        title: 'Sense and Sensibility',
        teaser: 'Two sisters, two fortunes, and the difference between what the heart wants and what the world allows.',
    },
    {
        slug: 'the-masque-of-the-red-death',
        title: 'The Masque of the Red Death',
        teaser: 'Behind locked gates and glittering masks, a night of celebration slowly transforms into a nightmare no one can escape.',
    },
    {
        slug: 'the-quest-of-the-holy-grail',
        title: 'The Quest of the Holy Grail',
        teaser: 'A quest for the Grail, where faith, courage, and the company you keep decide who is worthy.',
    },
    {
        slug: 'theseus-and-the-minotaur',
        title: 'Theseus and the Minotaur',
        teaser: 'Descend into the labyrinth where heroism, sacrifice, and fate collide.',
    },
    {
        slug: '20000-leagues-under-the-sea',
        title: '20,000 Leagues Under the Sea',
        teaser: 'Step aboard the Nautilus, where each choice pulls you deeper into beauty, danger, and the mystery of Captain Nemo.',
    },
];

export function findStoryBySlug(stories: StoryInterface[], slug: string): StoryInterface | undefined {
    return stories.find((story) => story.slug === slug);
}

export function resolveHomepageComingSoon(records: ComingSoonStoryInterface[]): ComingSoonStoryInterface[] {
    return HOMEPAGE_COMING_SOON.map((story, index) => {
        const record = records.find((item) => item.slug === story.slug);
        const cover = record?.cover?.trim() || resolveStoryCover(story.slug) || '';

        return {
            id: record?.id ?? index + 1,
            title: story.title,
            slug: story.slug,
            section: 'coming-soon',
            section_label: 'Coming Soon',
            teaser: record?.teaser?.trim() || story.teaser,
            category: record?.category ?? null,
            rating: record?.rating ?? null,
            author: record?.author ?? null,
            series_label: record?.series_label ?? null,
            image_position: record?.image_position ?? null,
            position: index,
            is_active: true,
            cover,
            updated_at: record?.updated_at ?? null,
        };
    });
}
