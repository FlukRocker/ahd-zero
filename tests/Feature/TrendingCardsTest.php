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
    public function getTrendingAnime(?int $days = 7, int $limit = 10): Collection
    {
        return $this->rows;
    }
}

/**
 * Exposes the aggregation pipeline so the all-time behaviour can be asserted
 * without a Mongo server — the pipeline is the only place the date window
 * exists, so building it correctly is the whole contract.
 */
final class PipelineProbeAnalytics extends AnalyticsService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function pipelineFor(?int $days, int $limit = 10): array
    {
        return $this->trendingPipeline($days, $limit);
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

    public function test_windowed_pipeline_filters_on_a_created_at_floor(): void
    {
        $match = (new PipelineProbeAnalytics('test'))->pipelineFor(7)[0]['$match'];

        $this->assertArrayHasKey('created_at', $match);
        $this->assertArrayHasKey('$gte', $match['created_at']);
    }

    public function test_all_time_pipeline_drops_the_created_at_filter_entirely(): void
    {
        $match = (new PipelineProbeAnalytics('test'))->pipelineFor(null)[0]['$match'];

        // A null window must remove the clause, not widen it — a very large
        // $gte would still exclude documents with a missing created_at.
        $this->assertArrayNotHasKey('created_at', $match);
        // The rest of the scoping has to survive, or all-time would leak
        // another site's traffic into this one's sidebar.
        $this->assertSame('test', $match['site']);
        $this->assertSame('anime', $match['page_type']);
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
