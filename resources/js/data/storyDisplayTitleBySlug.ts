/** Marketing / detail-page titles. Mirrors `config/stories.php` launch_catalog.titles. */
const DISPLAY_TITLES_BY_SLUG: Record<string, string> = {
    'the-wonderful-wizard-of-oz': 'The Wonderful Wizard of Oz: The Road Home',
    'peter-pan': "Peter Pan: Wendy's Neverland",
    'the-adventure-of-the-speckled-band': 'Sherlock Holmes: The Speckled Band',
    'a-scandal-in-bohemia': 'Sherlock Holmes: A Scandal in Bohemia',
    'the-adventure-of-the-blue-carbuncle': 'Sherlock Holmes: The Blue Carbuncle',
    dracula: 'Dracula: The First Invitation',
    frankenstein: 'Frankenstein: The Creature Awakens',
    'dr-jekyll-and-mr-hyde': 'Dr. Jekyll and Mr. Hyde: The Other Self',
    'the-tell-tale-heart': 'The Tell-Tale Heart: Beneath the Floor',
    'pride-and-prejudice': 'Pride and Prejudice: First Impressions',
    emma: 'Emma: Hearts Misread',
    'treasure-island': 'Treasure Island: The Map and the Mutiny',
    'around-the-world-in-eighty-days': 'Around the World in Eighty Days: Race the Clock',
    'journey-to-the-center-of-the-earth': 'Journey to the Center of the Earth: Descent',
    'the-three-musketeers': 'The Three Musketeers: One for All',
    'robin-hood': 'Robin Hood: Outlaw of Sherwood',
    'the-invisible-man': 'The Invisible Man: Unseen',
    'sir-gawain-and-the-green-knight': 'Sir Gawain and the Green Knight: The Year of Honor',
};

export function resolveStoryDisplayTitle(slug: string, title: string): string {
    return DISPLAY_TITLES_BY_SLUG[slug] ?? title;
}
