<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EpisodePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function seedAnimeWithEpisodes(): array
    {
        $animeId = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Player Anime',
            'cat_desc' => '<p>Synopsis.</p>',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
        $ep1 = DB::table('yu_anime_list')->insertGetId([
            'catagory_id' => $animeId, 'list_title' => 'ตอนที่ 1',
            'uuid' => 'uuid-1', 'list_url' => 'https://example.com/watch/one', 'adddate' => now(),
        ]);
        $ep2 = DB::table('yu_anime_list')->insertGetId([
            'catagory_id' => $animeId, 'list_title' => 'ตอนที่ 2',
            'uuid' => 'uuid-2', 'list_url' => 'https://example.com/watch/two', 'adddate' => now(),
        ]);

        return [$animeId, $ep1, $ep2];
    }

    public function test_episode_page_renders_player_and_sidebar_and_schema(): void
    {
        [$animeId, $ep1, $ep2] = $this->seedAnimeWithEpisodes();

        $response = $this->get("/anime/{$animeId}/episode/{$ep1}");

        $response->assertOk();
        $response->assertViewIs('episode');
        $response->assertSee('Player Anime', false);
        $response->assertSee('ตอนที่ 1', false);
        // Player iframe with the mode toggle (Alpine).
        $response->assertSee('<iframe', false);
        $response->assertSee('ตัวเล่นหลัก', false);
        $response->assertSee('ตัวเล่นสำรอง', false);
        // Sidebar lists episode 2, and next link points to it.
        $response->assertSee("/anime/{$animeId}/episode/{$ep2}", false);
        // VideoObject + breadcrumb JSON-LD, no Inertia shell.
        $response->assertSee('VideoObject', false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertDontSee('id="app" data-page', false);
    }

    public function test_missing_episode_returns_404(): void
    {
        [$animeId] = $this->seedAnimeWithEpisodes();
        $this->get("/anime/{$animeId}/episode/99999")->assertStatus(404);
    }
}
