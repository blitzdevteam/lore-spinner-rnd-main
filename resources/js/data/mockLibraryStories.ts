import { StoryInterface } from '@/types';
import { StoryRatingEnum, StoryStatusEnum } from '@/types/enum';

const draft = {
    status: { value: StoryStatusEnum.DRAFT, label: 'Draft' } as const,
    rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone' } as const,
    opening: null,
    published_at: null,
    cover: '',
    banner: '',
};

/**
 * Demo stories for Library / mood UI when not yet returned from the API.
 * Negative ids avoid clashes with DB rows.
 */
export const MOCK_LIBRARY_STORIES: StoryInterface[] = [
    {
        id: -1,
        title: 'Anima Machina',
        slug: 'anima-machina',
        teaser:
            'When a sentient AI threatens to overwrite all human grief with synthetic perfection, a haunted memory diver races against the clock to stop the digital reset.',
        updated_at: '2025-04-01T00:00:00.000Z',
        ...draft,
    },
    {
        id: -2,
        title: 'Jane Eyre',
        slug: 'jane-eyre',
        teaser:
            'A young orphan enters a dark and mysterious estate where buried secrets, dangerous love, and the search for belonging may change the course of her life forever.',
        updated_at: '2025-03-15T00:00:00.000Z',
        ...draft,
    },
    {
        id: -3,
        title: 'Pride and Prejudice',
        slug: 'pride-and-prejudice',
        teaser:
            'In a world ruled by reputation, romance, and social expectation, Elizabeth Bennet must navigate pride, misunderstanding, and the dangerous possibility of falling in love.',
        updated_at: '2025-03-10T00:00:00.000Z',
        ...draft,
    },
    {
        id: -4,
        title: 'Romeo & Juliet',
        slug: 'romeo-and-juliet',
        teaser:
            'A masked room. A borrowed name. A city holding its breath. Somewhere in the dark of Verona, love discovers it has enemies.',
        updated_at: '2025-03-08T00:00:00.000Z',
        ...draft,
    },
    {
        id: -5,
        title: "PJ's",
        slug: 'pjs',
        teaser:
            "A team of elite Air Force PJs discover that the hardest battlefield may be the one where there's no enemy to shoot, only lives to save and ghosts to outrun.",
        updated_at: '2025-03-05T00:00:00.000Z',
        ...draft,
    },
    {
        id: -6,
        title: 'Wasteland',
        slug: 'wasteland',
        teaser:
            "Abandoned in a desert built from humanity's castoffs, an engineer must decide whether to escape or help the people that the world chose to forget.",
        updated_at: '2025-03-04T00:00:00.000Z',
        ...draft,
    },
    {
        id: -7,
        title: 'Frankenstein',
        slug: 'frankenstein',
        teaser:
            'Step inside a world where creation, rejection, and consequence follow you like a shadow.',
        updated_at: '2025-03-03T00:00:00.000Z',
        ...draft,
    },
    {
        id: -8,
        title: 'The Wonderful Wizard of Oz',
        slug: 'the-wonderful-wizard-of-oz',
        teaser:
            'A storm carries you into the magical land of Oz, where witches whisper, lions tremble, and every step down the Yellow Brick Road changes who you are becoming.',
        updated_at: '2025-03-02T00:00:00.000Z',
        ...draft,
    },
    {
        id: -9,
        title: 'Treasure Island',
        slug: 'treasure-island',
        teaser:
            'Every choice at sea carries a price: who to trust, when to run, and what kind of courage survives betrayal. The map is only the beginning.',
        updated_at: '2025-03-01T00:00:00.000Z',
        ...draft,
    },
    {
        id: -10,
        title: '20,000 Leagues Under the Sea',
        slug: 'leagues',
        teaser:
            'Step aboard the Nautilus, where each choice pulls you deeper into beauty, danger, and the mystery of Captain Nemo.',
        updated_at: '2025-02-28T00:00:00.000Z',
        ...draft,
    },
    {
        id: -11,
        title: "Alice's Adventures in Wonderland",
        slug: 'alice-in-wonderland',
        teaser:
            'Follow Alice into a curious world of talking cats, mad tea parties, and impossible adventures where every path leads somewhere unexpected.',
        updated_at: '2025-02-27T00:00:00.000Z',
        ...draft,
    },
    {
        id: -12,
        title: 'Dracula',
        slug: 'dracula',
        teaser:
            'Step into a world where love, faith, and reason are tested by a hunger older than death.',
        updated_at: '2025-02-26T00:00:00.000Z',
        ...draft,
    },
    {
        id: -13,
        title: 'Sherlock Holmes in The Speckled Band',
        slug: 'the-adventure-of-the-speckled-band',
        teaser:
            'A young woman fears she will suffer the same fate as her sister, forcing Sherlock Holmes to confront a mystery hidden behind locked doors and deadly secrets.',
        updated_at: '2025-02-25T00:00:00.000Z',
        ...draft,
    },
    {
        id: -14,
        title: 'Nocturne',
        slug: 'nocturne',
        teaser:
            'Beyond the rain-soaked glass walls of Nocturne, Akira finds herself trapped inside a system where identities are rewritten and nothing is quite as voluntary as it seems.',
        updated_at: '2025-02-24T00:00:00.000Z',
        ...draft,
    },
    {
        id: -15,
        title: 'The Tell-Tale Heart',
        slug: 'the-tell-tale-heart',
        teaser:
            "As guilt begins to twist reality around him, a man struggles to silence the terrifying sound he cannot escape: the beating of a dead man's heart.",
        updated_at: '2025-02-23T00:00:00.000Z',
        ...draft,
    },
    {
        id: -16,
        title: 'Dr. Jekyll & Mr. Hyde',
        slug: 'dr-jekyll-and-mr-hyde',
        teaser:
            "Beneath the fog-covered streets of Victorian London, a terrifying secret grows inside Dr. Jekyll's laboratory, threatening to consume everyone around him.",
        updated_at: '2025-02-22T00:00:00.000Z',
        ...draft,
    },
];
