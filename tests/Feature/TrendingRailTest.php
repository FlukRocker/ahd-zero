<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class TrendingRailTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // IndexController caches into the array store, which survives across
        // tests in one process.
        Cache::flush();
    }

    /**
     * @param  list<int>  $catIds
     */
    private function seedAnime(array $catIds): void
    {
        foreach ($catIds as $catId) {
            DB::table('yu_anime_catagory')->insert([
                'cat_id' => $catId,
                'cat_title' => "Trending Title {$catId}",
                'cat_type' => 1,
                'cat_update' => now(),
            ]);
        }
    }

    /**
     * @param  list<int>  $catIds
     */
    private function fakeAnalytics(array $catIds): void
    {
        $rows = collect($catIds)->map(fn (int $id, int $i): array => [
            'cat_id' => $id,
            'views' => 1000 - $i,
        ]);

        $this->instance(AnalyticsService::class, new FakeTrendingAnalytics($rows));
    }

    public function test_homepage_renders_trending_rail_from_analytics(): void
    {
        $ids = [201, 202, 203, 204, 205, 206];
        $this->seedAnime($ids);
        $this->fakeAnalytics($ids);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('มาแรงตอนนี้', false);
        $response->assertSee('Trending Title 201', false);
    }

    public function test_trending_rail_hidden_when_too_few_results(): void
    {
        $ids = [301, 302, 303];
        $this->seedAnime($ids);
        $this->fakeAnalytics($ids);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('มาแรงตอนนี้', false);
    }

    public function test_homepage_still_renders_when_analytics_is_empty(): void
    {
        $this->seedAnime([401]);
        $this->instance(AnalyticsService::class, new FakeTrendingAnalytics(collect()));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('มาแรงตอนนี้', false);
        $response->assertSee('Trending Title 401', false);
    }
}
