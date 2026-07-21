<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\FeaturedAnime;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    public function renderIndex()
    {
        /** @var int $page */
        $page = request()->input('page', 1);
        $cacheKey = "index:page:{$page}";

        // 60s TTL — admin updates from kurokami land within a minute. Was
        // 300s; users were seeing stale episode counts on the listing.
        $anime = Cache::remember($cacheKey, 60, function () {
            $result = Anime::query()->select('cat_id', 'cat_title', 'cat_image', 'cat_type', 'cat_update', 'anime_status', 'episodes', 'anime_type', 'cat_banner')
                ->withCount('episodeList')
                ->orderByDesc('cat_update')
                ->paginate(24);

            $result->getCollection()->transform(function (Anime $item): Anime {
                $item->setAttribute('banner_md', $item->banner_md);
                $item->setAttribute('cover_md', $item->cover_md);

                return $item;
            });

            return $result;
        });

        $recommended = Cache::remember('featured:recommended', 60, fn () => $this->getFeatured('recommended'));
        $popular = Cache::remember('featured:popular', 60, fn () => $this->getFeatured('popular'));

        return view('index', [
            'anime' => $anime,
            'recommended' => $recommended,
            'popular' => $popular,
        ]);
    }

    /**
     * @return list<array{cat_id: int, cat_title: string, cat_image: string|null, cat_type: int, anime_status: string|null, episodes: int|null, anime_type: string|null, banner_md: string|null, cover_md: string|null}>
     */
    private function getFeatured(string $type): array
    {
        return FeaturedAnime::query()
            ->where('type', $type)
            ->active()
            ->orderBy('sort_order')
            ->with('anime:cat_id,cat_title,cat_image,cat_type,anime_status,episodes,anime_type,cat_banner')
            ->get()
            ->filter(fn (FeaturedAnime $f): bool => $f->anime !== null)
            ->map(fn (FeaturedAnime $f): array => [
                'cat_id' => $f->anime->cat_id,
                'cat_title' => $f->anime->cat_title,
                'cat_image' => $f->anime->cat_image,
                'cat_type' => $f->anime->cat_type,
                'anime_status' => $f->anime->anime_status,
                'episodes' => $f->anime->episodes,
                'anime_type' => $f->anime->anime_type,
                'banner_md' => $f->anime->banner_md,
                'cover_md' => $f->anime->cover_md,
            ])
            ->values()
            ->all();
    }
}
