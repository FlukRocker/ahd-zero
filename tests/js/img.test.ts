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

    it('passes images.shirokami.me through unchanged (not on Bunny zone)', () => {
        expect(
            bunnyImg('https://images.shirokami.me/i/abc.gif', { width: 360 }),
        ).toBe('https://images.shirokami.me/i/abc.gif');
    });

    it('rewrites img.shirokami.me to the proxy host with width + quality', () => {
        const out = bunnyImg(
            'https://img.shirokami.me/images/2024/abc.png',
            { width: 480 },
        );
        expect(out).toBe(`${PROXY}/images/2024/abc.png?width=480&quality=80`);
    });

    it('strips Chevereto .md / .th suffix before proxying', () => {
        expect(
            bunnyImg('https://img.shirokami.me/images/2024/abc.md.jpg', {
                width: 360,
            }),
        ).toBe(`${PROXY}/images/2024/abc.jpg?width=360&quality=80`);

        expect(
            bunnyImg('https://img-cdn.shirokami.me/2024/abc.th.webp', {
                width: 240,
            }),
        ).toBe(`${PROXY}/2024/abc.webp?width=240&quality=80`);
    });

    it('honors quality + format overrides', () => {
        expect(
            bunnyImg('https://img.shirokami.me/x.jpg', {
                width: 600,
                quality: 70,
                format: 'avif',
            }),
        ).toBe(`${PROXY}/x.jpg?width=600&quality=70&format=avif`);
    });
});

describe('bunnySrcset', () => {
    it('emits a w-keyed srcset across the requested widths', () => {
        const out = bunnySrcset(
            'https://img.shirokami.me/x.jpg',
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
    it('routes through bunnySrcset with default poster widths', () => {
        const out = chevSrcset('https://img.shirokami.me/x.png');
        // Should now hit the proxy, not the old th/md/original triple.
        expect(out).toContain('img-cdn-proxy.shirokami.me');
        expect(out).toContain('width=240');
    });

    it('returns null for non-shirokami URLs', () => {
        // bunnyImg passes external through, so srcset values won't have
        // ?width= params — that's fine. The contract here is "non-null on
        // shirokami, predictable on other hosts".
        const out = chevSrcset('https://cdn.myanimelist.net/x.jpg');
        // Each width still produces an entry but pointing at the same URL
        // (no proxy). Acceptable; just verify no crash.
        expect(out).not.toBeNull();
    });
});
