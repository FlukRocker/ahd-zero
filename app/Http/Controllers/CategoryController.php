<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * @var array<int, string>
     */
    private array $typeNames = [
        1 => 'ซับไทย',
        2 => 'พากย์ไทย',
        3 => 'เดอะมูฟวี่',
    ];

    public function index(Request $request, string $type): Response
    {
        $typeInt = (int) $type;
        /** @var int $page */
        $page = $request->input('page', 1);
        $cacheKey = "category:{$typeInt}:page:{$page}";

        $anime = Cache::remember($cacheKey, 300, function () use ($typeInt) {
            $query = Anime::query()->select('cat_id', 'cat_title', 'cat_image', 'cat_type', 'cat_update', 'anime_status', 'episodes', 'anime_type')
                ->withCount('episodeList')
                ->orderByDesc('cat_update');

            if (isset($this->typeNames[$typeInt])) {
                $query->where('cat_type', $typeInt);
            }

            return $query->paginate(24);
        });

        $categoryName = $this->typeNames[$typeInt] ?? 'อนิเมะทั้งหมด';

        return Inertia::render('Category', [
            'anime' => $anime,
            'categoryName' => $categoryName,
            'currentType' => $type,
        ]);
    }
}
