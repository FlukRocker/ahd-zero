<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Services\PlayerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

use function config;

/**
 * Diagnose why the player isn't loading on a given host. Run this on the
 * production server to isolate config / network / API issues.
 *
 *   php84 artisan player:probe <list_id>
 *   php84 artisan player:probe --url=https://drive.google.com/file/d/.../view
 */
class PlayerProbe extends Command
{
    protected $signature = 'player:probe
                            {listId? : Episode list_id to probe}
                            {--url= : Raw URL to probe (skip DB lookup)}';

    protected $description = 'Probe PlayerService end-to-end against a real Drive URL.';

    public function handle(PlayerService $service): int
    {
        $url = $this->option('url');
        $listId = $this->argument('listId');

        if ($url === null && $listId !== null) {
            $ep = Episode::query()->where('list_id', (int) $listId)->first();
            if ($ep === null) {
                $this->error("No episode with list_id={$listId}");

                return self::FAILURE;
            }
            $url = $ep->list_url ?: $ep->file_src;
            $this->line("Episode list_id={$ep->list_id} list_title={$ep->list_title}");
            $this->line('list_url='.($ep->list_url ?? '(null)'));
            $this->line('file_src='.($ep->file_src ?? '(null)'));
        }

        if ($url === null) {
            $this->error('Provide a list_id or --url=...');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Config ===');
        $this->line('akuma_player.url      = '.config('services.akuma_player.url'));
        $this->line('akuma_player.token    = '.preg_replace('/.{4}$/', '****', (string) config('services.akuma_player.token')));
        $this->line('akuma_player.player_domain = '.config('services.akuma_player.player_domain'));

        $this->newLine();
        $this->info('=== Network reachability ===');
        $apiUrl = (string) config('services.akuma_player.url');
        try {
            $r = Http::timeout(5)->get($apiUrl);
            $this->line("GET {$apiUrl} → HTTP {$r->status()}");
        } catch (\Throwable $e) {
            $this->error("GET {$apiUrl} threw: ".$e->getMessage());
        }

        $this->newLine();
        $this->info('=== PlayerService::getPlayerUrl ===');
        $result = $service->getPlayerUrl($url);
        $this->line('Input:  '.$url);
        $this->line('Output: '.($result ?? '(null)'));

        if ($result === null) {
            $this->newLine();
            $this->warn('Returned null. Check storage/logs/laravel.log for the most recent PlayerService warning.');
        }

        return self::SUCCESS;
    }
}
