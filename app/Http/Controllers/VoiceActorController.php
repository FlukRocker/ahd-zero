<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\VoiceActor;
use Illuminate\View\View;

class VoiceActorController extends Controller
{
    public function show(int $id): View
    {
        $voiceActor = VoiceActor::findOrFail($id);

        $anime = Anime::query()
            ->join('anime_character', 'yu_anime_catagory.cat_id', '=', 'anime_character.anime_id')
            ->where('anime_character.voice_actor_id', $voiceActor->id)
            ->select('yu_anime_catagory.cat_id', 'cat_title', 'cat_image', 'cat_type', 'cat_update', 'anime_status', 'episodes', 'anime_type')
            ->withCount('episodeList')
            ->distinct()
            ->orderByDesc('cat_update')
            ->paginate(24);

        return view('voice-actor', [
            'voiceActor' => [
                'id' => $voiceActor->id,
                'name' => $voiceActor->name,
                'name_japanese' => $voiceActor->name_japanese,
                'image_url' => $voiceActor->image_url,
                'language' => $voiceActor->language,
                'mal_id' => $voiceActor->mal_id,
            ],
            'anime' => $anime,
        ]);
    }
}
