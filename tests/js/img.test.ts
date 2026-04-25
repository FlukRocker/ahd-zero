import { describe, expect, it } from 'vitest';
import { bunnyImg, bunnySrcset, chevSrcset } from '@/lib/img';

const PROXY = 'https://img-cdn-proxy.shirokami.me';

describe('bunnyImg', () => {
    it('returns null for missing input', () => {
        expect(bunnyImg(null)).toBeNull();
        expect(bunnyImg(undefined)).toBeNull();
        expect(bunnyImg('')).toBeNull();
    });

    it('passes external origins through unchanged', () => {
        expect(
            bunnyImg('https://cdn.myanimelist.net/images/anime/1/abc.jpg', {
                width: 360,
            }),
        ).toBe('https://cdn.myanimelist.net/images/anime/1/abc.jpg');
    });

    it('passes img.shirokami.me through unchanged (not on proxy zone)', () => {
        expect(
            bunnyImg('https://img.shirokami.me/images/2024/abc.png', {
                width: 360,
            }),
        ).toBe('https://img.shirokami.me/images/2024/abc.png');
    });

    it('passes img-cdn.shirokami.me through unchanged (not on proxy zone)', () => {
        expect(
            bunnyImg('https://img-cdn.shirokami.me/2024/abc.webp', {
                width: 240,
            }),
        ).toBe('https://img-cdn.shirokami.me/2024/abc.webp');
    });

    it('passes images.shirokami.me through unchanged (different zone)', () => {
        expect(
            bunnyImg('https://images.shirokami.me/i/abc.gif', { width: 360 }),
        ).toBe('https://images.shirokami.me/i/abc.gif');
    });

    it('appends width + quality on URLs already on the proxy host', () => {
        expect(
            bunnyImg('https://img-cdn-proxy.shirokami.me/2024/abc.png', {
                width: 480,
            }),
        ).toBe(`${PROXY}/2024/abc.png?width=480&quality=80`);
    });

    it('strips Chevereto .md / .th suffix on proxy URLs', () => {
        expect(
            bunnyImg(
                'https://img-cdn-proxy.shirokami.me/2024/abc.md.jpg',
                { width: 360 },
            ),
        ).toBe(`${PROXY}/2024/abc.jpg?width=360&quality=80`);

        expect(
            bunnyImg(
                'https://img-cdn-proxy.shirokami.me/2024/abc.th.webp',
                { width: 240 },
            ),
        ).toBe(`${PROXY}/2024/abc.webp?width=240&quality=80`);
    });

    it('honors quality + format overrides', () => {
        expect(
            bunnyImg('https://img-cdn-proxy.shirokami.me/x.jpg', {
                width: 600,
                quality: 70,
                format: 'avif',
            }),
        ).toBe(`${PROXY}/x.jpg?width=600&quality=70&format=avif`);
    });

    it('preserves any pre-existing query params on the proxy URL', () => {
        const out = bunnyImg(
            'https://img-cdn-proxy.shirokami.me/x.jpg?token=abc',
            { width: 480 },
        );
        // Order of params not asserted — both should be present.
        expect(out).toContain('width=480');
        expect(out).toContain('quality=80');
        expect(out).toContain('token=abc');
    });
});

describe('bunnySrcset', () => {
    it('emits a w-keyed srcset across the requested widths', () => {
        const out = bunnySrcset(
            'https://img-cdn-proxy.shirokami.me/x.jpg',
            [240, 480],
        );
        expect(out).toBe(
            `${PROXY}/x.jpg?width=240&quality=80 240w, ${PROXY}/x.jpg?width=480&quality=80 480w`,
        );
    });

    it('returns null for missing input', () => {
        expect(bunnySrcset(null, [200])).toBeNull();
    });
});

describe('chevSrcset (back-compat alias)', () => {
    it('routes through bunnySrcset for proxy URLs', () => {
        const out = chevSrcset('https://img-cdn-proxy.shirokami.me/x.png');
        expect(out).toContain('width=240');
    });
});
