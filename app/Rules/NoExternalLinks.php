<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use function array_map;
use function config;
use function in_array;
use function is_string;
use function parse_url;
use function preg_match_all;
use function strtolower;

/**
 * Rejects a string field if it contains URLs to domains outside our own
 * properties. Stops users from leaving comment-section backlinks for SEO
 * spam — even raw text like "https://spammy.tld/" matches.
 *
 * Allowed hosts come from APP_URL plus a hard-coded set of internal CDNs.
 * Anything else fails validation. The rule is intentionally strict: links
 * to legit external resources should be discussed in the issue tracker
 * before being whitelisted.
 */
class NoExternalLinks implements ValidationRule
{
    /** @var list<string> */
    private const ALLOWED_HOSTS = [
        'animehdzero.net',
        // The v1 domain stays allowed so links inside existing comments do not
        // start failing validation on edit.
        'anime-hdzero.com',
        'akuma-player.xyz',
        'img.shirokami.me',
        'img-cdn.shirokami.me',
        'img-cdn-proxy.shirokami.me',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (preg_match_all('#https?://([^\s/?<>"\')]+)#i', $value, $m) === false) {
            return;
        }

        if (empty($m[1])) {
            return;
        }

        $allowed = $this->allowedHosts();

        foreach ($m[1] as $host) {
            $host = strtolower((string) $host);
            if (! $this->isAllowed($host, $allowed)) {
                $fail('ห้ามแนบลิงก์ไปยังเว็บไซต์ภายนอก');

                return;
            }
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function isAllowed(string $host, array $allowed): bool
    {
        foreach ($allowed as $a) {
            if ($host === $a || str_ends_with($host, '.'.$a)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        $hosts = self::ALLOWED_HOSTS;

        $appUrl = (string) config('app.url', '');
        if ($appUrl !== '') {
            $parsed = parse_url($appUrl, PHP_URL_HOST);
            if (is_string($parsed) && $parsed !== '' && ! in_array($parsed, $hosts, true)) {
                $hosts[] = $parsed;
            }
        }

        return array_map('strtolower', $hosts);
    }
}
