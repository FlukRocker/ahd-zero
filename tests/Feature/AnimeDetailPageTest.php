<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnimeDetailPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_anime_detail_renders_server_side_with_episodes_and_schema(): void
    {
        $id = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Detailed Anime Title',
            'cat_desc' => '<p>A great synopsis for the anime.</p>',
            'cat_type' => 1,
            'anime_status' => 'Finished Airing',
            'episodes' => 12,
            'cat_update' => now(),
        ]);
        DB::table('yu_anime_list')->insert([
            ['catagory_id' => $id, 'list_title' => 'ตอนที่ 1', 'uuid' => 'u1', 'adddate' => now()],
            ['catagory_id' => $id, 'list_title' => 'ตอนที่ 2', 'uuid' => 'u2', 'adddate' => now()],
        ]);

        $response = $this->get('/anime/'.$id);

        $response->assertOk();
        $response->assertViewIs('anime');
        // Server-rendered content.
        $response->assertSee('Detailed Anime Title', false);
        $response->assertSee('A great synopsis for the anime.', false);
        // Episode links to the watch route.
        $response->assertSee('/anime/'.$id.'/episode/', false);
        $response->assertSee('ตอนที่ 1', false);
        // SEO: TVSeries + breadcrumb JSON-LD, and no Inertia shell.
        $response->assertSee('TVSeries', false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertDontSee('id="app" data-page', false);
    }

    public function test_missing_anime_returns_404(): void
    {
        $this->get('/anime/99999')->assertStatus(404);
    }
}
