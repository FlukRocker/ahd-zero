<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function mb_strrpos;
use function mb_substr;
use function str_contains;

class ImageVariantService
{
    private const CACHE_TTL = 86400; // 24 hours

    private const CACHE_PREFIX = 'img_variant:';

    /**
     * Get a Chevereto image variant URL, with Redis-cached validation.
     * Returns the variant URL if it exists, otherwise the original.
     */
    public function getVariant(?string $url, string $suffix): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (! str_contains($url, 'shirokami.me')) {
            return $url;
        }

        $variantUrl = $this->buildVariantUrl($url, $suffix);
        if ($variantUrl === null) {
            return $url;
        }

        // Check Redis cache
        $cacheKey = self::CACHE_PREFIX.md5($variantUrl);
        $cached = Cache::get($cacheKey);

        if ($cached === 'valid') {
            return $variantUrl;
        }

        if ($cached === 'invalid') {
            return $url;
        }

        // Unknown. Never probe inline: this runs once per image per page, the
        // probe is a 3s-timeout network round-trip, and pages that render many
        // cards would serialise dozens of them into one response — which is
        // what pushed requests past the edge timeout (524) whenever this cache
        // went cold. Serve the original now; resolve the variant out of band.
        $this->probeInBackground($cacheKey, $variantUrl);

        return $url;
    }

    /**
     * Resolve the variant after the response has been sent, and only once per
     * URL at a time — otherwise every concurrent request for an uncached image
     * would queue its own probe.
     */
    private function probeInBackground(string $cacheKey, string $variantUrl): void
    {
        if (! Cache::add($cacheKey.':probing', 1, 60)) {
            return;
        }

        defer(function () use ($cacheKey, $variantUrl): void {
            $exists = $this->checkUrlExists($variantUrl);
            Cache::put($cacheKey, $exists ? 'valid' : 'invalid', self::CACHE_TTL);
            Cache::forget($cacheKey.':probing');
        });
    }

    private function buildVariantUrl(string $url, string $suffix): ?string
    {
        $dotPos = mb_strrpos($url, '.');
        if ($dotPos === false) {
            return null;
        }

        return mb_substr($url, 0, $dotPos).'.'.$suffix.mb_substr($url, $dotPos);
    }

    private function checkUrlExists(string $url): bool
    {
        try {
            $response = Http::timeout(3)->head($url);

            // Chevereto returns 404 with image/gif content-type for missing variants
            if ($response->status() !== 200) {
                return false;
            }

            // Check content-type: real images are webp/jpg/png, placeholder is gif
            $contentType = $response->header('Content-Type');
            if ($contentType === 'image/gif' && ! str_contains($url, '.gif')) {
                return false; // Chevereto placeholder GIF
            }

            return true;
        } catch (\Throwable) {
            // Network error — assume invalid, don't cache failure for long
            return false;
        }
    }
}
