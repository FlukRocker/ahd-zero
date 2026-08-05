<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class GenreController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $genre = Tag::query()
            ->where('type', 'genre')
            ->where('slug', $slug)
            ->first();

        abort_if($genre === null, 404);

        /** @var int $page */
        $page = $request->input('page', 1);

        $anime = Cache::remember("genre:{$genre->id}:page:{$page}", 300, fn () => Anime::query()
            ->select('cat_id', 'cat_title', 'cat_image', 'cat_type', 'cat_update', 'anime_status', 'episodes', 'anime_type')
            ->withCount('episodeList')
            ->whereHas('genres', fn ($q) => $q->where('tags.id', $genre->id))
            ->orderByDesc('cat_update')
            ->paginate(24));

        return view('genre', [
            'anime' => $anime,
            'genreName' => (string) ($genre->name_th ?: $genre->name),
            'genreSlug' => $genre->slug,
        ]);
    }
}
