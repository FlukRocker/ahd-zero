<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep episode_player_urls moving without ever hammering akuma-stream: an
// hourly slice picks up new episodes first, then re-checks the stalest rows
// that hold a URL. The slice is sized so every stored URL is re-verified
// inside a day — ~49k of them, so 2500/h (60k/day) leaves headroom — because
// a stored URL that has gone dead is what puts `{"error":"not found"}` in the
// player. At 5/s a full slice is ~8 minutes of the hour.
// withoutOverlapping matters because a slow API run can outlast the hour.
Schedule::command('player:backfill --limit=2500 --rate=5')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Keeps this site's trending snapshot fresh for the rest of the network even
// when nobody is loading the homepage.
Schedule::command('trending:publish --show=0')->everyTenMinutes()->withoutOverlapping()->runInBackground();
