import { describe, expect, it } from 'vitest';
import { chevSrcset } from '@/lib/img';

describe('chevSrcset', () => {
    it('returns null for missing input', () => {
        expect(chevSrcset(null)).toBeNull();
        expect(chevSrcset(undefined)).toBeNull();
        expect(chevSrcset('')).toBeNull();
    });

    it('returns null for non-shirokami URLs', () => {
        expect(chevSrcset('https://cdn.myanimelist.net/anime/1/abc.jpg')).toBeNull();
    });

    it('builds a 3-width srcset for Chevereto URLs', () => {
        const srcset = chevSrcset('https://img.shirokami.me/images/2024/abc.webp');

        expect(srcset).toBe(
            'https://img.shirokami.me/images/2024/abc.th.webp 300w, https://img.shirokami.me/images/2024/abc.md.webp 720w, https://img.shirokami.me/images/2024/abc.webp 1600w',
        );
    });

    it('strips an existing .md suffix before rebuilding srcset', () => {
        const srcset = chevSrcset('https://img.shirokami.me/images/2024/abc.md.jpg');

        expect(srcset).toBe(
            'https://img.shirokami.me/images/2024/abc.th.jpg 300w, https://img.shirokami.me/images/2024/abc.md.jpg 720w, https://img.shirokami.me/images/2024/abc.jpg 1600w',
        );
    });

    it('strips an existing .th suffix before rebuilding srcset', () => {
        const srcset = chevSrcset('https://img.shirokami.me/images/2024/abc.th.png');

        expect(srcset).toBe(
            'https://img.shirokami.me/images/2024/abc.th.png 300w, https://img.shirokami.me/images/2024/abc.md.png 720w, https://img.shirokami.me/images/2024/abc.png 1600w',
        );
    });
});
