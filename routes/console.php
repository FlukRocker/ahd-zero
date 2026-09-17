<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep episode_player_urls moving without ever hammering akuma-stream: a small
// hourly slice picks up new episodes first, then re-checks the stalest rows.
// withoutOverlapping matters because a slow API run can outlast the hour.
Schedule::command('player:backfill --limit=600 --rate=5')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
