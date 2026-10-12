/** Query parameters that keep a page canonical; any other parameter is a filter or search combination. */
export const INDEXABLE_PARAMS = ['page'];

export const DEFAULT_SOCIAL_IMAGE = '/images/brand/og-default.jpg';

export function absoluteUrl(appUrl: string | undefined, path: string): string {
    return /^https?:\/\//.test(path) ? path : `${appUrl ?? ''}${path.startsWith('/') ? path : `/${path}`}`;
}

/** Canonical path for an Inertia URL: no filters, and the page number only beyond the first page. */
export function canonicalPath(url: string): { path: string; filtered: boolean } {
    const parsed = new URL(url, 'http://desgarrados.local');
    const params = [...parsed.searchParams.keys()];
    const page = Number(parsed.searchParams.get('page') ?? '1');

    return {
        path: parsed.pathname + (Number.isInteger(page) && page > 1 ? `?page=${page}` : ''),
        filtered: params.some(param => !INDEXABLE_PARAMS.includes(param)),
    };
}

/** Plain-text summary for meta descriptions (max 160 characters). */
export function metaDescription(text: string | null | undefined): string | null {
    const plain = (text ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').replace(/ ([.,;:!?])/g, '$1').trim();

    return plain ? (plain.length > 160 ? `${plain.slice(0, 157).trimEnd()}…` : plain) : null;
}
