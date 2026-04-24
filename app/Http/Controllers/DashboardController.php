<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Episode;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
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
            ],
            'recentAnime' => $recentAnime,
            'recentMembers' => $recentMembers,
        ]);
    }
}
