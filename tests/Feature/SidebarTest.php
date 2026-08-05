<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // SidebarData caches into the array store, which survives across tests
        // in one process.
        Cache::flush();
    }

    private function seedGenres(): void
    {
        DB::table('tags')->insert([
            ['id' => 1, 'name' => 'Action', 'slug' => 'action', 'type' => 'genre', 'name_th' => 'แอคชั่น', 'order_column' => 1],
            ['id' => 2, 'name' => 'Isekai', 'slug' => 'isekai', 'type' => 'genre', 'name_th' => null, 'order_column' => 2],
        ]);
    }

    /**
     * @param  list<int>  $catIds
     */
    private function seedAnime(array $catIds): void
    {
        foreach ($catIds as $catId) {
            DB::table('yu_anime_catagory')->insert([
                'cat_id' => $catId,
                'cat_title' => "Sidebar Title {$catId}",
                'cat_type' => 1,
                'cat_update' => now(),
            ]);
        }
    }

    private function fakeEmptyAnalytics(): void
    {
        $this->instance(AnalyticsService::class, new EmptyAnalytics);
    }

    private function fakeWindowedAnalytics(): void
    {
        $this->instance(AnalyticsService::class, new WindowAwareAnalytics);
    }

    public function test_genres_render_in_the_sidebar_with_thai_names(): void
    {
        $this->seedGenres();
        $this->fakeEmptyAnalytics();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('หมวดหมู่', false);
        $response->assertSee('แอคชั่น', false);
        $response->assertSee('/genre/action', false);
    }

    public function test_genre_without_a_thai_name_falls_back_to_english(): void
    {
        $this->seedGenres();
        $this->fakeEmptyAnalytics();

        $this->get('/')->assertSee('Isekai', false);
    }

    public function test_popular_block_is_omitted_entirely_when_analytics_is_empty(): void
    {
        $this->seedGenres();
        $this->fakeEmptyAnalytics();

        $response = $this->get('/');

        $response->assertOk();
        // The heading must not appear at all — a heading over an empty list
        // would show on every page of the site whenever Mongo is down.
        $response->assertDontSee('อนิเมะยอดนิยม', false);
    }

    public function test_all_three_time_windows_render_when_analytics_has_data(): void
    {
        $this->seedAnime([101, 102, 103]);
        $this->fakeWindowedAnalytics();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('อนิเมะยอดนิยม', false);
        // Every window is in the HTML so the tabs never wait on a fetch.
        $response->assertSee('Sidebar Title 101', false);
        $response->assertSee('Sidebar Title 102', false);
        $response->assertSee('Sidebar Title 103', false);
        $response->assertSee('ตลอดเวลา', false);
    }

    public function test_only_the_seven_day_window_is_visible_without_javascript(): void
    {
        $this->seedAnime([101, 102, 103]);
        $this->fakeWindowedAnalytics();

        $html = $this->get('/')->getContent();

        // The 30-day and all-time lists ship hidden; Alpine reveals them on
        // tab click. Exactly two of the three lists carry the inline style.
        $this->assertSame(2, substr_count($html, 'style="display: none"'));
    }

    public function test_sidebar_is_absent_from_the_admin_dashboard(): void
    {
        $this->seedGenres();
        $this->fakeEmptyAnalytics();

        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertDontSee('/genre/action', false);
    }

    public function test_sidebar_renders_on_the_anime_page(): void
    {
        $this->seedGenres();
        $this->seedAnime([501]);
        $this->fakeEmptyAnalytics();

        $response = $this->get('/anime/501');

        $response->assertOk();
        $response->assertSee('/genre/action', false);
    }
}
