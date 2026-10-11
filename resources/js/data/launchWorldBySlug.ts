/** Mirrors `config/stories.php` launch_catalog.titles world names. */
const LAUNCH_WORLD_BY_SLUG: Record<string, string> = {
    'the-wonderful-wizard-of-oz': 'World of Oz',
    'the-adventure-of-the-speckled-band': 'Baker Street',
    'a-scandal-in-bohemia': 'Baker Street',
    'the-adventure-of-the-blue-carbuncle': 'Baker Street',
    dracula: 'Gothic Horrors',
    frankenstein: 'Gothic Horrors',
    'dr-jekyll-and-mr-hyde': 'Gothic Horrors',
    'the-strange-case-of-dr-jekyll-and-mr-hyde': 'Gothic Horrors',
    'the-tell-tale-heart': 'Gothic Horrors',
    'pride-and-prejudice': 'Jane Austen',
    emma: 'Jane Austen',
    'sense-and-sensibility': 'Jane Austen',
    'treasure-island': 'High Adventure',
    'around-the-world-in-eighty-days': 'Extraordinary Voyages',
    'journey-to-the-center-of-the-earth': 'Extraordinary Voyages',
    'the-three-musketeers': 'High Adventure',
    'robin-hood': 'High Adventure',
    'sir-gawain-and-the-green-knight': 'Camelot',
};

export function resolveLaunchWorldName(slug: string, fallback?: string | null): string | null {
    return LAUNCH_WORLD_BY_SLUG[slug] ?? fallback ?? null;
}
