const FALLBACK_APP_NAME = 'LoreSpinner';

type PageLike = {
    props?: Record<string, unknown>;
};

export function appNameFromPage(page?: PageLike): string {
    const shared = page?.props?.appName;

    if (typeof shared === 'string' && shared.trim() !== '') {
        return shared;
    }

    return FALLBACK_APP_NAME;
}
