<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Episode;
use App\Models\Member;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

use function collect;

class DashboardController extends Controller
{
    public function index(AnalyticsService $analytics): Response
    {
        $totalAnime = Anime::query()->count();
        $totalEpisodes = Episode::query()->count();
        $totalMembers = Member::query()->count();

        $animeByType = Anime::query()
            ->select('cat_type', DB::raw('count(*) as count'))
            ->groupBy('cat_type')
            ->pluck('count', 'cat_type')
            ->toArray();

        $recentAnime = Anime::query()
            ->select('cat_id', 'cat_title', 'cat_image', 'cat_type', 'cat_update', 'anime_status')
            ->orderByDesc('cat_update')
            ->limit(10)
            ->get();

        $recentMembers = Member::query()
            ->select('uuid', 'name', 'email', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $viewStats = $analytics->getViewStats();
        $trendingIds = $analytics->getTrendingAnime(7, 10);
        $trendingAnime = $trendingIds->isNotEmpty()
            ? Anime::query()
                ->whereIn('cat_id', $trendingIds->pluck('cat_id'))
                ->select('cat_id', 'cat_title', 'cat_image', 'cat_type')
                ->get()
                ->map(function (Anime $a) use ($trendingIds): array {
                    $views = $trendingIds->firstWhere('cat_id', $a->cat_id)['views'] ?? 0;

                    return [
                        'cat_id' => $a->cat_id,
                        'cat_title' => $a->cat_title,
                        'cat_image' => $a->cat_image,
                        'cat_type' => $a->cat_type,
                        'views' => $views,
                    ];
                })
                ->sortByDesc('views')
                ->values()
            : collect();

        $topReferrers = $analytics->getTopReferrers(30, 10);
        $viewsOverTime = $analytics->getViewsOverTime(30);

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalAnime' => $totalAnime,
                'totalEpisodes' => $totalEpisodes,
                'totalMembers' => $totalMembers,
                'animeByType' => [
                    'sub' => $animeByType[1] ?? 0,
                    'dub' => $animeByType[2] ?? 0,
                    'movie' => $animeByType[3] ?? 0,
                ],
                'views' => $viewStats,
            ],
            'recentAnime' => $recentAnime,
            'recentMembers' => $recentMembers,
            'trendingAnime' => $trendingAnime,
            'topReferrers' => $topReferrers,
            'viewsOverTime' => $viewsOverTime,
        ]);
    }
}
