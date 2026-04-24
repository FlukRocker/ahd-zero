<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use function response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $animes = Anime::query()->where('cat_title', 'LIKE', '%'.$validated['q'].'%')
            ->select('cat_id', 'cat_title', 'cat_image', 'cat_type', 'anime_status', 'episodes', 'anime_type')
            ->withCount('episodeList')
            ->paginate(24)
            ->appends(['q' => $validated['q']]);

        return Inertia::render('Search', [
            'animes' => $animes,
            'query' => $validated['q'],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $results = Anime::query()->where('cat_title', 'LIKE', '%'.$validated['q'].'%')
            ->select('cat_id', 'cat_title', 'cat_image', 'cat_type')
            ->orderByDesc('cat_id')
            ->limit(10)
            ->get();

        return response()->json($results);
    }
}
