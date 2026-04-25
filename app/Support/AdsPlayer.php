<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function preg_replace;
use function storage_path;

/**
 * Single-banner ad slots flanking the iframe on the watch page.
 * Sourced from storage/ads/player.jsonc. Cached 5 min.
 */
class AdsPlayer
{
    private const CACHE_KEY = 'ads:player:v1';

    private const TTL = 300;

    /**
     * @return array{
     *     top: array{href:string,src:string,alt:string,rel:string}|null,
     *     bottom: array{href:string,src:string,alt:string,rel:string}|null
     * }
     */
    public static function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            /** @var array{top: array{href:string,src:string,alt:string,rel:string}|null, bottom: array{href:string,src:string,alt:string,rel:string}|null} $cached */
            return $cached;
        }

        $items = self::load();
        Cache::put(self::CACHE_KEY, $items, self::TTL);

        return $items;
    }

    /**
     * @return array{
     *     top: array{href:string,src:string,alt:string,rel:string}|null,
     *     bottom: array{href:string,src:string,alt:string,rel:string}|null
     * }
     */
    private static function load(): array
    {
        $empty = ['top' => null, 'bottom' => null];

        $path = storage_path('app/ads/player.jsonc');
        if (! file_exists($path)) {
            $path = storage_path('ads/player.jsonc');
            if (! file_exists($path)) {
                return $empty;
            }
        }

        try {
            $raw = (string) file_get_contents($path);
            $stripped = self::stripComments($raw);
            $decoded = json_decode($stripped, true);
            if (! is_array($decoded)) {
                return $empty;
            }

            return [
                'top' => self::normalize($decoded['top'] ?? null),
                'bottom' => self::normalize($decoded['bottom'] ?? null),
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * @param  mixed  $row
     * @return array{href:string,src:string,alt:string,rel:string}|null
     */
    private static function normalize($row): ?array
    {
        if (! is_array($row) || ! isset($row['href'], $row['src'])) {
            return null;
        }

        return [
            'href' => (string) $row['href'],
            'src' => (string) $row['src'],
            'alt' => (string) ($row['alt'] ?? ''),
            'rel' => (string) ($row['rel'] ?? 'nofollow noopener sponsored noreferrer ugc'),
        ];
    }

    private static function stripComments(string $jsonc): string
    {
        $stripped = (string) preg_replace('#/\*.*?\*/#s', '', $jsonc);
        $stripped = (string) preg_replace('#(^|\s)//[^\n\r]*#', '$1', $stripped);

        return $stripped;
    }
}
