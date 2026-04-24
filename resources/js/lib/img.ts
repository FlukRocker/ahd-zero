/**
 * Responsive image helpers. Works with Chevereto-style variants (md/th suffix).
 */

function withSuffix(url: string, suffix: 'md' | 'th'): string | null {
    const dot = url.lastIndexOf('.');
    if (dot === -1) return null;
    return `${url.slice(0, dot)}.${suffix}${url.slice(dot)}`;
}

/**
 * Strip an existing Chevereto variant suffix (.md / .th) so a fresh srcset can
 * be built from the canonical original URL. Idempotent — no-op if no suffix.
 */
function stripVariant(url: string): string {
    return url.replace(/\.(md|th)(\.[a-z0-9]+)$/i, '$2');
}

/**
 * Build a srcset string from a Chevereto URL (variant or original).
 * Returns null if the URL isn't a shirokami Chevereto image.
 */
export function chevSrcset(url: string | null | undefined): string | null {
    if (!url || !url.includes('shirokami.me')) return null;
    const original = stripVariant(url);
    const md = withSuffix(original, 'md');
    const th = withSuffix(original, 'th');
    if (!md || !th) return null;
    // Widths roughly match Chevereto defaults: th≈300w, md≈720w, original≈1600w
    return `${th} 300w, ${md} 720w, ${original} 1600w`;
}

export interface ImgOptions {
    loading?: 'lazy' | 'eager';
    decoding?: 'async' | 'auto' | 'sync';
    sizes?: string;
    width?: number | string;
    height?: number | string;
}

export const POSTER_SIZES =
    '(max-width: 600px) 45vw, (max-width: 1200px) 25vw, 200px';
export const LANDSCAPE_SIZES =
    '(max-width: 800px) 90vw, (max-width: 1400px) 45vw, 340px';
export const HERO_SIZES = '100vw';
