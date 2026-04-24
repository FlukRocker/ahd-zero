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
 * Tagline ad strip across the top of front pages. Sourced from
 * storage/ads/navbar.jsonc (JSONC). Cached 5 min.
 */
class AdsNavbar
{
    private const CACHE_KEY = 'ads:navbar:v1';

    private const TTL = 300;

    /**
     * @return list<array{href:string,alt:string,rel:string}>
     */
    public static function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $items = self::load();
        Cache::put(self::CACHE_KEY, $items, self::TTL);

        return $items;
    }

    /**
     * @return list<array{href:string,alt:string,rel:string}>
     */
    private static function load(): array
    {
        $path = storage_path('app/ads/navbar.jsonc');
        if (! file_exists($path)) {
            $path = storage_path('ads/navbar.jsonc');
            if (! file_exists($path)) {
                return [];
            }
        }

        try {
            $raw = (string) file_get_contents($path);
            $stripped = self::stripComments($raw);
            $decoded = json_decode($stripped, true);
            if (! is_array($decoded)) {
                return [];
            }

            $out = [];
            foreach ($decoded as $row) {
                if (! is_array($row) || ! isset($row['href'])) {
                    continue;
                }
                $out[] = [
                    'href' => (string) $row['href'],
                    'alt' => (string) ($row['alt'] ?? $row['label'] ?? ''),
                    'rel' => (string) ($row['rel'] ?? 'nofollow noopener sponsored noreferrer ugc'),
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    private static function stripComments(string $jsonc): string
    {
        $stripped = (string) preg_replace('#/\*.*?\*/#s', '', $jsonc);
        $stripped = (string) preg_replace('#(^|\s)//[^\n\r]*#', '$1', $stripped);

        return $stripped;
    }
}
