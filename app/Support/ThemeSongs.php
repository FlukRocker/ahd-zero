<?php

namespace App\Support;

/**
 * Opening/ending theme songs for display.
 *
 * yu_anime_catagory.opening_themes / ending_themes hold a JSON list whose
 * items are either a legacy string (MyAnimeList text, sometimes wrapped in
 * HTML by an old editor) or, from ahd-admin's song editor, an object
 * {name, spotify: "track|album|playlist/{id}", youtube: "{id}"}.
 * The column is written elsewhere, so every value is re-checked here and
 * every URL is built here from a validated id, never taken from the row.
 *
 * Call it once per request, after the page cache: its output's `spotify` is
 * an array, which fails the id check if fed back in.
 *
 * Contract: ahd-admin docs/superpowers/specs/2026-10-20-theme-song-players-design.md §1.
 * Same behaviour in neko-miku, anime-hana, ahd-v2, kurokami-v2 and lnw-anime.
 */
class ThemeSongs
{
    private const SPOTIFY = '~^(track|album|playlist)/([A-Za-z0-9]{22})$~D';

    private const YOUTUBE = '~^[A-Za-z0-9_-]{11}$~D';

    /**
     * @return list<array{name: string, spotify: array{src: string, height: int}|null, youtube: string|null}>
     */
    public static function forDisplay(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $songs = [];
        foreach ($items as $item) {
            $song = self::song($item);
            if ($song !== null) {
                $songs[] = $song;
            }
        }

        return $songs;
    }

    /**
     * @return array{name: string, spotify: array{src: string, height: int}|null, youtube: string|null}|null
     */
    private static function song(mixed $item): ?array
    {
        if (is_string($item)) {
            $name = self::plainText($item);

            return $name === '' ? null : ['name' => $name, 'spotify' => null, 'youtube' => null];
        }

        if (! is_array($item) || ! is_string($item['name'] ?? null)) {
            return null;
        }

        $name = self::plainText($item['name']);
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'spotify' => self::spotify($item['spotify'] ?? null),
            'youtube' => self::youtube($item['youtube'] ?? null),
        ];
    }

    /**
     * @return array{src: string, height: int}|null
     */
    private static function spotify(mixed $ref): ?array
    {
        if (! is_string($ref) || preg_match(self::SPOTIFY, $ref, $m) !== 1) {
            return null;
        }

        // Spotify's compact embed: 80px fits one track, an album or playlist needs 152px.
        return [
            'src' => "https://open.spotify.com/embed/{$m[1]}/{$m[2]}",
            'height' => $m[1] === 'track' ? 80 : 152,
        ];
    }

    private static function youtube(mixed $id): ?string
    {
        return is_string($id) && preg_match(self::YOUTUBE, $id) === 1 ? $id : null;
    }

    private static function plainText(string $text): string
    {
        $plain = strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }
}
