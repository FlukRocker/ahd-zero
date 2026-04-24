import { describe, expect, it } from 'vitest';
import { toCardItem, toCardItems, type AnimeRecord } from '@/lib/animeCard';

const base: AnimeRecord = {
    cat_id: 42,
    cat_title: 'Lanterns of the Hollow Sky',
};

describe('toCardItem', () => {
    it('maps minimal Anime into a CardItem with a stable href', () => {
        const item = toCardItem(base);
        expect(item.id).toBe(42);
        expect(item.title).toBe('Lanterns of the Hollow Sky');
        expect(item.href).toBe('/anime/42');
    });

    it('prefers cover_md over cat_image for poster', () => {
        const item = toCardItem({ ...base, cat_image: 'a.jpg', cover_md: 'a.md.jpg' });
        expect(item.poster).toBe('a.md.jpg');
    });

    it('prefers banner_md over cat_image for landscape', () => {
        const item = toCardItem({ ...base, cat_image: 'a.jpg', banner_md: 'b.md.jpg' });
        expect(item.landscape).toBe('b.md.jpg');
    });

    it('derives a SUB tag when cat_type = 1', () => {
        expect(toCardItem({ ...base, cat_type: 1 }).tag).toBe('SUB');
    });

    it('derives a MOVIE tag + "Movie" ep label when cat_type = 3', () => {
        const item = toCardItem({ ...base, cat_type: 3, episodes: 1 });
        expect(item.tag).toBe('MOVIE');
        expect(item.ep).toBe('Movie');
    });

    it('marks airing series as AIRING', () => {
        const item = toCardItem({ ...base, anime_status: 'Currently Airing' });
        expect(item.tag).toBe('AIRING');
    });

    it('renders an "N EP" label when an episode count is present', () => {
        expect(toCardItem({ ...base, cat_type: 1, episodes: 12 }).ep).toBe('12 EP');
    });
});

describe('toCardItems', () => {
    it('handles null and undefined lists', () => {
        expect(toCardItems(null)).toEqual([]);
        expect(toCardItems(undefined)).toEqual([]);
    });

    it('maps every record', () => {
        const items = toCardItems([base, { ...base, cat_id: 43 }]);
        expect(items).toHaveLength(2);
        expect(items[1].id).toBe(43);
    });
});
