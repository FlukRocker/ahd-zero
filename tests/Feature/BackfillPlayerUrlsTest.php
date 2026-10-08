<?php

namespace Tests\Feature;

use App\Models\EpisodePlayerUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The sweep is the only thing that notices a persisted watch URL has gone
 * dead: akuma-stream mints a new video uuid when an episode is re-transcoded,
 * and the old /watch/{uuid} then answers `{"error":"not found"}`. Nothing
 * pushes that event here, so these cover how quickly, and in what order, the
 * sweep re-checks what it has already stored.
 */
class BackfillPlayerUrlsTest extends TestCase
{
    use RefreshDatabase;

    private int $animeId;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'services.akuma_stream.url' => 'https://app.akuma-stream.com',
            'services.akuma_stream.admin_token' => 'test-admin-token',
        ]);

        $this->animeId = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Backfill Anime',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
    }

    private function seedEpisode(string $driveId): int
    {
        return DB::table('yu_anime_list')->insertGetId([
            'catagory_id' => $this->animeId,
            'list_title' => "Ep {$driveId}",
            'uuid' => "uuid-{$driveId}",
            'list_url' => "https://drive.google.com/file/d/{$driveId}/view",
            'adddate' => now(),
        ]);
    }

    public function test_a_day_old_persisted_url_is_rechecked(): void
    {
        Http::fake([
            '*api/videos/player/DRIVE1' => Http::response([
                'status' => 'ready',
                'watchUrl' => '/watch/fresh-uuid',
            ], 200),
        ]);

        $listId = $this->seedEpisode('DRIVE1');
        EpisodePlayerUrl::query()->create([
            'list_id' => $listId,
            'watch_url' => 'https://app.akuma-stream.com/watch/dead-uuid',
            'resolved_at' => now()->subHours(25),
            'attempts' => 1,
        ]);

        $this->artisan('player:backfill --limit=10 --rate=1000')->assertSuccessful();

        $this->assertSame(
            'https://app.akuma-stream.com/watch/fresh-uuid',
            EpisodePlayerUrl::query()->whereKey($listId)->value('watch_url'),
            'a persisted URL must be re-verified within a day, not a week',
        );
    }

    public function test_rows_with_a_persisted_url_are_rechecked_before_rows_without_one(): void
    {
        // A row with no URL costs nothing to leave alone — the episode page
        // resolves those live on every view. A row that HAS a URL is the only
        // one that can rot unnoticed, so it goes first even when an emptier
        // row has been waiting longer.
        Http::fake([
            '*api/videos/player/HASURL' => Http::response([
                'status' => 'ready',
                'watchUrl' => '/watch/fresh-uuid',
            ], 200),
            '*api/videos/player/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $hasUrl = $this->seedEpisode('HASURL');
        $noUrl = $this->seedEpisode('NOURL');

        EpisodePlayerUrl::query()->create([
            'list_id' => $hasUrl,
            'watch_url' => 'https://app.akuma-stream.com/watch/dead-uuid',
            'resolved_at' => now()->subDays(10),
            'attempts' => 1,
        ]);
        EpisodePlayerUrl::query()->create([
            'list_id' => $noUrl,
            'watch_url' => null,
            'resolved_at' => now()->subDays(30),
            'attempts' => 4,
        ]);

        $this->artisan('player:backfill --limit=1 --rate=1000')->assertSuccessful();

        $this->assertSame(
            'https://app.akuma-stream.com/watch/fresh-uuid',
            EpisodePlayerUrl::query()->whereKey($hasUrl)->value('watch_url'),
        );
        $this->assertSame(
            4,
            (int) EpisodePlayerUrl::query()->whereKey($noUrl)->value('attempts'),
            'the emptier row should not have consumed the one slot',
        );
    }

    public function test_an_episode_without_a_row_still_comes_first(): void
    {
        Http::fake([
            '*api/videos/player/NEWEP' => Http::response([
                'status' => 'ready',
                'watchUrl' => '/watch/new-uuid',
            ], 200),
            '*api/videos/player/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $stale = $this->seedEpisode('STALEEP');
        $fresh = $this->seedEpisode('NEWEP');

        EpisodePlayerUrl::query()->create([
            'list_id' => $stale,
            'watch_url' => 'https://app.akuma-stream.com/watch/dead-uuid',
            'resolved_at' => now()->subDays(10),
            'attempts' => 1,
        ]);

        $this->artisan('player:backfill --limit=1 --rate=1000')->assertSuccessful();

        $this->assertSame(
            'https://app.akuma-stream.com/watch/new-uuid',
            EpisodePlayerUrl::query()->whereKey($fresh)->value('watch_url'),
        );
    }
}
