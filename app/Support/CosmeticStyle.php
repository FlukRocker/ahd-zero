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

    /** Name icons an admin can attach to a cosmetic: key => glyph (the glyph lives in CSS, keyed by the same name). */
    public const ICONS = ['crown', 'star', 'heart', 'fire', 'bolt', 'sparkle', 'flower', 'skull', 'moon', 'music', 'diamond', 'ghost'];

    /** Name animations: each maps to a keyframe in name-styles.blade.php. */
    public const ANIMS = ['blink', 'pulse', 'rainbow', 'shimmer', 'bounce'];

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

        $base = match ($cosmetic->type) {
            CosmeticType::NameColor => self::hex($payload['color'] ?? null) !== null
                ? ['--chat-name-color' => (string) $payload['color']]
                : [],

            CosmeticType::NameGradient => self::gradient($payload),

            CosmeticType::Glow => self::glow($payload),

            CosmeticType::Background => self::background($payload),

            CosmeticType::Pawn => self::asset($cosmetic),

            CosmeticType::Smiley => [],
        };

        // Optional extras ride on the name and pawn types only. A bad extra is
        // dropped on its own and never breaks the base style.
        if ($base === [] || ! in_array($cosmetic->type, [CosmeticType::NameColor, CosmeticType::NameGradient, CosmeticType::Glow], true)) {
            return $base;
        }

        if ($cosmetic->type !== CosmeticType::Glow && is_array($payload['glow'] ?? null)) {
            $base += self::glow($payload['glow']);
        }

        return $base + self::extras($payload);
    }

    /**
     * Allow-listed icon and animation keywords.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function extras(array $payload): array
    {
        $out = [];

        if (is_string($payload['icon'] ?? null) && in_array($payload['icon'], self::ICONS, true)) {
            $out['--chat-icon'] = $payload['icon'];
        }

        if (is_string($payload['anim'] ?? null) && in_array($payload['anim'], self::ANIMS, true)) {
            $out['--chat-anim'] = $payload['anim'];
        }

        return $out;
    }

    /**
     * Why the optional extras of a payload are invalid, or null when fine.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function extrasError(array $payload, CosmeticType $type): ?string
    {
        if (isset($payload['icon']) && (! is_string($payload['icon']) || ! in_array($payload['icon'], self::ICONS, true))) {
            return 'Unknown icon.';
        }

        if (isset($payload['anim']) && (! is_string($payload['anim']) || ! in_array($payload['anim'], self::ANIMS, true))) {
            return 'Unknown animation.';
        }

        if (isset($payload['glow'])) {
            if (! in_array($type, [CosmeticType::NameColor, CosmeticType::NameGradient], true)) {
                return 'Glow can only be added to a name colour or gradient.';
            }

            if (! is_array($payload['glow']) || self::glow($payload['glow']) === []) {
                return 'The extra glow needs a #rrggbb colour and strength 1-3.';
            }
        }

        return null;
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
        $relative = "/chat-assets/{$directory}/{$slug}.{$extension}";

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
    private static function gradient(array $payload, string $property = '--chat-name-gradient'): array
    {
        $from = self::hex($payload['from'] ?? null);
        $to = self::hex($payload['to'] ?? null);
        $angle = $payload['angle'] ?? null;

        if ($from === null || $to === null || ! is_int($angle) || $angle < 0 || $angle > 360) {
            return [];
        }

        return [$property => "linear-gradient({$angle}deg, {$from}, {$to})"];
    }

    /**
     * Colour or gradient only. An asset-shaped payload yields nothing.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function background(array $payload): array
    {
        $color = self::hex($payload['color'] ?? null);

        if ($color !== null) {
            return ['--chat-background' => $color];
        }

        return self::gradient($payload, '--chat-background');
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

        return ['--chat-pawn' => "url('{$url}')"];
    }
}
