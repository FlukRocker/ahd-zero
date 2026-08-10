<?php

namespace App\Support;

class Img
{
    private const BUNNY_PROXY = 'https://img-cdn-proxy.shirokami.me';

    private const PROXIED_SOURCE_HOST = 'img-cdn.shirokami.me';

    /** @var list<int> */
    public const POSTER_WIDTHS = [240, 360, 480, 600];

    /** @var list<int> */
    public const LANDSCAPE_WIDTHS = [340, 520, 760, 1020];

    /** @var list<int> */
    public const HERO_WIDTHS = [800, 1200, 1600, 2000];

    public const POSTER_SIZES = '(max-width: 600px) 45vw, (max-width: 1200px) 25vw, 200px';

    public const LANDSCAPE_SIZES = '(max-width: 800px) 90vw, (max-width: 1400px) 45vw, 340px';

    public const HERO_SIZES = '100vw';

    /**
     * True when url() will send this through Bunny — which also means url()
     * strips any .md/.th suffix off it, so resolving a variant for this URL is
     * work whose answer is thrown away.
     */
    public static function isProxied(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $parsed = parse_url($url);

        return $parsed !== false && ($parsed['host'] ?? null) === self::PROXIED_SOURCE_HOST;
    }

    /**
     * Build a Bunny Optimizer URL. Non-shirokami-proxied origins pass through
     * unchanged. Ports resources/js/lib/img.ts `bunnyImg`.
     *
     * @param  array{width?:int,height?:int,quality?:int,format?:string,aspect?:string,crop?:string}  $opts
     */
    public static function url(?string $url, array $opts = []): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['host'])) {
            return $url;
        }

        if ($parsed['host'] !== self::PROXIED_SOURCE_HOST) {
            return $url;
        }

        $path = self::stripVariant($parsed['path'] ?? '');

        $params = [];
        if (isset($opts['width'])) {
            $params['width'] = (string) $opts['width'];
        }
        if (isset($opts['height'])) {
            $params['height'] = (string) $opts['height'];
        }
        if (isset($opts['aspect'])) {
            $params['aspect_ratio'] = $opts['aspect'];
        }
        if (isset($opts['crop'])) {
            $params['crop'] = $opts['crop'];
        }
        $params['quality'] = (string) ($opts['quality'] ?? 80);
        if (isset($opts['format']) && $opts['format'] !== 'auto') {
            $params['format'] = $opts['format'];
        }

        // Preserve any pre-existing query params (e.g. signed-URL signatures)
        // without overriding our optimizer params.
        if (isset($parsed['query']) && $parsed['query'] !== '') {
            parse_str($parsed['query'], $existing);
            foreach ($existing as $k => $v) {
                if (! array_key_exists($k, $params)) {
                    $params[$k] = is_array($v) ? implode(',', $v) : (string) $v;
                }
            }
        }

        return self::BUNNY_PROXY.$path.'?'.http_build_query($params);
    }

    /**
     * Build a srcset from a list of widths. Ports `bunnySrcset`.
     *
     * @param  list<int>  $widths
     * @param  array<string,mixed>  $opts
     */
    public static function srcset(?string $url, array $widths, array $opts = []): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $entries = [];
        foreach ($widths as $w) {
            // ['width' => $w] first so the per-descriptor width always wins
            // over any width in $opts (PHP array-union keeps the left operand).
            $u = self::url($url, ['width' => $w] + $opts);
            if ($u !== null) {
                $entries[] = $u.' '.$w.'w';
            }
        }

        return $entries === [] ? null : implode(', ', $entries);
    }

    private static function stripVariant(string $path): string
    {
        // Strip a Chevereto .md / .th variant suffix so Bunny resizes from the
        // canonical original: /a.md.png -> /a.png
        $result = preg_replace('/\.(md|th)(\.[a-z0-9]+)$/i', '$2', $path);

        return $result ?? $path;
    }
}
