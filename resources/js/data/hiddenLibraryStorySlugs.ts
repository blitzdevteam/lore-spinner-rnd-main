/** Published stories excluded from library grids and public story counts. Mirrors `config/stories.php` → `library_hidden_slugs`. */
export const HIDDEN_LIBRARY_STORY_SLUGS = [
    'driftheart',
    'the-snow-queen',
    'momotaro-the-peach-boy',
    'the-matrix',
    'i-love-lucy-job-switching',
    'dream-police',
    'the-unwritten-ones',
] as const;

export function isHiddenLibraryStory(slug: string): boolean {
    return (HIDDEN_LIBRARY_STORY_SLUGS as readonly string[]).includes(slug);
}

export function filterVisibleLibraryStories<T extends { slug: string }>(stories: T[]): T[] {
    return stories.filter((story) => !isHiddenLibraryStory(story.slug));
}
