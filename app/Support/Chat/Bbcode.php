<?php

namespace App\Support\Chat;

/**
 * BBCode -> flat text segments. Never HTML.
 *
 * Whitelist: [b] [i] [u] [s] [color=#hex] [spoiler]. Anything else — unknown,
 * unclosed, mismatched, a hex that is not 3 or 6 digits — stays literal text.
 * There is no [url]. [img]https://host/pic.jpg[/img] is the one embed: https
 * only, a real domain, an image extension, no credentials or port. Anything
 * else inside [img] stays literal text, which NoLinks then rejects.
 * The client only ever sees {t:'text', v, b?, i?, u?, s?, c?, sp?} and
 * {t:'image', v, sp?}; it sets v as an <img src> and never as markup.
 */
class Bbcode
{
    private const SIMPLE = ['b', 'i', 'u', 's', 'spoiler'];

    private const FLAG = ['b' => 'b', 'i' => 'i', 'u' => 'u', 's' => 's', 'spoiler' => 'sp'];

    /** Most pictures one message may embed. */
    public const MAX_IMAGES = 3;

    private const MAX_URL = 400;

    private const IMG_TOKEN = '(?i:\[img\]https://[^\s\[\]"\'<>\\\\]{1,400}\[/img\])';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function parse(string $text): array
    {
        $tokens = preg_split(
            '~('.self::IMG_TOKEN.'|\[/?(?:b|i|u|s|spoiler)\]|\[color=#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\]|\[/color\])~',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [$text];

        // First pass: which opening tags have a matching close, in order.
        $matched = self::matchedIndexes($tokens);

        $segments = [];
        $stack = [];

        foreach ($tokens as $index => $token) {
            $image = self::imageOf($token);

            if ($image !== null) {
                self::pushImage($segments, $image, $stack);

                continue;
            }

            $tag = self::tagOf($token);

            if ($tag === null || ! isset($matched[$index])) {
                self::push($segments, $token, $stack);

                continue;
            }

            if ($tag['close']) {
                array_pop($stack);
            } else {
                $stack[] = $tag;
            }
        }

        return $segments;
    }

    /** The vetted URL when the token is a valid [img] embed, else null. */
    private static function imageOf(string $token): ?string
    {
        if (preg_match('~^'.self::IMG_TOKEN.'$~', $token) !== 1) {
            return null;
        }

        return self::imageUrl(substr($token, 5, -6));
    }

    /**
     * https, a real domain name (no IP, localhost, credentials or port) and an
     * image extension on the path. Returns the URL unchanged, or null.
     */
    public static function imageUrl(string $url): ?string
    {
        if ($url === '' || strlen($url) > self::MAX_URL || preg_match('~^https://[^\s\[\]"\'<>\\\\]+$~i', $url) !== 1) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if (preg_match('~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,24}|xn--[a-z0-9-]{1,20})$~', $host) !== 1) {
            return null;
        }

        if (preg_match('~\.(?:jpe?g|png|gif|webp)$~i', (string) ($parts['path'] ?? '')) !== 1) {
            return null;
        }

        return $url;
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<int, array{name: string, close: bool, color: ?string}>  $stack
     */
    private static function pushImage(array &$segments, string $url, array $stack): void
    {
        $segment = ['t' => 'image', 'v' => $url];

        foreach ($stack as $tag) {
            if ($tag['name'] === 'spoiler') {
                $segment['sp'] = true;
            }
        }

        $segments[] = $segment;
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     */
    public static function imageCount(array $segments): int
    {
        return count(array_filter($segments, static fn (array $s): bool => $s['t'] === 'image'));
    }

    /**
     * Text a reader sees outside the embeds, for link screening.
     *
     * @param  array<int, array<string, mixed>>  $segments
     */
    public static function plainText(array $segments): string
    {
        return implode('', array_map(
            static fn (array $s): string => $s['t'] === 'text' ? (string) $s['v'] : '',
            $segments,
        ));
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, true>
     */
    private static function matchedIndexes(array $tokens): array
    {
        $open = [];
        $matched = [];

        foreach ($tokens as $index => $token) {
            $tag = self::tagOf($token);

            if ($tag === null) {
                continue;
            }

            if (! $tag['close']) {
                $open[] = [$tag['name'], $index];

                continue;
            }

            // Properly nested only: a close must match the innermost open tag.
            if ($open !== [] && $open[count($open) - 1][0] === $tag['name']) {
                $opener = array_pop($open);
                $matched[$opener[1]] = true;
                $matched[$index] = true;
            }
        }

        return $matched;
    }

    /**
     * @return array{name: string, close: bool, color: ?string}|null
     */
    private static function tagOf(string $token): ?array
    {
        if ($token === '' || $token[0] !== '[') {
            return null;
        }

        if (preg_match('~^\[(/)?(b|i|u|s|spoiler)\]$~', $token, $m) === 1) {
            return ['name' => $m[2], 'close' => $m[1] === '/', 'color' => null];
        }

        if ($token === '[/color]') {
            return ['name' => 'color', 'close' => true, 'color' => null];
        }

        if (preg_match('~^\[color=(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}))\]$~', $token, $m) === 1) {
            return ['name' => 'color', 'close' => false, 'color' => strtolower($m[1])];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<int, array{name: string, close: bool, color: ?string}>  $stack
     */
    private static function push(array &$segments, string $text, array $stack): void
    {
        if ($text === '') {
            return;
        }

        $segment = ['t' => 'text', 'v' => $text];

        foreach ($stack as $tag) {
            if ($tag['name'] === 'color') {
                $segment['c'] = $tag['color'];
            } elseif (in_array($tag['name'], self::SIMPLE, true)) {
                $segment[self::FLAG[$tag['name']]] = true;
            }
        }

        $segments[] = $segment;
    }
}
