/** Only use a story's assigned cover in the home “New Stories” slider — no slug fallbacks. */
export function resolveNewStoryCover(_slug: string, cover?: string | null): string | null {
    const trimmed = cover?.trim();

    return trimmed || null;
}
