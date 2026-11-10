<?php

namespace Tests\Concerns;

/**
 * site_settings.json is real storage shared with the running app: restore it
 * after each test so a test's lock, slow mode or word list never leaks.
 */
trait BacksUpSiteSettings
{
    private ?string $settingsBackup = null;

    protected function backUpSiteSettings(): void
    {
        $path = storage_path('app/site_settings.json');
        $this->settingsBackup = file_exists($path) ? (string) file_get_contents($path) : null;
    }

    protected function restoreSiteSettings(): void
    {
        $path = storage_path('app/site_settings.json');
        $this->settingsBackup === null ? @unlink($path) : file_put_contents($path, $this->settingsBackup);
    }
}
