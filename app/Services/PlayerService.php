<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function config;
use function preg_match;

class PlayerService
{
    /**
     * Get the player iframe URL for an episode.
     * Extracts Google Drive ID from list_url, calls the API, caches the result.
     */
    public function getPlayerUrl(?string $listUrl): ?string
    {
        if ($listUrl === null || $listUrl === '') {
            return null;
        }

        $driveId = $this->extractDriveId($listUrl);
        if ($driveId === null) {
            // Strict: never expose a Drive URL the regex didn't recognize.
            // The DB stores raw Drive links — leaking one to the iframe would
            // both fail (X-Frame-Options) AND expose private file IDs.
            // Pass through ONLY non-Drive URLs (direct embed targets).
            if (str_contains(strtolower($listUrl), 'drive.google.com')) {
                return null;
            }

            return $listUrl;
        }

        // Check cache
        $cacheKey = "player:drive:{$driveId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached !== '' ? $cached : null;
        }

        // Call the API
        $playerUrl = $this->fetchPlayerUrl($driveId);

        // Cache result (even null/empty to avoid repeated API calls)
        Cache::put($cacheKey, $playerUrl ?? '', 86400); // 24 hours

        return $playerUrl;
    }

    /**
     * Extract Google Drive file ID from various URL formats.
     */
    private function extractDriveId(string $url): ?string
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

        return null;
    }

    private function fetchPlayerUrl(string $driveId): ?string
    {
        try {
            $apiUrl = config('services.akuma_player.url', 'http://65.108.61.69:3002');
            $token = config('services.akuma_player.token', '23xSO2aBkri5yY35sjA9');

            $response = Http::timeout(10)->get("{$apiUrl}/api/v1/get-datas/{$driveId}", [
                'token' => $token,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $uid = $data['result']['uid'] ?? null;

            if ($uid === null) {
                return null;
            }

            $playerDomain = config('services.akuma_player.player_domain', 'https://akuma-player.xyz');

            return "{$playerDomain}/play/{$uid}";
        } catch (\Throwable) {
            return null;
        }
    }
}
