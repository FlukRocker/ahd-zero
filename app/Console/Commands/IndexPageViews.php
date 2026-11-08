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
        // page_id is in the key so the aggregation is covered by the index —
        // without it Mongo fetches every matching document just to read
        // page_id, and the all-time window fetches every anime view ever.
        'ahd_trending_covered' => ['site' => 1, 'page_type' => 1, 'created_at' => -1, 'page_id' => 1],
        // getViewStats / getViewsOverTime / getTopReferrers: site + created_at.
        'ahd_site_created' => ['site' => 1, 'created_at' => -1],
    ];

    /**
     * Indexes this command used to create and has since replaced. Dropped only
     * after the replacements exist, so the queries are never left unindexed.
     *
     * @var list<string>
     */
    private const SUPERSEDED = [
        // Uncovered: forced a document fetch per view (see ahd_trending_covered).
        'ahd_trending',
    ];

    public function handle(): int
    {
        $status = $this->option('drop') ? $this->dropManaged() : $this->createManaged();

        if ($status !== self::SUCCESS) {
            return $status;
        }

        return $this->dropSuperseded();
    }

    private function createManaged(): int
    {
        return $this->eachIndex(self::INDEXES, function (string $name, array $keys): void {
            PageView::raw(fn ($collection) => $collection->createIndex($keys, [
                'name' => $name,
                // Keep the collection readable and writable while it builds.
                'background' => true,
            ]));
            $this->info("created {$name}: ".json_encode($keys));
        });
    }

    private function dropManaged(): int
    {
        return $this->eachIndex(self::INDEXES, fn (string $name) => $this->dropIfPresent($name));
    }

    private function dropSuperseded(): int
    {
        return $this->eachIndex(array_flip(self::SUPERSEDED), fn (string $name) => $this->dropIfPresent($name));
    }

    private function dropIfPresent(string $name): void
    {
        $existing = PageView::raw(fn ($collection) => iterator_to_array($collection->listIndexes()));

        foreach ($existing as $index) {
            if ($index->getName() === $name) {
                PageView::raw(fn ($collection) => $collection->dropIndex($name));
                $this->info("dropped {$name}");

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $indexes
     * @param  callable(string, mixed): void  $fn
     */
    private function eachIndex(array $indexes, callable $fn): int
    {
        foreach ($indexes as $name => $keys) {
            try {
                $fn($name, $keys);
            } catch (Throwable $e) {
                $this->error("{$name}: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
