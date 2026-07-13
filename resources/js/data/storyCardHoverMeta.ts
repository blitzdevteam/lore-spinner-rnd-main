/**
 * Canonical 3-word descriptors for story cards (home carousels, mood picks, explore panel).
 */
export const STORY_DESCRIPTOR_THEMES_BY_SLUG: Record<string, [string, string, string]> = {
    'the-wonderful-wizard-of-oz': ['Wonder', 'Courage', 'Home'],
    'the-adventure-of-the-speckled-band': ['Mystery', 'Danger', 'Truth'],
    'the-tell-tale-heart': ['Obsession', 'Guilt', 'Madness'],
    'the-masque-of-the-red-death': ['Fear', 'Denial', 'Death'],
    'alice-in-wonderland': ['Curiosity', 'Nonsense', 'Identity'],
    dracula: ['Hunger', 'Seduction', 'Blood'],
    'dr-jekyll-and-mr-hyde': ['Duality', 'Temptation', 'Control'],
    frankenstein: ['Creation', 'Loneliness', 'Consequence'],
    'jane-eyre': ['Love', 'Secrets', 'Independence'],
    'pride-and-prejudice': ['Wit', 'Reputation', 'Love'],
    'romeo-and-juliet': ['Love', 'Fate', 'Loss'],
    'treasure-island': ['Greed', 'Danger', 'Discovery'],
    leagues: ['Discovery', 'Depth', 'Wonder'],
    'anima-machina': ['Creation', 'Control', 'Destiny'],
    'i-love-lucy-job-switching': ['Comedy', 'Chaos', 'Friendship'],
    nocturne: ['Identity', 'Memory', 'Control'],
    pjs: ['Brotherhood', 'Sacrifice', 'Duty'],
    wasteland: ['Survival', 'Guilt', 'Escape'],
    'the-matrix': ['Rebellion', 'Identity', 'Truth'],
    'momotaro-the-peach-boy': ['Courage', 'Loyalty', 'Destiny'],
};

const SLUG_ALIASES: Record<string, string> = {
    'jekyll-and-hyde': 'dr-jekyll-and-mr-hyde',
    'alices-adventures-in-wonderland': 'alice-in-wonderland',
    '20000-leagues-under-the-sea': 'leagues',
};

export function getStoryDescriptorThemes(slug: string): string[] {
    const key = SLUG_ALIASES[slug] ?? slug;
    const themes = STORY_DESCRIPTOR_THEMES_BY_SLUG[key];
    return themes ? [...themes] : [];
}

/** @deprecated Use getStoryDescriptorThemes — kept for existing imports */
export const STORY_HOVER_META_BY_SLUG: Record<string, { themes: string[] }> = Object.fromEntries(
    Object.entries(STORY_DESCRIPTOR_THEMES_BY_SLUG).map(([slug, themes]) => [
        slug,
        { themes: [...themes] },
    ]),
);
