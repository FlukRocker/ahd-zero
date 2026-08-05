<?php

namespace Tests\Feature;

use App\Models\Anime;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class GenrePageTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->instance(AnalyticsService::class, new EmptyAnalytics);

        DB::table('tags')->insert([
            ['id' => 1, 'name' => 'Action', 'slug' => 'action', 'type' => 'genre', 'name_th' => 'แอคชั่น', 'order_column' => 1],
            ['id' => 2, 'name' => 'Romance', 'slug' => 'romance', 'type' => 'genre', 'name_th' => 'โรแมนซ์', 'order_column' => 2],
        ]);
    }

    private function seedAnime(int $catId, string $title): void
    {
        DB::table('yu_anime_catagory')->insert([
            'cat_id' => $catId,
            'cat_title' => $title,
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
    }

    private function tagAnime(int $catId, int $tagId): void
    {
        DB::table('taggables')->insert([
            'tag_id' => $tagId,
            'taggable_id' => $catId,
            'taggable_type' => Anime::class,
        ]);
    }

    public function test_genre_page_lists_only_anime_carrying_that_tag(): void
    {
        $this->seedAnime(601, 'Punchy Show');
        $this->seedAnime(602, 'Kissy Show');
        $this->tagAnime(601, 1);
        $this->tagAnime(602, 2);

        $response = $this->get('/genre/action');

        $response->assertOk();
        $response->assertSee('Punchy Show', false);
        $response->assertDontSee('Kissy Show', false);
    }

    public function test_genre_page_shows_the_thai_genre_name_as_its_heading(): void
    {
        $this->seedAnime(601, 'Punchy Show');
        $this->tagAnime(601, 1);

        $this->get('/genre/action')->assertSee('แอคชั่น', false);
    }

    public function test_unknown_genre_slug_returns_404(): void
    {
        $this->get('/genre/does-not-exist')->assertNotFound();
    }

    public function test_a_non_genre_tag_slug_is_not_reachable_as_a_genre(): void
    {
        DB::table('tags')->insert([
            'id' => 3, 'name' => 'Summer 2026', 'slug' => 'summer-2026', 'type' => 'season', 'name_th' => null, 'order_column' => 1,
        ]);

        $this->get('/genre/summer-2026')->assertNotFound();
    }

    public function test_genre_with_no_anime_renders_an_empty_state_rather_than_erroring(): void
    {
        $response = $this->get('/genre/romance');

        $response->assertOk();
        $response->assertSee('ยังไม่มีอนิเมะในหมวดหมู่นี้', false);
    }

    public function test_genre_page_renders_the_sidebar(): void
    {
        $this->seedAnime(601, 'Punchy Show');
        $this->tagAnime(601, 1);

        $this->get('/genre/action')->assertSee('/genre/romance', false);
    }
}
