<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

/**
 * Stubs the Mongo-backed aggregate so the hydration path can be tested
 * without a Mongo server. Subclassing beats mocking here: AnalyticsService
 * has a typed `$site` property that an un-constructed Mockery partial would
 * leave uninitialized.
 */
final class FakeTrendingAnalytics extends AnalyticsService
{
    /**
     * @param  Collection<int, array{cat_id: int, views: int}>  $rows
     */
    public function __construct(private Collection $rows)
    {
        parent::__construct('test');
    }

    #[Override]
    public function getTrendingAnime(int $days = 7, int $limit = 10): Collection
    {
        return $this->rows;
    }
}

class TrendingCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trending_cards_are_ordered_by_views_not_database_order(): void
    {
        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 101, 'cat_title' => 'Barely Watched', 'cat_type' => 1, 'cat_update' => now()],
            ['cat_id' => 102, 'cat_title' => 'Everyone Watched', 'cat_type' => 2, 'cat_update' => now()],
        ]);

        $analytics = new FakeTrendingAnalytics(collect([
            ['cat_id' => 102, 'views' => 900],
            ['cat_id' => 101, 'views' => 5],
        ]));

        $cards = $analytics->getTrendingCards(7, 12);

        $this->assertSame([102, 101], array_column($cards, 'cat_id'));
        $this->assertSame(900, $cards[0]['views']);
        $this->assertSame('Everyone Watched', $cards[0]['cat_title']);
    }

    public function test_trending_cards_are_empty_when_analytics_returns_nothing(): void
    {
        $analytics = new FakeTrendingAnalytics(collect());

        $this->assertSame([], $analytics->getTrendingCards());
    }

    public function test_trending_cards_skip_ids_with_no_anime_row(): void
    {
        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 101, 'cat_title' => 'Still Here', 'cat_type' => 1, 'cat_update' => now()],
        ]);

        $analytics = new FakeTrendingAnalytics(collect([
            ['cat_id' => 999, 'views' => 900],
            ['cat_id' => 101, 'views' => 5],
        ]));

        $cards = $analytics->getTrendingCards();

        $this->assertSame([101], array_column($cards, 'cat_id'));
    }
}
