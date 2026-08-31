/** Mirrors `config/stories.php` launch_catalog.titles world names. */
const LAUNCH_WORLD_BY_SLUG: Record<string, string> = {
    'the-wonderful-wizard-of-oz': 'Land of Oz',
    'peter-pan': 'Neverland',
    'the-adventure-of-the-speckled-band': 'Baker Street',
    'a-scandal-in-bohemia': 'Baker Street',
    'the-adventure-of-the-blue-carbuncle': 'Baker Street',
    dracula: "Dracula's England",
    frankenstein: "Frankenstein's World",
    'dr-jekyll-and-mr-hyde': "Hyde's London",
    'the-tell-tale-heart': "Poe's Gothic",
    'pride-and-prejudice': 'Regency England',
    emma: 'Regency England',
    'treasure-island': 'Treasure Island',
    'around-the-world-in-eighty-days': 'Extraordinary Voyages',
    'journey-to-the-center-of-the-earth': 'Extraordinary Voyages',
    'the-three-musketeers': 'Musketeer France',
    'robin-hood': 'Sherwood Forest',
    'the-invisible-man': 'The Unseen World',
    'sir-gawain-and-the-green-knight': 'Arthurian Britain',
};

export function resolveLaunchWorldName(slug: string, fallback?: string | null): string | null {
    return LAUNCH_WORLD_BY_SLUG[slug] ?? fallback ?? null;
}
