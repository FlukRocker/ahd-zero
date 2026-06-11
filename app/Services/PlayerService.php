<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function config;
use function preg_match;

class PlayerService
{
    /**
     * Get the player iframe URL for an episode.
     *
     * Resolves the akuma-stream watch URL from a Google Drive link (external
     * file ref) or a bare video UUID via GET /api/videos/player/{ref}.
     * Caches the result.
     */
    public function getPlayerUrl(?string $listUrl): ?string
    {
        if ($listUrl === null || $listUrl === '') {
            return null;
        }

        $ref = $this->extractRef($listUrl);
        if ($ref === null) {
            // Strict: never expose a Drive URL the regex didn't recognize.
            // The DB stores raw Drive links — leaking one to the iframe would
            // both fail (X-Frame-Options) AND expose private file IDs.
            // Pass through ONLY non-Drive URLs (direct embed targets).
            if (str_contains(strtolower($listUrl), 'drive.google.com')) {
                return null;
            }

            return $listUrl;
        }

        $cacheKey = "player:stream:v1:{$ref}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached !== '' ? $cached : null;
        }

        $watchUrl = $this->fetchWatchUrl($ref);

        // Cache ready videos for 24h. Cache misses (still processing, 404,
        // transient API outage) for only 60s so the player appears as soon
        // as the video becomes ready instead of being pinned to "no player".
        Cache::put($cacheKey, $watchUrl ?? '', $watchUrl !== null ? 86400 : 60);

        return $watchUrl;
    }

    /**
     * Extract the API ref: a Google Drive file ID from various URL formats,
     * or a bare video UUID (both hit the same /api/videos/player/{ref} route).
     */
    private function extractRef(string $url): ?string
    {
        // Format: https://drive.google.com/file/d/{ID}/...
        if (preg_match('#drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        // Format: https://drive.google.com/open?id={ID}
        if (preg_match('#drive\.google\.com/open\?id=([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        // Format: https://drive.google.com/uc?id={ID} (direct download link)
        if (preg_match('#drive\.google\.com/uc[^?]*\?(?:[^&]*&)*id=([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        // Bare video UUID stored directly in the DB.
        if (preg_match('#^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$#', $url)) {
            return $url;
        }

        return null;
    }

    private function fetchWatchUrl(string $ref): ?string
    {
        $apiUrl = rtrim((string) config('services.akuma_stream.url', 'https://app.akuma-stream.com'), '/');
        $token = (string) config('services.akuma_stream.admin_token', '');

        if ($apiUrl === '' || $token === '') {
            Log::warning('PlayerService akuma_stream config missing', ['ref' => $ref, 'hasUrl' => $apiUrl !== '', 'hasToken' => $token !== '']);

            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['x-admin-token' => $token])
                ->get("{$apiUrl}/api/videos/player/{$ref}");

            if ($response->status() === 404) {
                Log::info('PlayerService unknown ref', ['ref' => $ref]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('PlayerService API non-2xx', [
                    'ref' => $ref,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 200),
                ]);

                return null;
            }

            $data = $response->json();
            $watchUrl = $data['watchUrl'] ?? null;

            if (! is_string($watchUrl) || $watchUrl === '') {
                // watchUrl stays null until status=ready — short-cached above
                // so the player shows up once processing finishes.
                Log::info('PlayerService video not ready', [
                    'ref' => $ref,
                    'videoStatus' => $data['status'] ?? null,
                ]);

                return null;
            }

            // API returns a relative path ("/watch/{uuid}") — make it absolute
            // for the iframe src.
            if (str_starts_with($watchUrl, '/')) {
                return $apiUrl.$watchUrl;
            }

            return $watchUrl;
        } catch (\Throwable $e) {
            Log::warning('PlayerService API threw', [
                'ref' => $ref,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return null;
        }
    }
}
