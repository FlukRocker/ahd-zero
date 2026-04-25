import { describe, expect, it } from 'vitest';
import { bunnyImg, bunnySrcset, chevSrcset } from '@/lib/img';

const PROXY = 'https://img-cdn-proxy.shirokami.me';

describe('bunnyImg', () => {
    it('returns null for missing input', () => {
        expect(bunnyImg(null)).toBeNull();
        expect(bunnyImg(undefined)).toBeNull();
        expect(bunnyImg('')).toBeNull();
    });

    it('rewrites img-cdn.shirokami.me to the Bunny proxy with width + quality', () => {
        const out = bunnyImg(
            'https://img-cdn.shirokami.me/2024/abc.png',
            { width: 480 },
        );
        expect(out).toBe(`${PROXY}/2024/abc.png?width=480&quality=80`);
    });

    it('strips Chevereto .md / .th suffix before proxying', () => {
        expect(
            bunnyImg('https://img-cdn.shirokami.me/2024/abc.md.jpg', {
                width: 360,
            }),
        ).toBe(`${PROXY}/2024/abc.jpg?width=360&quality=80`);

        expect(
            bunnyImg('https://img-cdn.shirokami.me/2024/abc.th.webp', {
                width: 240,
            }),
        ).toBe(`${PROXY}/2024/abc.webp?width=240&quality=80`);
    });

    it('honors quality + format overrides', () => {
        expect(
            bunnyImg('https://img-cdn.shirokami.me/x.jpg', {
                width: 600,
                quality: 70,
                format: 'avif',
            }),
        ).toBe(`${PROXY}/x.jpg?width=600&quality=70&format=avif`);
    });

    it('passes external origins through unchanged', () => {
        expect(
            bunnyImg('https://cdn.myanimelist.net/images/anime/1/abc.jpg', {
                width: 360,
            }),
        ).toBe('https://cdn.myanimelist.net/images/anime/1/abc.jpg');
    });

    it('passes img.shirokami.me through unchanged (different origin)', () => {
        expect(
            bunnyImg('https://img.shirokami.me/images/2024/abc.png', {
                width: 360,
            }),
        ).toBe('https://img.shirokami.me/images/2024/abc.png');
    });

    it('passes images.shirokami.me through unchanged (different zone)', () => {
        expect(
            bunnyImg('https://images.shirokami.me/i/abc.gif', { width: 360 }),
        ).toBe('https://images.shirokami.me/i/abc.gif');
    });

    it('passes URLs already on the proxy through unchanged', () => {
        // Don't double-rewrite — caller is presumed to have already invoked us.
        expect(
            bunnyImg(`${PROXY}/2024/abc.png?width=360`, { width: 480 }),
        ).toBe(`${PROXY}/2024/abc.png?width=360`);
    });
});

describe('bunnySrcset', () => {
    it('emits a w-keyed srcset across the requested widths', () => {
        const out = bunnySrcset(
            'https://img-cdn.shirokami.me/x.jpg',
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
    it('routes through bunnySrcset for img-cdn URLs', () => {
        const out = chevSrcset('https://img-cdn.shirokami.me/x.png');
        expect(out).toContain('img-cdn-proxy.shirokami.me');
        expect(out).toContain('width=240');
    });
});
