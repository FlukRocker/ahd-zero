<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\EpisodePlayerUrl;
use App\Services\PlayerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Resolve akuma-stream watch URLs for episodes and persist them.
 *
 * Resolving is one HTTP call per episode, so this runs out of band rather than
 * on page views or sitemap builds. Both readers treat a missing row as "no
 * player yet" and degrade quietly, so a partial backfill is always safe.
 *
 * This is also the only thing that notices a stored URL has gone dead.
 * akuma-stream mints a new video uuid when an episode is re-transcoded, and
 * the old /watch/{uuid} then answers `{"error":"not found"}` straight into the
 * player iframe. ahd-admin clears the row when it re-registers an episode, so
 * the sweep is the backstop for everything that changes upstream without a
 * re-save — hence a one-day window, not a week.
 */
class BackfillPlayerUrls extends Command
{
    protected $signature = 'player:backfill
        {--limit=1000 : Episodes to resolve in this run}
        {--rate=5 : Requests per second against akuma-stream}
        {--stale=1 : Re-resolve rows older than this many days (0 disables)}
        {--fresh : Resolve only episodes never tried before}';

    protected $description = 'Resolve and persist akuma-stream watch URLs for episodes';

    public function handle(PlayerService $playerService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $rate = max(1, (int) $this->option('rate'));
        $staleDays = max(0, (int) $this->option('stale'));
        $sleepMicros = (int) (1_000_000 / $rate);

        $episodes = $this->pickEpisodes($limit, $staleDays);

        if ($episodes->isEmpty()) {
            $this->info('Nothing to resolve.');

            return self::SUCCESS;
        }

        $this->info("Resolving {$episodes->count()} episode(s) at {$rate}/s…");
        $bar = $this->output->createProgressBar($episodes->count());
        $bar->start();

        $resolved = 0;
        $empty = 0;

        foreach ($episodes as $episode) {
            $watchUrl = $playerService->getPlayerUrl($episode->list_url ?? null)
                ?? $playerService->getPlayerUrl($episode->file_src ?? null);

            EpisodePlayerUrl::query()->updateOrCreate(
                ['list_id' => $episode->list_id],
                [
                    'watch_url' => $watchUrl,
                    'resolved_at' => Carbon::now(),
                    // Counts how often we have asked, so a permanently
                    // unresolvable episode is visible rather than silently
                    // retried forever.
                    'attempts' => EpisodePlayerUrl::query()
                        ->whereKey($episode->list_id)
                        ->value('attempts') + 1,
                ]
            );

            $watchUrl === null ? $empty++ : $resolved++;

            $bar->advance();
            usleep($sleepMicros);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Resolved: {$resolved}   No player: {$empty}");

        return self::SUCCESS;
    }

    /**
     * Never-tried episodes first, then the stalest rows that hold a URL, then
     * the stalest rows that don't.
     *
     * @return \Illuminate\Support\Collection<int, Episode>
     */
    private function pickEpisodes(int $limit, int $staleDays): \Illuminate\Support\Collection
    {
        $never = Episode::query()
            ->whereNull('deleted_at')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('episode_player_urls')
                    ->whereColumn('episode_player_urls.list_id', 'yu_anime_list.list_id');
            })
            ->orderBy('list_id')
            ->limit($limit)
            ->get();

        if ($this->option('fresh') || $staleDays === 0 || $never->count() >= $limit) {
            return $never;
        }

        $stale = Episode::query()
            ->whereNull('deleted_at')
            ->whereIn('list_id', $this->staleListIds($limit - $never->count(), $staleDays))
            ->get();

        return $never->concat($stale);
    }

    /**
     * A row with no URL costs nothing to leave alone: the episode page resolves
     * those live on every view and so self-heals the moment the video is ready.
     * A row that HOLDS a URL is the only one that can rot unnoticed, because
     * the page prefers it over asking again — so those are re-checked first
     * even when an emptier row has been waiting longer.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function staleListIds(int $room, int $staleDays): \Illuminate\Support\Collection
    {
        $cutoff = Carbon::now()->subDays($staleDays);

        $ids = EpisodePlayerUrl::query()
            ->whereNotNull('watch_url')
            ->where('resolved_at', '<', $cutoff)
            ->orderBy('resolved_at')
            ->limit($room)
            ->pluck('list_id');

        if ($ids->count() >= $room) {
            return $ids;
        }

        return $ids->concat(
            EpisodePlayerUrl::query()
                ->whereNull('watch_url')
                ->where('resolved_at', '<', $cutoff)
                ->orderBy('resolved_at')
                ->limit($room - $ids->count())
                ->pluck('list_id')
        );
    }
}
