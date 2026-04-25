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
 * Read the ads banner config from storage/ads/banner.jsonc.
 *
 * The file is JSONC (JSON with `// line` and `/* block *\/` comments). We
 * strip comments before parsing so editors can leave inline notes in there.
 *
 * Cached 5 minutes — admin can edit the file on the server and a clear of
 * cache (or natural TTL) makes the change visible without a rebuild.
 */
class AdsBanner
{
    private const CACHE_KEY = 'ads:banners:v2';

    private const TTL = 300;

    /**
     * @return list<array{href:string,src:string,alt:string,col:int,rel:string}>
     */
    public static function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $banners = self::load();
        Cache::put(self::CACHE_KEY, $banners, self::TTL);

        return $banners;
    }

    /**
     * @return list<array{href:string,src:string,alt:string,col:int,rel:string}>
     */
    private static function load(): array
    {
        $path = storage_path('app/ads/banner.jsonc');
        if (! file_exists($path)) {
            // Fallback path — older deploy layout had banner.jsonc directly in
            // storage/ads/ rather than storage/app/ads/.
            $path = storage_path('ads/banner.jsonc');
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
                if (! is_array($row) || ! isset($row['href'], $row['src'])) {
                    continue;
                }
                $out[] = [
                    'href' => (string) $row['href'],
                    'src' => (string) $row['src'],
                    'alt' => (string) ($row['alt'] ?? ''),
                    'col' => (int) ($row['col'] ?? 6),
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
        // Strip `/* ... */` block comments (non-greedy, multi-line).
        $stripped = (string) preg_replace('#/\*.*?\*/#s', '', $jsonc);
        // Strip `// ...` line comments — careful not to eat URL schemes like
        // `https://` inside string literals. Only strip `//` that is preceded
        // by whitespace or line start.
        $stripped = (string) preg_replace('#(^|\s)//[^\n\r]*#', '$1', $stripped);

        return $stripped;
    }
}
