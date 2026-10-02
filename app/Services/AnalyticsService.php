<?php

namespace App\Services;

use App\Models\Anime;
use App\Models\PageView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;
use Throwable;

use function collect;
use function config;
use function now;

class AnalyticsService
{
    /**
     * Site key all queries scope to. Lets multiple Laravel apps share one
     * Mongo `page_views` collection without leaking each other's traffic
     * into dashboards. Defaults to the host app's `app.site_key` config.
     */
    private string $site;

    public function __construct(?string $site = null)
    {
        $this->site = $site ?? (string) config('app.site_key');
    }

    /**
     * Build the trending aggregation. A null `$days` means all time: the
     * `created_at` clause is removed rather than widened, because a very old
     * `$gte` would still drop documents that have no `created_at` at all.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function trendingPipeline(?int $days, int $limit): array
    {
        $match = [
            'site' => $this->site,
            'page_type' => 'anime',
            'page_id' => ['$ne' => null],
        ];

        if ($days !== null) {
            $match['created_at'] = ['$gte' => new UTCDateTime(now()->subDays($days))];
        }

        return [
            ['$match' => $match],
            ['$group' => ['_id' => '$page_id', 'views' => ['$sum' => 1]]],
            ['$sort' => ['views' => -1]],
            ['$limit' => $limit],
        ];
    }

    /**
     * @return Collection<int, array{cat_id: int, views: int}>
     */
    public function getTrendingAnime(?int $days = 7, int $limit = 10): Collection
    {
        return $this->safe(function () use ($days, $limit): Collection {
            $pipeline = $this->trendingPipeline($days, $limit);

            /** @var iterable<object{_id: mixed, views: int}> $results */
            $results = PageView::raw(fn ($collection) => $collection->aggregate($pipeline));

            return collect($results)->map(fn ($row): array => [
                'cat_id' => (int) $row->_id,
                'views' => (int) $row->views,
            ])->values();
        }, collect());
    }

    /**
     * Hydrate the trending aggregate into card-shaped rows for the homepage
     * rail and the admin dashboard. Kept cache-free: callers cache with the
     * TTL that suits them.
     *
     * @return list<array{cat_id: int, cat_title: string, cat_image: string|null, cat_type: int, anime_status: string|null, episodes: int|null, anime_type: string|null, banner_md: string|null, cover_md: string|null, views: int}>
     */
    public function getTrendingCards(?int $days = 7, int $limit = 12): array
    {
        $trending = $this->getTrendingAnime($days, $limit);

        if ($trending->isEmpty()) {
            return [];
        }

        return $this->hydrateCards($trending->sortByDesc('views')->values()->all(), $limit);
    }

    /**
     * The homepage rail: ranked across the whole site network under the
     * settings on ahd-admin's Trending page (see TrendingNetwork). Falls back
     * to this site's own 7-day traffic when the network database is
     * unreachable.
     *
     * @return list<array<string, mixed>>
     */
    public function getNetworkTrendingCards(int $limit = 12): array
    {
        // Twice the rail: ids the catalog no longer has, or soft-deleted, drop
        // out while hydrating and must not leave the rail short.
        $ranked = $this->safe(
            fn (): array => (new TrendingNetwork($this->site))->rank($limit * 2),
            null,
        ) ?? $this->getTrendingAnime(7, $limit * 2)->all();

        return $ranked === [] ? [] : $this->hydrateCards($ranked, $limit);
    }

    /**
     * @param  array<int, array{cat_id: int, views: int}>  $ranked  best first
     * @return list<array<string, mixed>>
     */
    private function hydrateCards(array $ranked, int $limit): array
    {
        $position = array_flip(array_column($ranked, 'cat_id'));
        $views = array_column($ranked, 'views', 'cat_id');

        return Anime::query()
            ->whereIn('cat_id', array_keys($position))
            ->select('cat_id', 'cat_title', 'cat_image', 'cat_type', 'anime_status', 'episodes', 'anime_type', 'cat_banner')
            ->get()
            ->map(fn (Anime $a): array => [
                'cat_id' => (int) $a->cat_id,
                'cat_title' => (string) $a->cat_title,
                'cat_image' => $a->cat_image,
                'cat_type' => (int) $a->cat_type,
                'anime_status' => $a->anime_status,
                'episodes' => $a->episodes,
                'anime_type' => $a->anime_type,
                'banner_md' => $a->banner_md,
                'cover_md' => $a->cover_md,
                'views' => (int) ($views[$a->cat_id] ?? 0),
            ])
            // whereIn returns rows in database order — restore the ranking's.
            // It is not views alone once pins and blend weights apply.
            ->sortBy(fn (array $card): int => $position[$card['cat_id']])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{domain: string, count: int}>
     */
    public function getTopReferrers(int $days = 30, int $limit = 10): Collection
    {
        return $this->safe(function () use ($days, $limit): Collection {
            $since = new UTCDateTime(now()->subDays($days));

            $pipeline = [
                ['$match' => [
                    'site' => $this->site,
                    'created_at' => ['$gte' => $since],
                    'referrer_domain' => ['$nin' => [null, '']],
                ]],
                ['$group' => ['_id' => '$referrer_domain', 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]],
                ['$limit' => $limit],
            ];

            /** @var iterable<object{_id: mixed, count: int}> $results */
            $results = PageView::raw(fn ($collection) => $collection->aggregate($pipeline));

            return collect($results)->map(fn ($row): array => [
                'domain' => (string) $row->_id,
                'count' => (int) $row->count,
            ])->values();
        }, collect());
    }

    /**
     * @return array{total: int, today: int, week: int, month: int}
     */
    public function getViewStats(): array
    {
        return $this->safe(fn (): array => [
            'total' => PageView::query()->where('site', $this->site)->count(),
            'today' => PageView::query()
                ->where('site', $this->site)
                ->where('created_at', '>=', Carbon::today())
                ->count(),
            'week' => PageView::query()
                ->where('site', $this->site)
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
            'month' => PageView::query()
                ->where('site', $this->site)
                ->where('created_at', '>=', now()->subMonth())
                ->count(),
        ], ['total' => 0, 'today' => 0, 'week' => 0, 'month' => 0]);
    }

    /**
     * @return Collection<int, array{date: string, views: int}>
     */
    public function getViewsOverTime(int $days = 30): Collection
    {
        return $this->safe(function () use ($days): Collection {
            $since = new UTCDateTime(now()->subDays($days));

            $pipeline = [
                ['$match' => [
                    'site' => $this->site,
                    'created_at' => ['$gte' => $since],
                ]],
                ['$group' => [
                    '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
                    'views' => ['$sum' => 1],
                ]],
            ];

            /** @var iterable<object{_id: string, views: int}> $results */
            $results = PageView::raw(fn ($collection) => $collection->aggregate($pipeline));

            $byDate = collect($results)->mapWithKeys(fn ($row): array => [(string) $row->_id => (int) $row->views]);

            $allDays = collect();
            for ($i = $days; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $allDays->push([
                    'date' => $date,
                    'views' => $byDate->get($date, 0),
                ]);
            }

            /** @var Collection<int, array{date: string, views: int}> */
            return $allDays;
        }, collect());
    }

    /**
     * Wrap each Mongo call so that a downed Mongo (or missing collection)
     * degrades the admin dashboard to empty stats instead of a 500. Mongo
     * is opt-in infra — local dev / CI without it must still boot.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @param  T  $fallback
     * @return T
     */
    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
