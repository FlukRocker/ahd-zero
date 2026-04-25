/**
 * Responsive image helpers built on the Bunny.net Optimizer.
 *
 * Source images live on Chevereto at img.shirokami.me / img-cdn.shirokami.me.
 * The Bunny Pull Zone at https://img-cdn-proxy.shirokami.me proxies the same
 * paths and runs Bunny Optimizer (https://docs.bunny.net/optimizer/dynamic-images/overview)
 * which transforms images on-demand: resize, quality, automatic WebP/AVIF
 * via Accept-header negotiation.
 *
 * Pattern:
 *   origin:  https://img.shirokami.me/images/2024/04/abc.png
 *   proxied: https://img-cdn-proxy.shirokami.me/images/2024/04/abc.png?width=720&quality=80
 */

const BUNNY_PROXY = 'https://img-cdn-proxy.shirokami.me';

const PROXIED_SOURCE_HOSTS = new Set([
    'img.shirokami.me',
    'img-cdn.shirokami.me',
    'images.shirokami.me',
]);

/**
 * Strip an existing Chevereto variant suffix (.md / .th) so we always feed
 * the canonical original to Bunny — Bunny resizes from the largest source.
 */
function stripVariant(url: string): string {
    return url.replace(/\.(md|th)(\.[a-z0-9]+)$/i, '$2');
}

interface BunnyOptions {
    width?: number;
    height?: number;
    quality?: number;
    format?: 'auto' | 'webp' | 'avif' | 'jpeg' | 'png';
    /** Bunny `aspect_ratio=W:H` — useful for cropped thumbs. */
    aspect?: string;
    /** Bunny `crop=W,H,X,Y` — manual crop rectangle. */
    crop?: string;
    /** Override the default 80 quality. */
    sharpen?: number;
}

/**
 * Build a Bunny Optimizer URL. Non-shirokami origins pass through unchanged
 * (e.g. cdn.myanimelist.net) — Bunny only proxies our own zones.
 */
export function bunnyImg(
    url: string | null | undefined,
    opts: BunnyOptions = {},
): string | null {
    if (!url) return null;

    let parsed: URL;
    try {
        parsed = new URL(url);
    } catch {
        return url;
    }

    if (!PROXIED_SOURCE_HOSTS.has(parsed.hostname)) {
        // External origin — Bunny pull zone won't proxy it. Return as-is.
        return url;
    }

    const path = stripVariant(parsed.pathname);
    const params = new URLSearchParams();
    if (opts.width) params.set('width', String(opts.width));
    if (opts.height) params.set('height', String(opts.height));
    if (opts.aspect) params.set('aspect_ratio', opts.aspect);
    if (opts.crop) params.set('crop', opts.crop);
    if (opts.sharpen !== undefined) params.set('sharpen', String(opts.sharpen));
    params.set('quality', String(opts.quality ?? 80));
    // Bunny auto-negotiates WebP/AVIF when no explicit format param is set,
    // so we only pin it when the caller wants a specific format.
    if (opts.format && opts.format !== 'auto') {
        params.set('format', opts.format);
    }

    return `${BUNNY_PROXY}${path}?${params.toString()}`;
}

/**
 * Build a `srcset` from a list of widths. Each entry runs through Bunny so
 * the browser picks the smallest variant for the device's DPR + viewport.
 */
export function bunnySrcset(
    url: string | null | undefined,
    widths: number[],
    opts: Omit<BunnyOptions, 'width'> = {},
): string | null {
    if (!url) return null;
    const built = widths
        .map((w) => {
            const u = bunnyImg(url, { ...opts, width: w });
            return u ? `${u} ${w}w` : null;
        })
        .filter((s): s is string => s !== null);
    return built.length ? built.join(', ') : null;
}

/**
 * `src` for the default download. Pick the middle width so old browsers w/o
 * srcset still get a sane size instead of the full-resolution original.
 */
export function bunnyDefault(
    url: string | null | undefined,
    width: number,
    opts: Omit<BunnyOptions, 'width'> = {},
): string | null {
    return bunnyImg(url, { ...opts, width });
}

export interface ImgOptions {
    loading?: 'lazy' | 'eager';
    decoding?: 'async' | 'auto' | 'sync';
    sizes?: string;
    width?: number | string;
    height?: number | string;
}

// Width sets calibrated to the card aspect ratios + DPR up to 3x.
export const POSTER_WIDTHS = [240, 360, 480, 600];
export const LANDSCAPE_WIDTHS = [340, 520, 760, 1020];
export const HERO_WIDTHS = [800, 1200, 1600, 2000];

export const POSTER_SIZES =
    '(max-width: 600px) 45vw, (max-width: 1200px) 25vw, 200px';
export const LANDSCAPE_SIZES =
    '(max-width: 800px) 90vw, (max-width: 1400px) 45vw, 340px';
export const HERO_SIZES = '100vw';

// Backward-compat alias — older callers used chevSrcset(url) with the
// hard-coded th/md/original triple. New helpers go through Bunny.
export function chevSrcset(url: string | null | undefined): string | null {
    return bunnySrcset(url, POSTER_WIDTHS);
}
