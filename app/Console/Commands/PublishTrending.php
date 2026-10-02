<?php

namespace App\Console\Commands;

use App\Services\TrendingNetwork;
use Illuminate\Console\Command;
use Throwable;

/**
 * Publishes this site's trending snapshot to the network database.
 *
 * A site with a trending rail republishes on its own when its snapshot goes
 * stale; a site without one depends on this entirely. It is scheduled so
 * every site keeps a fresh snapshot in the network however quiet it is, and
 * run by hand to check that the network connection and grant work.
 *
 * Identical in every site that publishes, like TrendingNetwork.
 */
class PublishTrending extends Command
{
    protected $signature = 'trending:publish
                            {--show=10 : Print this many of the network ranking afterwards}';

    protected $description = "Publish this site's trending snapshot to the shared network database";

    public function handle(TrendingNetwork $network): int
    {
        try {
            $snapshot = $network->publish();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'published %s: %d anime, %d views over %d days',
            $network->site(),
            count($snapshot['items']),
            $snapshot['total'],
            $snapshot['days'],
        ));

        $show = (int) $this->option('show');

        if ($show > 0) {
            $settings = $network->settings();
            $this->line("mode {$settings['mode']}, network weight {$settings['network_weight']}");

            $rows = [];
            foreach ($network->rank($show) as $i => $row) {
                $score = $row['score'] === PHP_FLOAT_MAX ? 'pinned' : round($row['score'], 5);
                $rows[] = [$i + 1, $row['cat_id'], $score, $row['views']];
            }

            $this->table(['#', 'cat_id', 'score', 'views'], $rows);
        }

        return self::SUCCESS;
    }
}
