/**
 * JSON-LD schema builders for SEO. Emit via useHead({ script: [...] }).
 */

function absoluteUrl(path: string): string {
    if (typeof window === 'undefined') return path;
    if (/^https?:\/\//i.test(path)) return path;
    return new URL(path, window.location.origin).toString();
}

export function siteJsonLd(appName: string, appUrl: string) {
    return {
        type: 'application/ld+json' as const,
        innerHTML: JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'WebSite',
            name: appName,
            url: appUrl,
            potentialAction: {
                '@type': 'SearchAction',
                target: {
                    '@type': 'EntryPoint',
                    urlTemplate: `${appUrl.replace(/\/$/, '')}/search/results?q={search_term_string}`,
                },
                'query-input': 'required name=search_term_string',
            },
        }),
    };
}

export function organizationJsonLd(appName: string, appUrl: string, logo?: string) {
    return {
        type: 'application/ld+json' as const,
        innerHTML: JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'Organization',
            name: appName,
            url: appUrl,
            ...(logo ? { logo } : {}),
        }),
    };
}

export interface TvSeriesInput {
    name: string;
    alternateName?: string | null;
    description?: string | null;
    image?: string | null;
    url: string;
    numberOfEpisodes?: number | null;
    startDate?: string | null;
    endDate?: string | null;
    genre?: string[];
    actor?: Array<{ name: string }>;
    productionCompany?: Array<{ name: string }>;
}

export function tvSeriesJsonLd(input: TvSeriesInput) {
    const payload: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type': 'TVSeries',
        name: input.name,
        url: absoluteUrl(input.url),
    };
    if (input.alternateName) payload.alternateName = input.alternateName;
    if (input.description) payload.description = input.description;
    if (input.image) payload.image = input.image;
    if (input.numberOfEpisodes != null) payload.numberOfEpisodes = input.numberOfEpisodes;
    if (input.startDate) payload.startDate = input.startDate;
    if (input.endDate) payload.endDate = input.endDate;
    if (input.genre?.length) payload.genre = input.genre;
    if (input.actor?.length) payload.actor = input.actor.map((a) => ({ '@type': 'Person', name: a.name }));
    if (input.productionCompany?.length)
        payload.productionCompany = input.productionCompany.map((c) => ({ '@type': 'Organization', name: c.name }));

    return {
        type: 'application/ld+json' as const,
        innerHTML: JSON.stringify(payload),
    };
}

export interface VideoObjectInput {
    name: string;
    description?: string | null;
    thumbnailUrl?: string | null;
    uploadDate?: string | null;
    contentUrl?: string | null;
    embedUrl?: string | null;
    partOfSeries?: { name: string; url: string };
}

export function videoObjectJsonLd(input: VideoObjectInput) {
    const payload: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type': 'VideoObject',
        name: input.name,
    };
    if (input.description) payload.description = input.description;
    if (input.thumbnailUrl) payload.thumbnailUrl = input.thumbnailUrl;
    if (input.uploadDate) payload.uploadDate = input.uploadDate;
    if (input.contentUrl) payload.contentUrl = input.contentUrl;
    if (input.embedUrl) payload.embedUrl = input.embedUrl;
    if (input.partOfSeries) {
        payload.partOfSeries = {
            '@type': 'TVSeries',
            name: input.partOfSeries.name,
            url: absoluteUrl(input.partOfSeries.url),
        };
    }

    return {
        type: 'application/ld+json' as const,
        innerHTML: JSON.stringify(payload),
    };
}

export interface BreadcrumbCrumb {
    name: string;
    url: string;
}

export function breadcrumbJsonLd(crumbs: BreadcrumbCrumb[]) {
    return {
        type: 'application/ld+json' as const,
        innerHTML: JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'BreadcrumbList',
            itemListElement: crumbs.map((c, i) => ({
                '@type': 'ListItem',
                position: i + 1,
                name: c.name,
                item: absoluteUrl(c.url),
            })),
        }),
    };
}
