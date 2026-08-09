<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class MetaImageTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->instance(AnalyticsService::class, new EmptyAnalytics);
    }

    private function seedAnime(int $catId, ?string $image): void
    {
        DB::table('yu_anime_catagory')->insert([
            'cat_id' => $catId,
            'cat_title' => "Meta Show {$catId}",
            'cat_image' => $image,
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
    }

    public function test_the_homepage_uses_the_logo_card_as_its_meta_image(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('og-default.jpg', false);
    }

    public function test_the_anime_page_uses_its_cover_rather_than_the_logo(): void
    {
        $cover = 'https://img-cdn.shirokami.me/cover-801.webp';
        $this->seedAnime(801, $cover);

        $response = $this->get('/anime/801');

        $response->assertOk();
        $response->assertSee($cover, false);
        $response->assertDontSee('og-default.jpg', false);
    }

    public function test_an_anime_with_no_cover_falls_back_to_the_logo_card(): void
    {
        // Otherwise og:image ships empty and the share card renders blank.
        $this->seedAnime(802, null);

        $response = $this->get('/anime/802');

        $response->assertOk();
        $response->assertSee('og-default.jpg', false);
    }

    public function test_the_meta_image_is_always_an_absolute_url(): void
    {
        // Crawlers silently drop a relative og:image.
        $html = $this->get('/')->getContent();

        preg_match('/<meta property="og:image" content="([^"]*)"/', $html, $m);

        $this->assertNotEmpty($m[1] ?? '');
        $this->assertStringStartsWith('http', $m[1]);
    }

    public function test_the_logo_is_used_as_the_header_mark_and_favicon(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('/ahd-logo-64.png', $html);
        $this->assertStringContainsString('/favicon.ico', $html);
    }
}
