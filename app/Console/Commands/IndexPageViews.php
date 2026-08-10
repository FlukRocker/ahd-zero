<?php

namespace App\Console\Commands;

use App\Models\PageView;
use Illuminate\Console\Command;
use Throwable;

/**
 * Creates the indexes the analytics aggregations rely on.
 *
 * Deliberately a command and not a migration: building an index over a large
 * page_views collection is itself load, and a migration would fire during
 * deploy. Run this once, from a shell, at a quiet hour.
 */
class IndexPageViews extends Command
{
    protected $signature = 'analytics:index-page-views {--drop : Drop the managed indexes instead of creating them}';

    protected $description = 'Create the page_views indexes used by the trending and stats aggregations';

    /**
     * Keyed by index name so a re-run is a no-op rather than a duplicate.
     *
     * @var array<string, array<string, int>>
     */
    private const INDEXES = [
        // getTrendingAnime: match on site + page_type + created_at, group page_id.
        'ahd_trending' => ['site' => 1, 'page_type' => 1, 'created_at' => -1],
        // getViewStats / getViewsOverTime / getTopReferrers: site + created_at.
        'ahd_site_created' => ['site' => 1, 'created_at' => -1],
    ];

    public function handle(): int
    {
        foreach (self::INDEXES as $name => $keys) {
            try {
                if ($this->option('drop')) {
                    PageView::raw(fn ($collection) => $collection->dropIndex($name));
                    $this->info("dropped {$name}");

                    continue;
                }

                PageView::raw(fn ($collection) => $collection->createIndex($keys, [
                    'name' => $name,
                    // Keep the collection readable and writable while it builds.
                    'background' => true,
                ]));
                $this->info("created {$name}: ".json_encode($keys));
            } catch (Throwable $e) {
                $this->error("{$name}: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
