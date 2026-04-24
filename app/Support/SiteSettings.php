<?php

namespace App\Support;

use function config;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function storage_path;

/**
 * Resolves runtime site flags. Layered config:
 *   1. config/site.php   — deploy-time (env-driven) defaults
 *   2. storage/app/site_settings.json — admin Settings UI overrides
 */
class SiteSettings
{
    private static function path(): string
    {
        return storage_path('app/site_settings.json');
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(): array
    {
        $path = self::path();
        if (! file_exists($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function set(string $key, mixed $value): void
    {
        $settings = self::read();
        $settings[$key] = $value;
        file_put_contents(self::path(), json_encode($settings));
    }

    public static function registrationEnabled(): bool
    {
        $configDefault = (bool) config('site.registration_enabled', true);

        $stored = self::read();
        if (! array_key_exists('registration_enabled', $stored)) {
            return $configDefault;
        }

        // Both must agree: env can hard-disable, admin can also toggle within
        // whatever env permits.
        return $configDefault && (bool) $stored['registration_enabled'];
    }
}
