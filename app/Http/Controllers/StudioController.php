<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Studio;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function show(int $id): View
    {
        $studio = Studio::findOrFail($id);

        $anime = Anime::query()
            ->join('anime_studio', 'yu_anime_catagory.cat_id', '=', 'anime_studio.anime_id')
            ->where('anime_studio.studio_id', $studio->id)
            ->select('yu_anime_catagory.cat_id', 'cat_title', 'cat_image', 'cat_type', 'cat_update', 'anime_status', 'episodes', 'anime_type')
            ->withCount('episodeList')
            ->distinct()
            ->orderByDesc('cat_update')
            ->paginate(24);

        return view('studio', [
            'studio' => [
                'id' => $studio->id,
                'name' => $studio->name,
                'name_japanese' => $studio->name_japanese,
                'mal_id' => $studio->mal_id,
            ],
            'anime' => $anime,
        ]);
    }
}
