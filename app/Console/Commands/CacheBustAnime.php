<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Bust the per-anime detail cache + every paginated index/category page.
 * Run after pushing a new episode upstream to make it visible in <1s
 * instead of waiting on the 60s TTL.
 *
 *   php84 artisan cache:bust-anime <cat_id>
 *   php84 artisan cache:bust-anime --all   # nuke index + featured caches too
 */
class CacheBustAnime extends Command
{
    protected $signature = 'cache:bust-anime
                            {catId? : yu_anime_catagory.cat_id to bust}
                            {--all : Also flush index pagination + featured caches}';

    protected $description = 'Invalidate anime detail and listing caches.';

    public function handle(): int
    {
        $catId = $this->argument('catId');

        if ($catId !== null) {
            $key = "anime:detail:v2:{$catId}";
            Cache::forget($key);
            $this->info("Forgot {$key}");
        }

        if ($this->option('all') || $catId === null) {
            // Index pagination — assume bounded (admin tooling rarely walks
            // beyond first 100 pages). Forget pages 1..100 explicitly so we
            // don't need a SCAN.
            for ($p = 1; $p <= 100; $p++) {
                Cache::forget("index:page:{$p}");
            }
            Cache::forget('featured:recommended');
            Cache::forget('featured:popular');
            $this->info('Forgot index:page:1..100 + featured:recommended + featured:popular');
        }

        return self::SUCCESS;
    }
}
