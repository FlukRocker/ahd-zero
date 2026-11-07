<?php

namespace App\Support;

use App\Enums\CosmeticType;
use App\Models\ChatCosmetic;

use function is_array;
use function is_int;
use function is_string;

/**
 * Turns a cosmetic's stored payload into inline CSS custom properties.
 *
 * It re-validates on every render, deliberately. Validating only at admin save
 * time assumes the row cannot change afterwards, and a value that reaches
 * style="..." sits in a CSS context — HTML escaping does nothing there, so
 * `red; background: url(...)` would sail through. The rule is that this class
 * refuses anything it cannot prove safe and returns an empty map, which renders
 * as the default style.
 *
 * Asset slugs are matched against a strict pattern AND checked for existence in
 * a fixed directory, so a payload cannot name a file outside it.
 */
class CosmeticStyle
{
    private const HEX = '/^#[0-9a-f]{6}$/iD';

    private const SLUG = '/^[a-z0-9-]{1,32}$/D';

    private const SMILEY_CODE = '/^\(\w{2,12}\)$/D';

    /**
     * @return array<string, string>
     */
    public static function for(ChatCosmetic $cosmetic): array
    {
        // A hand-edited row can decode to null or a JSON scalar despite the array
        // cast, so the declared type is not trusted here.
        /** @var mixed $payload */
        $payload = $cosmetic->payload;

        if (! is_array($payload)) {
            return [];
        }

        return match ($cosmetic->type) {
            CosmeticType::NameColor => self::hex($payload['color'] ?? null) !== null
                ? ['--chat-name-color' => (string) $payload['color']]
                : [],

            CosmeticType::NameGradient => self::gradient($payload),

            CosmeticType::Glow => self::glow($payload),

            CosmeticType::Pawn, CosmeticType::Background => self::asset($cosmetic),

            CosmeticType::Smiley => [],
        };
    }

    public static function assetUrl(CosmeticType $type, string $slug): ?string
    {
        $directory = match ($type) {
            CosmeticType::Pawn => 'pawns',
            CosmeticType::Background => 'backgrounds',
            default => null,
        };

        if ($directory === null || preg_match(self::SLUG, $slug) !== 1) {
            return null;
        }

        $extension = $type === CosmeticType::Pawn ? 'svg' : 'webp';
        $relative = "/chat/{$directory}/{$slug}.{$extension}";

        return is_file(public_path($relative)) ? $relative : null;
    }

    public static function isValidSmileyCode(string $code): bool
    {
        return preg_match(self::SMILEY_CODE, $code) === 1;
    }

    private static function hex(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::HEX, $value) === 1 ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function gradient(array $payload): array
    {
        $from = self::hex($payload['from'] ?? null);
        $to = self::hex($payload['to'] ?? null);
        $angle = $payload['angle'] ?? null;

        if ($from === null || $to === null || ! is_int($angle) || $angle < 0 || $angle > 360) {
            return [];
        }

        return ['--chat-name-gradient' => "linear-gradient({$angle}deg, {$from}, {$to})"];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function glow(array $payload): array
    {
        $color = self::hex($payload['color'] ?? null);
        $strength = $payload['strength'] ?? null;

        if ($color === null || ! is_int($strength) || $strength < 1 || $strength > 3) {
            return [];
        }

        $radius = $strength * 4;

        return ['--chat-glow' => "0 0 {$radius}px {$color}"];
    }

    /**
     * @return array<string, string>
     */
    private static function asset(ChatCosmetic $cosmetic): array
    {
        $slug = $cosmetic->payload['asset'] ?? null;

        if (! is_string($slug)) {
            return [];
        }

        $url = self::assetUrl($cosmetic->type, $slug);

        if ($url === null) {
            return [];
        }

        $property = $cosmetic->type === CosmeticType::Pawn ? '--chat-pawn' : '--chat-background';

        return [$property => "url('{$url}')"];
    }
}
