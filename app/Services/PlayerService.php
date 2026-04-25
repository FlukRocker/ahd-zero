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

        // Bumped key prefix (`v2`) so previously-cached failures from when
        // the API was misconfigured don't stick for 24h.
        $cacheKey = "player:drive:v2:{$driveId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached !== '' ? $cached : null;
        }

        $playerUrl = $this->fetchPlayerUrl($driveId);

        // Cache successes for 24h. Cache failures for only 60s so a transient
        // API outage doesn't pin every episode to "no player" until tomorrow.
        Cache::put($cacheKey, $playerUrl ?? '', $playerUrl !== null ? 86400 : 60);

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
        $apiUrl = (string) config('services.akuma_player.url', 'http://65.108.61.69:3002');
        $token = (string) config('services.akuma_player.token', '23xSO2aBkri5yY35sjA9');
        $playerDomain = (string) config('services.akuma_player.player_domain', 'https://akuma-player.xyz');

        if ($apiUrl === '' || $token === '') {
            Log::warning('PlayerService config missing', compact('apiUrl', 'driveId'));

            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get("{$apiUrl}/api/v1/get-datas/{$driveId}", ['token' => $token]);

            if (! $response->successful()) {
                Log::warning('PlayerService API non-2xx', [
                    'driveId' => $driveId,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 200),
                ]);

                return null;
            }

            $data = $response->json();
            $uid = $data['result']['uid'] ?? null;

            if (! is_string($uid) || $uid === '') {
                Log::warning('PlayerService API ok but no uid', [
                    'driveId' => $driveId,
                    'data' => $data,
                ]);

                return null;
            }

            return "{$playerDomain}/play/{$uid}";
        } catch (\Throwable $e) {
            Log::warning('PlayerService API threw', [
                'driveId' => $driveId,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return null;
        }
    }
}
