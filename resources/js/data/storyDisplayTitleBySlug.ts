/** Published titles. Mirrors `config/stories.php` launch_catalog.titles. */
const DISPLAY_TITLES_BY_SLUG: Record<string, string> = {
    'the-wonderful-wizard-of-oz': 'The Wonderful Wizard of Oz',
    'peter-pan': 'Peter Pan',
    'the-adventure-of-the-speckled-band': 'Sherlock Holmes: The Speckled Band',
    'a-scandal-in-bohemia': 'Sherlock Holmes: A Scandal in Bohemia',
    'the-adventure-of-the-blue-carbuncle': 'Sherlock Holmes: The Blue Carbuncle',
    dracula: 'Dracula',
    frankenstein: 'Frankenstein',
    'dr-jekyll-and-mr-hyde': 'Strange Case of Dr. Jekyll and Mr. Hyde',
    'the-tell-tale-heart': 'The Tell-Tale Heart',
    'pride-and-prejudice': 'Pride and Prejudice',
    emma: 'Emma',
    'sense-and-sensibility': 'Sense and Sensibility',
    'treasure-island': 'Treasure Island',
    'around-the-world-in-eighty-days': 'Around the World in Eighty Days',
    'journey-to-the-center-of-the-earth': 'Journey to the Center of the Earth',
    'the-three-musketeers': 'The Three Musketeers',
    'robin-hood': 'Robin Hood',
    'the-invisible-man': 'The Invisible Man',
    'sir-gawain-and-the-green-knight': 'Sir Gawain and the Green Knight',
};

export function resolveStoryDisplayTitle(slug: string, title: string): string {
    return DISPLAY_TITLES_BY_SLUG[slug] ?? title;
}
