<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Studio;
use App\Models\VoiceActor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    public function studios(Request $request): Response
    {
        $search = $request->input('q', '');
        $page = (int) $request->input('page', 1);
        $cacheKey = "dir:studios:{$search}:{$page}";

        $studios = Cache::remember($cacheKey, 600, function () use ($search) {
            $query = Studio::query()->select('id', 'name', 'name_japanese', 'mal_id');

            if ($search !== '' && $search !== null) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            return $query->orderBy('name')->paginate(48);
        });

        return Inertia::render('directory/Studios', [
            'studios' => $studios,
            'query' => $search,
        ]);
    }

    public function voiceActors(Request $request): Response
    {
        $search = $request->input('q', '');
        $page = (int) $request->input('page', 1);
        $cacheKey = "dir:va:{$search}:{$page}";

        $voiceActors = Cache::remember($cacheKey, 600, function () use ($search) {
            $query = VoiceActor::query()->select('id', 'name', 'name_japanese', 'image_url', 'language', 'mal_id');

            if ($search !== '' && $search !== null) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            return $query->orderBy('name')->paginate(48);
        });

        return Inertia::render('directory/VoiceActors', [
            'voiceActors' => $voiceActors,
            'query' => $search,
        ]);
    }

    public function staff(Request $request): Response
    {
        $search = $request->input('q', '');
        $page = (int) $request->input('page', 1);
        $cacheKey = "dir:staff:{$search}:{$page}";

        $staffList = Cache::remember($cacheKey, 600, function () use ($search) {
            $query = Staff::query()->select('id', 'name', 'name_japanese', 'image_url', 'mal_id');

            if ($search !== '' && $search !== null) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            return $query->orderBy('name')->paginate(48);
        });

        return Inertia::render('directory/Staff', [
            'staffList' => $staffList,
            'query' => $search,
        ]);
    }
}
