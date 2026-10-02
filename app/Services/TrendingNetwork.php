<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use RuntimeException;
use Throwable;

use function array_slice;
use function config;
use function max;
use function min;
use function now;
use function report;
use function usort;

/**
 * Trending across every site in the network.
 *
 * Each site aggregates its OWN page_views into a small snapshot — its top
 * anime and the window's total — and publishes it to the shared `network`
 * Mongo database. Ranking reads every site's snapshot and blends this site's
 * share of views with the rest of the network's, under settings edited
 * centrally on ahd-admin's Trending page.
 *
 * Shares, not raw counts: one site can have a thousand times another's
 * traffic, and raw counts would let it decide every other site's rail.
 *
 *   score = (1 - w) * own_share + w * network_share
 *
 * where network_share is the source-weighted mean of the other sites' shares.
 * A site with too little traffic of its own (cold start) ranks purely by the
 * network until it has enough.
 *
 * No site ever reads another site's page_views: the only cross-site data is
 * the snapshots, so each site's Mongo user needs readWrite on `network` and
 * nothing else.
 *
 * Sites that show a trending rail call rank(); a site without one (kurokami-v2)
 * only publishes, so its traffic still counts for the others.
 *
 * KEEP IDENTICAL in ahd-v2, neko-miku, anime-hana and kurokami-v2. ahd-admin
 * carries the same resolveSettings() and blend() for its preview. Change them
 * together.
 */
final class TrendingNetwork
{
    public const SNAPSHOTS = 'trending_snapshots';

    public const SETTINGS = 'trending_settings';

    /**
     * What a site gets when neither the `global` document nor its own
     * override says otherwise.
     *
     * @var array{mode: string, network_weight: float, days: int, cold_start_min_views: int, refresh_minutes: int, source_weights: array<string, float>, pinned: list<int>, excluded: list<int>, refresh_requested_at: int|null}
     */
    public const DEFAULTS = [
        // own | blend | network
        'mode' => 'blend',
        'network_weight' => 0.3,
        'days' => 7,
        'cold_start_min_views' => 200,
        'refresh_minutes' => 15,
        // site => weight in the network share. Missing means 1, 0 ignores it.
        'source_weights' => [],
        'pinned' => [],
        'excluded' => [],
        // Unix seconds. A snapshot older than this is republished on the next
        // read — the admin's "refresh now", since ahd-admin cannot run another
        // site's aggregation itself.
        'refresh_requested_at' => null,
    ];

    /** Enough depth that a site's own filters still leave a full rail. */
    private const SNAPSHOT_SIZE = 300;

    /** A site that stopped publishing drops out of everyone's network share. */
    private const MAX_SOURCE_AGE_SECONDS = 172800;

    private const SETTINGS_TTL = 60;

    private readonly string $site;

    public function __construct(?string $site = null)
    {
        $this->site = $site ?? (string) config('app.site_key');
    }

    public function site(): string
    {
        return $this->site;
    }

    /**
     * Effective settings for this site, cached briefly so a page render does
     * not round-trip to Mongo. Defaults when the network is unreachable.
     *
     * @return array{mode: string, network_weight: float, days: int, cold_start_min_views: int, refresh_minutes: int, source_weights: array<string, float>, pinned: list<int>, excluded: list<int>, refresh_requested_at: int|null}
     */
    public function settings(): array
    {
        return Cache::remember("trending:settings:{$this->site}", self::SETTINGS_TTL, function (): array {
            try {
                $docs = [];
                foreach ($this->collection(self::SETTINGS)->find(['_id' => ['$in' => ['global', $this->site]]]) as $doc) {
                    $docs[(string) $doc['_id']] = (array) $doc;
                }

                return self::resolveSettings($docs['global'] ?? [], $docs[$this->site] ?? []);
            } catch (Throwable $e) {
                report($e);

                return self::DEFAULTS;
            }
        });
    }

    /**
     * Ranked cat_ids for this site, best first. Republishes this site's own
     * snapshot first when it is missing, stale or out of date with settings.
     *
     * @return list<array{cat_id: int, score: float, views: int}>
     */
    public function rank(int $limit): array
    {
        $settings = $this->settings();
        $snapshots = $this->snapshots();

        if ($this->needsPublish($snapshots[$this->site] ?? null, $settings)) {
            // Non-blocking: if another request is already publishing, rank on
            // what is there rather than queue behind it.
            $fresh = Cache::lock("trending:publish:{$this->site}", 120)->get(fn (): array => $this->publish($settings));

            if ($fresh) {
                $snapshots[$this->site] = $fresh;
            }
        }

        return self::blend($this->site, $settings, $snapshots, $limit, now()->getTimestamp());
    }

    /**
     * Aggregate this site's page_views over the configured window and replace
     * its snapshot in the network.
     *
     * @param  array{days: int}|null  $settings
     * @return array{site: string, days: int, total: int, computed_at: int, items: array<int, int>}
     */
    public function publish(?array $settings = null): array
    {
        $days = ($settings ?? $this->settings())['days'];

        $pipeline = [
            // Same leading stages as the per-site trending query, so the
            // ahd_trending_covered index answers them without fetching.
            ['$match' => [
                'site' => $this->site,
                'page_type' => 'anime',
                'created_at' => ['$gte' => new UTCDateTime(now()->subDays($days))],
            ]],
            ['$group' => ['_id' => '$page_id', 'views' => ['$sum' => 1]]],
            ['$match' => ['_id' => ['$ne' => null]]],
            ['$facet' => [
                'top' => [['$sort' => ['views' => -1]], ['$limit' => self::SNAPSHOT_SIZE]],
                'total' => [['$group' => ['_id' => null, 'views' => ['$sum' => '$views']]]],
            ]],
        ];

        $result = $this->localPageViews()->aggregate($pipeline)->toArray()[0] ?? null;

        $items = [];
        foreach ($result['top'] ?? [] as $row) {
            // page_id has been written as both int and numeric string over the
            // years; they are the same anime.
            $id = (int) $row['_id'];
            if ($id > 0) {
                $items[$id] = ($items[$id] ?? 0) + (int) $row['views'];
            }
        }

        $snapshot = [
            'site' => $this->site,
            'days' => $days,
            'total' => (int) ($result['total'][0]['views'] ?? 0),
            'computed_at' => now()->getTimestamp(),
            'items' => $items,
        ];

        $list = [];
        foreach ($items as $id => $views) {
            $list[] = ['id' => $id, 'v' => $views];
        }

        $this->collection(self::SNAPSHOTS)->replaceOne(
            ['_id' => $this->site],
            [
                'site' => $this->site,
                'days' => $days,
                'total' => $snapshot['total'],
                'computed_at' => new UTCDateTime($snapshot['computed_at'] * 1000),
                'items' => $list,
            ],
            ['upsert' => true],
        );

        return $snapshot;
    }

    /**
     * Every site's snapshot, keyed by site.
     *
     * @return array<string, array{site: string, days: int, total: int, computed_at: int, items: array<int, int>}>
     */
    public function snapshots(): array
    {
        $snapshots = [];

        foreach ($this->collection(self::SNAPSHOTS)->find() as $doc) {
            $items = [];
            foreach ($doc['items'] ?? [] as $item) {
                $items[(int) $item['id']] = (int) $item['v'];
            }

            $computedAt = $doc['computed_at'] ?? null;

            $snapshots[(string) $doc['_id']] = [
                'site' => (string) $doc['_id'],
                'days' => (int) ($doc['days'] ?? 0),
                'total' => (int) ($doc['total'] ?? 0),
                'computed_at' => $computedAt instanceof UTCDateTime ? $computedAt->toDateTime()->getTimestamp() : 0,
                'items' => $items,
            ];
        }

        return $snapshots;
    }

    /**
     * Defaults, then `global`, then the site's own override. Only keys that
     * are present override, so a site document can change one thing.
     *
     * @param  array<string, mixed>  $global
     * @param  array<string, mixed>  $override
     * @return array{mode: string, network_weight: float, days: int, cold_start_min_views: int, refresh_minutes: int, source_weights: array<string, float>, pinned: list<int>, excluded: list<int>, refresh_requested_at: int|null}
     */
    public static function resolveSettings(array $global, array $override): array
    {
        $merged = self::DEFAULTS;

        foreach ([$global, $override] as $layer) {
            foreach (self::DEFAULTS as $key => $_) {
                if (isset($layer[$key])) {
                    $merged[$key] = $layer[$key];
                }
            }
        }

        $weights = [];
        foreach ((array) $merged['source_weights'] as $site => $weight) {
            $weights[(string) $site] = max(0.0, (float) $weight);
        }

        $requested = $merged['refresh_requested_at'];
        if ($requested instanceof UTCDateTime) {
            $requested = $requested->toDateTime()->getTimestamp();
        }

        return [
            'mode' => in_array($merged['mode'], ['own', 'blend', 'network'], true) ? $merged['mode'] : 'blend',
            'network_weight' => min(1.0, max(0.0, (float) $merged['network_weight'])),
            'days' => min(90, max(1, (int) $merged['days'])),
            'cold_start_min_views' => max(0, (int) $merged['cold_start_min_views']),
            'refresh_minutes' => min(1440, max(1, (int) $merged['refresh_minutes'])),
            'source_weights' => $weights,
            'pinned' => self::ids($merged['pinned']),
            'excluded' => self::ids($merged['excluded']),
            'refresh_requested_at' => $requested === null ? null : (int) $requested,
        ];
    }

    /**
     * The ranking itself, free of I/O so it can be tested and previewed.
     *
     * @param  array{mode: string, network_weight: float, cold_start_min_views: int, source_weights: array<string, float>, pinned: list<int>, excluded: list<int>}  $settings
     * @param  array<string, array{total: int, computed_at: int, items: array<int, int>}>  $snapshots
     * @return list<array{cat_id: int, score: float, views: int}>
     */
    public static function blend(string $site, array $settings, array $snapshots, int $limit, int $now): array
    {
        $own = $snapshots[$site] ?? ['total' => 0, 'items' => []];

        // Other sites' shares, each scaled by its source weight.
        $network = [];
        $networkViews = [];
        $weightSum = 0.0;

        foreach ($snapshots as $source => $snapshot) {
            $weight = $settings['source_weights'][$source] ?? 1.0;

            if ($source === $site || $weight <= 0 || $snapshot['total'] <= 0
                || $now - $snapshot['computed_at'] > self::MAX_SOURCE_AGE_SECONDS) {
                continue;
            }

            $weightSum += $weight;
            foreach ($snapshot['items'] as $id => $views) {
                $network[$id] = ($network[$id] ?? 0.0) + $weight * $views / $snapshot['total'];
                $networkViews[$id] = ($networkViews[$id] ?? 0) + $views;
            }
        }

        $w = match ($settings['mode']) {
            'own' => 0.0,
            'network' => 1.0,
            default => $settings['network_weight'],
        };

        if ($settings['mode'] !== 'own' && $own['total'] < $settings['cold_start_min_views']) {
            $w = 1.0;
        }

        // Nothing to blend with: fall back to this site's own traffic.
        if ($weightSum <= 0) {
            $w = 0.0;
        }

        // The view count shown beside a title counts only what ranked it.
        if ($w <= 0) {
            $networkViews = [];
        }

        $scores = [];
        foreach ($own['items'] as $id => $views) {
            $scores[$id] = (1 - $w) * $views / max(1, $own['total']);
        }
        foreach ($network as $id => $share) {
            $scores[$id] = ($scores[$id] ?? 0.0) + $w * $share / $weightSum;
        }

        $excluded = array_flip($settings['excluded']);
        $pinned = array_flip($settings['pinned']);

        $ranked = [];
        foreach ($scores as $id => $score) {
            if ($score > 0 && ! isset($excluded[$id]) && ! isset($pinned[$id])) {
                $ranked[] = ['cat_id' => $id, 'score' => $score, 'views' => ($own['items'][$id] ?? 0) + ($networkViews[$id] ?? 0)];
            }
        }

        // Ties on score break on views, then id, so the order is stable
        // between renders.
        usort($ranked, fn (array $a, array $b): int => [$b['score'], $b['views'], $a['cat_id']] <=> [$a['score'], $a['views'], $b['cat_id']]);

        $top = [];
        foreach ($settings['pinned'] as $id) {
            if (! isset($excluded[$id])) {
                $top[] = ['cat_id' => $id, 'score' => PHP_FLOAT_MAX, 'views' => ($own['items'][$id] ?? 0) + ($networkViews[$id] ?? 0)];
            }
        }

        return array_slice([...$top, ...$ranked], 0, $limit);
    }

    /**
     * @param  array{days: int, refresh_minutes: int, refresh_requested_at: int|null}  $settings
     * @param  array{days: int, computed_at: int}|null  $snapshot
     */
    private function needsPublish(?array $snapshot, array $settings): bool
    {
        if ($snapshot === null || $snapshot['days'] !== $settings['days']) {
            return true;
        }

        $age = now()->getTimestamp() - $snapshot['computed_at'];

        return $age > $settings['refresh_minutes'] * 60
            || ($settings['refresh_requested_at'] !== null && $snapshot['computed_at'] < $settings['refresh_requested_at']);
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        $ids = [];
        foreach ((array) $value as $id) {
            if ((int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function collection(string $name): Collection
    {
        return $this->mongo('network')->getCollection($name);
    }

    private function localPageViews(): Collection
    {
        return $this->mongo('mongodb')->getCollection('page_views');
    }

    private function mongo(string $name): Connection
    {
        $connection = DB::connection($name);

        if (! $connection instanceof Connection) {
            throw new RuntimeException("The {$name} connection is not a MongoDB\Laravel\Connection.");
        }

        return $connection;
    }
}
