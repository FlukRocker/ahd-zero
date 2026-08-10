<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use App\Support\SidebarData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

/**
 * Returns a different row set per window so the tests can prove each window
 * is actually queried with its own day count, rather than one result being
 * reused for all three tabs.
 */
final class WindowAwareAnalytics extends AnalyticsService
{
    public function __construct()
    {
        parent::__construct('test');
    }

    #[Override]
    public function getTrendingAnime(?int $days = 7, int $limit = 10): Collection
    {
        return match ($days) {
            7 => collect([['cat_id' => 101, 'views' => 10]]),
            30 => collect([['cat_id' => 102, 'views' => 50]]),
            null => collect([['cat_id' => 103, 'views' => 900]]),
            default => collect(),
        };
    }
}

final class EmptyAnalytics extends AnalyticsService
{
    public function __construct()
    {
        parent::__construct('test');
    }

    #[Override]
    public function getTrendingAnime(?int $days = 7, int $limit = 10): Collection
    {
        return collect();
    }
}

/**
 * Counts aggregation runs. The all-time window scans the whole page_views
 * collection, so how often it is executed is the thing that matters.
 */
final class CountingAnalytics extends AnalyticsService
{
    public int $calls = 0;

    public function __construct()
    {
        parent::__construct('test');
    }

    #[Override]
    public function getTrendingAnime(?int $days = 7, int $limit = 10): Collection
    {
        $this->calls++;

        return collect([['cat_id' => 101, 'views' => 10]]);
    }
}

class SidebarDataTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_genres_use_the_thai_name_and_link_to_the_slug(): void
    {
        DB::table('tags')->insert([
            ['name' => 'Action', 'slug' => 'action', 'type' => 'genre', 'name_th' => 'แอคชั่น', 'order_column' => 1],
        ]);

        $genres = (new SidebarData(new EmptyAnalytics))->genres();

        $this->assertSame('แอคชั่น', $genres[0]['label']);
        $this->assertSame('action', $genres[0]['slug']);
    }

    public function test_genres_fall_back_to_the_english_name_when_no_thai_name_exists(): void
    {
        DB::table('tags')->insert([
            ['name' => 'Isekai', 'slug' => 'isekai', 'type' => 'genre', 'name_th' => null, 'order_column' => 1],
        ]);

        $genres = (new SidebarData(new EmptyAnalytics))->genres();

        $this->assertSame('Isekai', $genres[0]['label']);
    }

    public function test_genres_exclude_tags_that_are_not_genres(): void
    {
        DB::table('tags')->insert([
            ['name' => 'Action', 'slug' => 'action', 'type' => 'genre', 'name_th' => null, 'order_column' => 1],
            ['name' => 'Summer 2026', 'slug' => 'summer-2026', 'type' => 'season', 'name_th' => null, 'order_column' => 1],
        ]);

        $genres = (new SidebarData(new EmptyAnalytics))->genres();

        $this->assertSame(['action'], array_column($genres, 'slug'));
    }

    public function test_genres_are_empty_when_no_tags_exist(): void
    {
        $this->assertSame([], (new SidebarData(new EmptyAnalytics))->genres());
    }

    public function test_each_popular_window_is_queried_with_its_own_day_count(): void
    {
        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 101, 'cat_title' => 'Week Winner', 'cat_type' => 1, 'cat_update' => now()],
            ['cat_id' => 102, 'cat_title' => 'Month Winner', 'cat_type' => 1, 'cat_update' => now()],
            ['cat_id' => 103, 'cat_title' => 'All Time Winner', 'cat_type' => 1, 'cat_update' => now()],
        ]);

        $popular = (new SidebarData(new WindowAwareAnalytics))->popular();

        $this->assertSame('Week Winner', $popular['7d'][0]['title']);
        $this->assertSame('Month Winner', $popular['30d'][0]['title']);
        $this->assertSame('All Time Winner', $popular['all'][0]['title']);
    }

    public function test_popular_windows_are_empty_when_analytics_returns_nothing(): void
    {
        $popular = (new SidebarData(new EmptyAnalytics))->popular();

        $this->assertSame([], $popular['7d']);
        $this->assertSame([], $popular['30d']);
        $this->assertSame([], $popular['all']);
    }

    public function test_repeat_requests_do_not_rerun_the_aggregation(): void
    {
        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 101, 'cat_title' => 'Cached Show', 'cat_type' => 1, 'cat_update' => now()],
        ]);

        $analytics = new CountingAnalytics;
        $data = new SidebarData($analytics);

        $data->popular();
        $afterFirst = $analytics->calls;
        $data->popular();
        $data->popular();

        // Three windows on the first call, nothing after — otherwise every
        // request on every page would hit Mongo three times.
        $this->assertSame(3, $afterFirst);
        $this->assertSame(3, $analytics->calls);
    }

    public function test_a_stale_window_is_still_served_without_blocking_on_a_recompute(): void
    {
        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 101, 'cat_title' => 'Cached Show', 'cat_type' => 1, 'cat_update' => now()],
        ]);

        $analytics = new CountingAnalytics;
        $data = new SidebarData($analytics);
        $data->popular();

        // Past the 7d window's fresh period (15m) but well inside its stale
        // period (24h). The caller must still get data immediately; the refresh
        // happens behind a lock rather than in this request's critical path.
        $this->travel(30)->minutes();

        $popular = $data->popular();

        $this->assertSame('Cached Show', $popular['7d'][0]['title']);
        // The key guarantee: the reader did not run the aggregation itself. A
        // plain TTL would have expired here and made this request pay for the
        // recompute — which, multiplied by every concurrent request, is the
        // stampede that produced the 524s.
        $this->assertSame(3, $analytics->calls);
    }

    public function test_popular_entries_carry_the_card_fields_the_sidebar_renders(): void
    {
        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 101, 'cat_title' => 'Week Winner', 'cat_type' => 1, 'cat_update' => now()],
        ]);

        $entry = (new SidebarData(new WindowAwareAnalytics))->popular()['7d'][0];

        $this->assertSame('/anime/101', $entry['href']);
        $this->assertNotEmpty($entry['poster']);
    }
}
