<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

use function app;
use function getimagesize;
use function ini_get;
use function ini_set;
use function md5;

/**
 * Resolves the intrinsic pixel dimensions of a (usually remote) ad creative so
 * the markup can emit explicit width/height and reserve exact space — the ad
 * image can then load without shifting the page (CLS). Ad creatives change
 * rarely, so the lookup is cached for a day; failures cache briefly and retry.
 */
class AdImage
{
    /**
     * @return array{w:int,h:int}|null
     */
    public static function dimensions(?string $url): ?array
    {
        if ($url === null || $url === '') {
            return null;
        }

        // Never hit the network during tests — the markup falls back to the
        // CSS aspect-ratio (still CLS-safe) and tests stay hermetic/fast.
        if (app()->runningUnitTests()) {
            return null;
        }

        $cacheKey = 'adimg:dims:'.md5($url);
        $cached = Cache::get($cacheKey);
        if ($cached === 'miss') {
            return null;
        }
        if (is_array($cached) && isset($cached['w'], $cached['h'])) {
            return $cached;
        }

        $dims = self::probe($url);

        // Cache a hit for a day; a miss for an hour so a transient CDN error or
        // a not-yet-uploaded creative gets retried without hammering it.
        Cache::put($cacheKey, $dims ?? 'miss', $dims !== null ? now()->addDay() : now()->addHour());

        return $dims;
    }

    /**
     * @return array{w:int,h:int}|null
     */
    private static function probe(string $url): ?array
    {
        $prev = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '3');
        try {
            // getimagesize reads only the image header, so this is a small
            // ranged-ish fetch, not a full download.
            $info = @getimagesize($url);
        } catch (\Throwable) {
            $info = false;
        } finally {
            if ($prev !== false) {
                ini_set('default_socket_timeout', (string) $prev);
            }
        }

        if ($info === false || ! isset($info[0], $info[1]) || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        return ['w' => (int) $info[0], 'h' => (int) $info[1]];
    }
}
