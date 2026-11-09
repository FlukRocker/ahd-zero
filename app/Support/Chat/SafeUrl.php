<?php

namespace App\Support\Chat;

/**
 * The one gate for a third-party URL the chat will let a browser fetch:
 * https, a real domain name (no IP, localhost, credentials or port), and no
 * character that could break out of an attribute or a CSS string.
 */
class SafeUrl
{
    public static function https(?string $url, int $maxLength, bool $imageExtension = false): ?string
    {
        if ($url === null || $url === '' || strlen($url) > $maxLength
            || preg_match('~^https://[^\s\[\]"\'<>\\\\]+$~i', $url) !== 1) {
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

        if ($imageExtension && preg_match('~\.(?:jpe?g|png|gif|webp)$~i', (string) ($parts['path'] ?? '')) !== 1) {
            return null;
        }

        return $url;
    }
}
