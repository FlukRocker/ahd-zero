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
 * Floating ad slots: left + right rails (160x600) and a bottom strip of 728x90
 * banners. Sourced from storage/ads/floating.jsonc. Cached 5 min.
 */
class AdsFloating
{
    private const CACHE_KEY = 'ads:floating:v1';

    private const TTL = 300;

    /**
     * @return array{
     *     left: array{href:string,src:string,alt:string,rel:string}|null,
     *     right: array{href:string,src:string,alt:string,rel:string}|null,
     *     bottom: list<array{href:string,src:string,alt:string,rel:string}>
     * }
     */
    public static function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            /** @var array{left: array{href:string,src:string,alt:string,rel:string}|null, right: array{href:string,src:string,alt:string,rel:string}|null, bottom: list<array{href:string,src:string,alt:string,rel:string}>} $cached */
            return $cached;
        }

        $items = self::load();
        Cache::put(self::CACHE_KEY, $items, self::TTL);

        return $items;
    }

    /**
     * @return array{
     *     left: array{href:string,src:string,alt:string,rel:string}|null,
     *     right: array{href:string,src:string,alt:string,rel:string}|null,
     *     bottom: list<array{href:string,src:string,alt:string,rel:string}>
     * }
     */
    private static function load(): array
    {
        $empty = ['left' => null, 'right' => null, 'bottom' => []];

        $path = storage_path('app/ads/floating.jsonc');
        if (! file_exists($path)) {
            $path = storage_path('ads/floating.jsonc');
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

            $bottomList = is_array($decoded['bottom'] ?? null) ? $decoded['bottom'] : [];
            $bottom = [];
            foreach ($bottomList as $row) {
                $banner = self::normalize($row);
                if ($banner !== null) {
                    $bottom[] = $banner;
                }
            }

            return [
                'left' => self::normalize($decoded['left'] ?? null),
                'right' => self::normalize($decoded['right'] ?? null),
                'bottom' => $bottom,
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
