<?php

namespace App\Support\Chat;

use App\Support\SiteSettings;

/**
 * Admin-tunable chat controls that live in the site settings file (no schema):
 * the announcement banner, the banned-word list, and per-room lock / slow mode.
 */
class ChatSettings
{
    public const ANNOUNCEMENT_MAX = 300;

    public const WORDS_MAX = 500;

    public const WORD_MIN_LEN = 2;

    public const WORD_MAX_LEN = 40;

    public const SLOW_MAX = 3600;

    public static function announcement(): ?string
    {
        $value = SiteSettings::read()['chat_announcement'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public static function setAnnouncement(string $text): void
    {
        // One line, no control characters.
        $clean = trim((string) preg_replace('/\p{C}+/u', ' ', $text));

        SiteSettings::set('chat_announcement', mb_substr($clean, 0, self::ANNOUNCEMENT_MAX));
    }

    /** @return list<string> */
    public static function words(): array
    {
        $value = SiteSettings::read()['chat_words'] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /** One word per line; lower-cased, trimmed, de-duplicated and length-limited. */
    public static function setWords(string $text): void
    {
        $words = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $word = mb_strtolower(trim((string) preg_replace('/\p{C}+/u', '', $line)));
            $length = mb_strlen($word);

            if ($length >= self::WORD_MIN_LEN && $length <= self::WORD_MAX_LEN) {
                $words[$word] = $word;
            }
        }

        SiteSettings::set('chat_words', array_slice(array_values($words), 0, self::WORDS_MAX));
    }

    /**
     * The first banned word found in the text, or null. Plain substring match
     * (case-insensitive) because Thai has no word boundaries; the cost is that a
     * short word can also match inside a longer, innocent one.
     */
    public static function bannedWordIn(string $text): ?string
    {
        foreach (self::words() as $word) {
            if (mb_stripos($text, $word) !== false) {
                return $word;
            }
        }

        return null;
    }

    /** @return array{locked: bool, slow: int} */
    public static function room(int $roomId): array
    {
        $all = SiteSettings::read()['chat_room_controls'] ?? [];
        $row = is_array($all) ? ($all[(string) $roomId] ?? []) : [];

        return [
            'locked' => is_array($row) && ($row['locked'] ?? false) === true,
            'slow' => is_array($row) && is_int($row['slow'] ?? null) ? max(0, min(self::SLOW_MAX, $row['slow'])) : 0,
        ];
    }

    public static function setRoom(int $roomId, bool $locked, int $slowSeconds): void
    {
        $all = SiteSettings::read()['chat_room_controls'] ?? [];
        $all = is_array($all) ? $all : [];
        $all[(string) $roomId] = ['locked' => $locked, 'slow' => max(0, min(self::SLOW_MAX, $slowSeconds))];

        SiteSettings::set('chat_room_controls', $all);
    }
}
