import { resolveStoryCover } from '@/data/storyCoverBySlug';

/** Match library cover resolution; null means show the title + logo placeholder. */
export function resolveNewStoryCover(slug: string, cover?: string | null): string | null {
    const resolved = resolveStoryCover(slug, cover);

    return resolved || null;
}
