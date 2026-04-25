<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\AnimeRelation;
use App\Models\Character;
use App\Models\Episode;
use App\Models\Staff;
use App\Models\Studio;
use App\Models\Tag;
use App\Models\VoiceActor;
use App\Support\AdsBanner;
use App\Support\AdsFloating;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

use function abort_unless;
use function app;
use function collect;
use function redirect;

class AnimeController extends Controller
{
    public function show(int $id): Response
    {
        /** @var array<string, mixed> $animeData */
        $animeData = Cache::remember("anime:detail:v2:{$id}", 600, fn () => $this->buildAnimeDetail($id));

        return Inertia::render('Anime', [
            'anime' => $animeData,
            'adsBanners' => AdsBanner::all(),
            'floatingAds' => AdsFloating::all(),
        ]);
    }

    /**
     * Safely load a relation. Returns empty collection if the underlying
     * pivot/relation table is missing or any other DB error occurs — better
     * than nuking the whole detail page over a missing aux table.
     */
    private function safe(callable $fn): Collection
    {
        try {
            $result = $fn();

            if ($result instanceof Collection) {
                return $result;
            }

            return collect();
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAnimeDetail(int $id): array
    {
        $anime = Anime::query()->findOrFail($id);

        // Eager load episodes (cheap, same DB) — fail-soft if list table absent
        $episodeList = $this->safe(fn () => $anime->episodeList()->get());

        $genres = $this->safe(fn () => $anime->genres()->get())->map(fn (Tag $g): array => [
            'id' => $g->id,
            'name' => $g->name,
            'name_th' => $g->name_th,
            'slug' => $g->slug,
        ])->values();

        $studios = $this->safe(fn () => $anime->studios()->get())
            ->map(fn (Studio $s): array => ['id' => $s->id, 'name' => $s->name])->values();

        $producers = $this->safe(fn () => $anime->producers()->get())
            ->map(fn (Studio $s): array => ['id' => $s->id, 'name' => $s->name])->values();

        $licensors = $this->safe(fn () => $anime->licensors()->get())
            ->map(fn (Studio $s): array => ['id' => $s->id, 'name' => $s->name])->values();

        $characters = $this->safe(fn () => $anime->characters()->get())
            ->map(function (Character $character): array {
                $pivot = $character->getRelation('pivot');
                $voiceActorId = $pivot->voice_actor_id ?? null;

                $voiceActor = $voiceActorId
                    ? VoiceActor::query()->find($voiceActorId)
                    : null;

                return [
                    'id' => $character->id,
                    'name' => $character->name,
                    'name_japanese' => $character->name_japanese,
                    'image_url' => $character->image_url,
                    'role' => $pivot->character_role ?? null,
                    'voice_actor' => $voiceActor instanceof VoiceActor ? [
                        'id' => $voiceActor->id,
                        'name' => $voiceActor->name,
                        'name_japanese' => $voiceActor->name_japanese,
                        'image_url' => $voiceActor->image_url,
                        'language' => $voiceActor->language,
                    ] : null,
                ];
            })->values();

        $staffMembers = $this->safe(fn () => $anime->staff()->get())
            ->map(function (Staff $s): array {
                $pivot = $s->getRelation('pivot');

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'name_japanese' => $s->name_japanese,
                    'image_url' => $s->image_url,
                    'position' => $pivot->position ?? null,
                ];
            })->values();

        $seriesAnime = $this->safe(function () use ($anime): Collection {
            $series = $anime->series;
            if ($series === null) {
                return collect();
            }

            return $series->anime->map(fn (Anime $a): array => [
                'cat_id' => $a->cat_id,
                'cat_title' => $a->cat_title,
                'cat_image' => $a->cat_image,
                'cat_type' => $a->cat_type,
                'series_order' => $a->series_order,
            ])->values();
        });

        $relations = $this->safe(fn () => $anime->relations()->with('relatedAnime')->get());
        $relatedAnime = $relations->map(fn (AnimeRelation $rel): array => [
            'id' => $rel->id,
            'relation_type' => $rel->relation_type,
            'related_title' => $rel->relatedAnime->cat_title ?? $rel->related_title,
            'related_image' => $rel->relatedAnime?->cat_image,
            'related_anime_id' => $rel->related_anime_id,
            'related_mal_id' => $rel->related_mal_id,
        ])->values();

        return [
            'cat_id' => $anime->cat_id,
            'cat_title' => $anime->cat_title,
            'cat_desc' => $anime->cat_desc,
            'cat_image' => $anime->cat_image,
            'cat_type' => $anime->cat_type,
            'cat_update' => $anime->cat_update,
            'banner_original' => $anime->banner_original,
            'banner_md' => $anime->banner_md,
            'cover_md' => $anime->cover_md,
            'cover_th' => $anime->cover_th,
            'title_english' => $anime->title_english,
            'title_japanese' => $anime->title_japanese,
            'title_synonyms' => $anime->title_synonyms,
            'anime_type' => $anime->anime_type,
            'episodes' => $anime->episodes,
            'anime_status' => $anime->anime_status,
            'aired_from' => $anime->aired_from?->format('M d, Y'),
            'aired_to' => $anime->aired_to?->format('M d, Y'),
            'aired_from_iso' => $anime->aired_from?->format('Y-m-d'),
            'aired_to_iso' => $anime->aired_to?->format('Y-m-d'),
            'cat_update_iso' => $anime->cat_update?->toIso8601String(),
            'premiered_season' => $anime->premiered_season,
            'premiered_year' => $anime->premiered_year,
            'broadcast' => $anime->broadcast,
            'source' => $anime->source,
            'duration' => $anime->duration,
            'rating' => $anime->rating,
            'mal_id' => $anime->mal_id,
            'opening_themes' => $anime->opening_themes ?? [],
            'ending_themes' => $anime->ending_themes ?? [],
            'review_url' => $anime->review_url,
            'anime_slug' => $anime->anime_slug,
            'genres' => $genres,
            'studios' => $studios,
            'producers' => $producers,
            'licensors' => $licensors,
            'characters' => $characters,
            'staff' => $staffMembers,
            'episode_list' => $episodeList->map(fn (Episode $ep): array => [
                'list_id' => $ep->list_id,
                'list_title' => $ep->list_title,
                'uuid' => $ep->uuid,
            ])->values(),
            'series_anime' => $seriesAnime,
            'related_anime' => $relatedAnime,
        ];
    }

    public function episode(int $id, int $listId): Response
    {
        $anime = Anime::query()->findOrFail($id);

        $episodeList = $this->safe(fn () => $anime->episodeList()->get());

        $currentEpisode = $episodeList->firstWhere('list_id', $listId);

        abort_unless($currentEpisode !== null, 404);

        $episodes = $episodeList->map(fn (Episode $ep): array => [
            'list_id' => $ep->list_id,
            'list_title' => $ep->list_title,
            'uuid' => $ep->uuid,
        ])->values();

        $relations = $this->safe(fn () => $anime->relations()->with('relatedAnime')->get());

        $relatedFromDb = $relations
            ->filter(fn (AnimeRelation $rel): bool => $rel->relatedAnime !== null)
            ->map(fn (AnimeRelation $rel): array => [
                'cat_id' => $rel->relatedAnime->cat_id,
                'cat_title' => $rel->relatedAnime->cat_title,
                'cat_image' => $rel->relatedAnime->cat_image,
                'cat_type' => $rel->relatedAnime->cat_type,
                'relation_type' => $rel->relation_type,
            ]);

        $excludeIds = $relatedFromDb->pluck('cat_id')->push($anime->cat_id)->toArray();
        $remaining = 6 - $relatedFromDb->count();

        $recommendedAnime = $remaining > 0
            ? Anime::query()
                ->whereNotIn('cat_id', $excludeIds)
                ->select('cat_id', 'cat_title', 'cat_image', 'cat_type')
                ->orderByDesc('cat_update')
                ->limit($remaining)
                ->get()
                ->map(fn (Anime $a): array => [
                    'cat_id' => $a->cat_id,
                    'cat_title' => $a->cat_title,
                    'cat_image' => $a->cat_image,
                    'cat_type' => $a->cat_type,
                    'relation_type' => null,
                ])
            : collect();

        $allRelated = $relatedFromDb->concat($recommendedAnime)->take(6);

        // Drive ID → akuma-player.xyz/play/{uid} (mirrors kurokami exactly).
        // Do NOT fall back to the raw Drive URL — Google refuses to iframe it,
        // which produces a broken/blocked embed instead of a working player.
        $playerService = app(\App\Services\PlayerService::class);
        $playerUrl = $playerService->getPlayerUrl($currentEpisode->list_url ?? null)
            ?? $playerService->getPlayerUrl($currentEpisode->file_src ?? null);

        return Inertia::render('Episode', [
            'anime' => [
                'cat_id' => $anime->cat_id,
                'cat_title' => $anime->cat_title,
                'cat_image' => $anime->cat_image,
                'cat_type' => $anime->cat_type,
                'cat_desc' => $anime->cat_desc,
                'anime_type' => $anime->anime_type,
                'anime_status' => $anime->anime_status,
                'banner_md' => $anime->banner_md,
                'cover_md' => $anime->cover_md,
            ],
            'currentEpisode' => [
                'list_id' => $currentEpisode->list_id,
                'list_title' => $currentEpisode->list_title,
                'uuid' => $currentEpisode->uuid,
                'player_url' => $playerUrl,
                'upload_date_iso' => $currentEpisode->adddate?->toIso8601String(),
            ],
            'episodes' => $episodes,
            'relatedAnime' => $allRelated->values(),
            'adsBanners' => AdsBanner::all(),
            'floatingAds' => AdsFloating::all(),
        ]);
    }

    /**
     * Legacy v1 redirect: /watch/{list_id} → /anime/{cat_id}/episode/{list_id}
     *
     * Tolerates v1 shortlink garbage like /watch/12345&mirror=true — pulls
     * the numeric prefix out of whatever segment was passed.
     */
    public function legacyWatch(string $listId): \Illuminate\Http\RedirectResponse
    {
        if (! preg_match('/^(\d+)/', $listId, $m)) {
            abort(404);
        }
        $id = (int) $m[1];

        $episode = Episode::where('list_id', $id)->firstOrFail();

        return redirect("/anime/{$episode->catagory_id}/episode/{$episode->list_id}", 301);
    }
}
