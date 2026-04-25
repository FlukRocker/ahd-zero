<?php

namespace App\Services;

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
     * @return Collection<int, array{cat_id: int, views: int}>
     */
    public function getTrendingAnime(int $days = 7, int $limit = 10): Collection
    {
        return $this->safe(function () use ($days, $limit): Collection {
            $since = new UTCDateTime(now()->subDays($days));

            $pipeline = [
                ['$match' => [
                    'site' => $this->site,
                    'page_type' => 'anime',
                    'created_at' => ['$gte' => $since],
                    'page_id' => ['$ne' => null],
                ]],
                ['$group' => ['_id' => '$page_id', 'views' => ['$sum' => 1]]],
                ['$sort' => ['views' => -1]],
                ['$limit' => $limit],
            ];

            /** @var iterable<object{_id: mixed, views: int}> $results */
            $results = PageView::raw(fn ($collection) => $collection->aggregate($pipeline));

            return collect($results)->map(fn ($row): array => [
                'cat_id' => (int) $row->_id,
                'views' => (int) $row->views,
            ])->values();
        }, collect());
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
