<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function makeAnime(string $title, int $type = 1): int
    {
        return DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => $title,
            'cat_type' => $type,
            'cat_update' => now(),
        ]);
    }

    public function test_category_page_renders_matching_anime_with_breadcrumb(): void
    {
        $this->makeAnime('Sub Anime One', 1);
        $this->makeAnime('Dub Anime Two', 2);

        $response = $this->get('/category/1');

        $response->assertOk();
        $response->assertViewIs('category');
        $response->assertSee('Sub Anime One', false);
        $response->assertSee('ซับไทย', false);
        // Breadcrumb JSON-LD present.
        $response->assertSee('BreadcrumbList', false);
        $response->assertDontSee('id="app" data-page', false);
    }

    public function test_search_page_is_noindex_and_shows_results(): void
    {
        $this->makeAnime('Findable Naruto Title', 1);

        $response = $this->get('/search/results?q=Naruto');

        $response->assertOk();
        $response->assertViewIs('search');
        $response->assertSee('Findable Naruto Title', false);
        $response->assertSee('noindex,follow', false);
    }

    public function test_studio_page_lists_studio_anime(): void
    {
        $animeId = $this->makeAnime('Studio Anime', 1);
        $studioId = DB::table('studios')->insertGetId(['name' => 'Studio Ghibli Test']);
        DB::table('anime_studio')->insert(['anime_id' => $animeId, 'studio_id' => $studioId, 'role' => 'studio']);

        $response = $this->get('/studio/'.$studioId);

        $response->assertOk();
        $response->assertViewIs('studio');
        $response->assertSee('Studio Ghibli Test', false);
        $response->assertSee('Studio Anime', false);
        $response->assertSee('BreadcrumbList', false);
    }

    public function test_voice_actor_page_lists_va_anime(): void
    {
        $animeId = $this->makeAnime('VA Anime', 1);
        $vaId = DB::table('voice_actors')->insertGetId(['name' => 'Mamoru Miyano Test', 'language' => 'Japanese']);
        DB::table('anime_character')->insert(['anime_id' => $animeId, 'voice_actor_id' => $vaId]);

        $response = $this->get('/voice-actor/'.$vaId);

        $response->assertOk();
        $response->assertViewIs('voice-actor');
        $response->assertSee('Mamoru Miyano Test', false);
        $response->assertSee('VA Anime', false);
    }

    public function test_directory_studios_renders_and_filters(): void
    {
        DB::table('studios')->insert(['name' => 'MAPPA Test']);
        DB::table('studios')->insert(['name' => 'Bones Test']);

        $all = $this->get('/studios');
        $all->assertOk();
        $all->assertViewIs('directory.studios');
        $all->assertSee('MAPPA Test', false);
        $all->assertSee('Bones Test', false);

        $filtered = $this->get('/studios?q=MAPPA');
        $filtered->assertOk();
        $filtered->assertSee('MAPPA Test', false);
        $filtered->assertDontSee('Bones Test', false);
    }

    public function test_directory_voice_actors_and_staff_render(): void
    {
        DB::table('voice_actors')->insert(['name' => 'VA Directory Test']);
        DB::table('staff')->insert(['name' => 'Staff Directory Test']);

        $va = $this->get('/voice-actors');
        $va->assertOk();
        $va->assertViewIs('directory.voice-actors');
        $va->assertSee('VA Directory Test', false);

        $staff = $this->get('/staff');
        $staff->assertOk();
        $staff->assertViewIs('directory.staff');
        $staff->assertSee('Staff Directory Test', false);
    }
}
