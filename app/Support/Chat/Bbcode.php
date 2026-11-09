<?php

namespace App\Support\Chat;

/**
 * BBCode -> flat text segments. Never HTML.
 *
 * Whitelist: [b] [i] [u] [s] [color=#hex] [spoiler]. Anything else — unknown,
 * unclosed, mismatched, a hex that is not 3 or 6 digits — stays literal text.
 * There is no [url] and no [img] here (images are server-appended tokens, see
 * ChatImage). The client only ever sees {t:'text', v, b?, i?, u?, s?, c?, sp?}.
 */
class Bbcode
{
    private const SIMPLE = ['b', 'i', 'u', 's', 'spoiler'];

    private const FLAG = ['b' => 'b', 'i' => 'i', 'u' => 'u', 's' => 's', 'spoiler' => 'sp'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function parse(string $text): array
    {
        $tokens = preg_split(
            '~(\[/?(?:b|i|u|s|spoiler)\]|\[color=#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\]|\[/color\])~',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [$text];

        // First pass: which opening tags have a matching close, in order.
        $matched = self::matchedIndexes($tokens);

        $segments = [];
        $stack = [];

        foreach ($tokens as $index => $token) {
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
