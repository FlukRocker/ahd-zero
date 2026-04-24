/**
 * Bridge between kurokami Anime Eloquent shape and ahd-v2-design card shape.
 */

export interface AnimeRecord {
    cat_id: number;
    cat_title: string;
    cat_image?: string | null;
    cat_type?: number | null;
    cat_update?: string | null;
    banner_md?: string | null;
    banner_original?: string | null;
    cover_md?: string | null;
    cover_th?: string | null;
    title_english?: string | null;
    title_japanese?: string | null;
    anime_type?: string | null;
    anime_status?: string | null;
    episodes?: number | null;
    episode_list_count?: number;
    [key: string]: unknown;
}

export interface CardItem {
    id: number;
    title: string;
    poster: string;
    landscape: string;
    tag: string | null;
    ep: string;
    kanji: string;
    genre: string;
    rating: string;
    href: string;
    cat_type: number;
    raw: AnimeRecord;
}

const PLACEHOLDER = '/images/placeholder-poster.webp';

function resolvePoster(a: AnimeRecord): string {
    return a.cover_md || a.cat_image || a.cover_th || PLACEHOLDER;
}

function resolveLandscape(a: AnimeRecord): string {
    return a.banner_md || a.banner_original || a.cat_image || PLACEHOLDER;
}

function resolveTag(a: AnimeRecord): string | null {
    if (a.anime_status === 'Currently Airing' || a.anime_status === 'airing')
        return 'AIRING';
    if (a.cat_type === 1) return 'SUB';
    if (a.cat_type === 2) return 'DUB';
    if (a.cat_type === 3) return 'MOVIE';
    return null;
}

function resolveEp(a: AnimeRecord): string {
    const count = a.episode_list_count ?? a.episodes;
    if (count == null) return '';
    if (a.cat_type === 3) return 'Movie';
    return `${count} EP`;
}

function resolveGenre(a: AnimeRecord): string {
    return a.anime_type || '';
}

export function toCardItem(a: AnimeRecord): CardItem {
    return {
        id: a.cat_id,
        title: a.cat_title,
        poster: resolvePoster(a),
        landscape: resolveLandscape(a),
        tag: resolveTag(a),
        ep: resolveEp(a),
        kanji: a.title_japanese || '',
        genre: resolveGenre(a),
        rating: '',
        href: `/anime/${a.cat_id}`,
        cat_type: a.cat_type ?? 1,
        raw: a,
    };
}

export function toCardItems(
    list: AnimeRecord[] | undefined | null,
): CardItem[] {
    if (!list) return [];
    return list.map(toCardItem);
}
