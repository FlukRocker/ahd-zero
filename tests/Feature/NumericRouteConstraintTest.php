<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

/**
 * These routes bind to int-typed controller arguments. Without a numeric
 * constraint a non-numeric segment reaches the controller as a string and PHP
 * raises a TypeError, so crawlers hitting malformed URLs generate 500s and
 * error-log noise instead of a plain 404.
 */
class NumericRouteConstraintTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->instance(AnalyticsService::class, new EmptyAnalytics);

        DB::table('yu_anime_catagory')->insert([
            'cat_id' => 901,
            'cat_title' => 'Routed Show',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function nonNumericUrls(): array
    {
        return [
            'anime id' => ['/anime/abc'],
            'anime id with suffix' => ['/anime/901abc'],
            'episode id' => ['/anime/901/episode/abc'],
            'episode anime id' => ['/anime/abc/episode/1'],
            'studio id' => ['/studio/abc'],
            'voice actor id' => ['/voice-actor/abc'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonNumericUrls')]
    public function test_non_numeric_ids_return_404_not_500(string $url): void
    {
        $this->get($url)->assertNotFound();
    }

    public function test_numeric_ids_still_resolve(): void
    {
        // The constraint must not break the routes it guards.
        $this->get('/anime/901')->assertOk();
    }

    public function test_a_missing_numeric_anime_is_still_a_404(): void
    {
        $this->get('/anime/999999')->assertNotFound();
    }
}
