import { describe, expect, it } from 'vitest';
import {
    breadcrumbJsonLd,
    organizationJsonLd,
    siteJsonLd,
    tvSeriesJsonLd,
    videoObjectJsonLd,
} from '@/lib/schema';

function parse(payload: { innerHTML: string }) {
    return JSON.parse(payload.innerHTML);
}

describe('siteJsonLd', () => {
    it('emits a WebSite with a SearchAction pointing at /search/results', () => {
        const data = parse(siteJsonLd('Anime HD Zero', 'https://ahd.test'));
        expect(data['@type']).toBe('WebSite');
        expect(data.potentialAction['@type']).toBe('SearchAction');
        expect(data.potentialAction.target.urlTemplate).toBe(
            'https://ahd.test/search/results?q={search_term_string}',
        );
    });
});

describe('organizationJsonLd', () => {
    it('omits logo when not provided', () => {
        const data = parse(organizationJsonLd('Anime HD Zero', 'https://ahd.test'));
        expect(data.logo).toBeUndefined();
    });

    it('includes logo when provided', () => {
        const data = parse(organizationJsonLd('Anime HD Zero', 'https://ahd.test', '/logo.svg'));
        expect(data.logo).toBe('/logo.svg');
    });
});

describe('tvSeriesJsonLd', () => {
    it('maps optional fields correctly', () => {
        const data = parse(
            tvSeriesJsonLd({
                name: 'Test',
                url: '/anime/1',
                numberOfEpisodes: 12,
                genre: ['Action', 'Drama'],
                productionCompany: [{ name: 'Studio X' }],
            }),
        );

        expect(data['@type']).toBe('TVSeries');
        expect(data.numberOfEpisodes).toBe(12);
        expect(data.genre).toEqual(['Action', 'Drama']);
        expect(data.productionCompany).toEqual([
            { '@type': 'Organization', name: 'Studio X' },
        ]);
    });
});

describe('videoObjectJsonLd', () => {
    it('links episode to parent series', () => {
        const data = parse(
            videoObjectJsonLd({
                name: 'Episode 1',
                partOfSeries: { name: 'Series', url: '/anime/1' },
            }),
        );

        expect(data['@type']).toBe('VideoObject');
        expect(data.partOfSeries['@type']).toBe('TVSeries');
        expect(data.partOfSeries.name).toBe('Series');
    });
});

describe('breadcrumbJsonLd', () => {
    it('emits sequential positions', () => {
        const data = parse(
            breadcrumbJsonLd([
                { name: 'Home', url: '/' },
                { name: 'Anime', url: '/anime/1' },
            ]),
        );

        expect(data['@type']).toBe('BreadcrumbList');
        expect(data.itemListElement).toHaveLength(2);
        expect(data.itemListElement[0].position).toBe(1);
        expect(data.itemListElement[1].position).toBe(2);
    });
});
