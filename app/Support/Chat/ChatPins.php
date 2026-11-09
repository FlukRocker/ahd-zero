<?php

namespace App\Support\Chat;

use App\Support\SiteSettings;

/**
 * One pinned message per room, kept in the site settings file as
 * {"<roomId>": <messageId>}. There is no column for it (this app cannot add
 * migrations), and a pin is one tiny, rarely-written value.
 */
class ChatPins
{
    /**
     * @return array<string, int>
     */
    public static function all(): array
    {
        $pins = SiteSettings::read()['chat_pins'] ?? [];

        return is_array($pins) ? array_map('intval', $pins) : [];
    }

    public static function get(int $roomId): ?int
    {
        return self::all()[(string) $roomId] ?? null;
    }

    public static function set(int $roomId, int $messageId): void
    {
        // Not a spread: it would renumber integer-like keys and move other rooms' pins.
        $pins = self::all();
        $pins[(string) $roomId] = $messageId;

        SiteSettings::set('chat_pins', $pins);
    }

    public static function clear(int $roomId): void
    {
        $pins = self::all();
        unset($pins[(string) $roomId]);

        SiteSettings::set('chat_pins', $pins);
    }
}
