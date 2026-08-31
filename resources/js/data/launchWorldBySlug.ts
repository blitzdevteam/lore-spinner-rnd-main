/** Mirrors `config/stories.php` launch_catalog.titles world names. */
const LAUNCH_WORLD_BY_SLUG: Record<string, string> = {
    'the-wonderful-wizard-of-oz': 'Oz',
    'peter-pan': 'Neverland',
    'the-adventure-of-the-speckled-band': 'Baker Street',
    'a-scandal-in-bohemia': 'Baker Street',
    'the-adventure-of-the-blue-carbuncle': 'Baker Street',
    dracula: 'Dracula',
    frankenstein: 'Frankenstein',
    'dr-jekyll-and-mr-hyde': 'Jekyll and Hyde',
    'the-strange-case-of-dr-jekyll-and-mr-hyde': 'Jekyll and Hyde',
    'the-tell-tale-heart': 'Edgar Allan Poe',
    'pride-and-prejudice': 'Jane Austen',
    emma: 'Jane Austen',
    'treasure-island': 'Treasure Island',
    'around-the-world-in-eighty-days': 'Jules Verne',
    'journey-to-the-center-of-the-earth': 'Jules Verne',
    'the-three-musketeers': 'The Three Musketeers',
    'robin-hood': 'Sherwood Forest',
    'the-invisible-man': 'The Invisible Man',
    'sir-gawain-and-the-green-knight': 'Arthurian Legends',
};

export function resolveLaunchWorldName(slug: string, fallback?: string | null): string | null {
    return LAUNCH_WORLD_BY_SLUG[slug] ?? fallback ?? null;
}
