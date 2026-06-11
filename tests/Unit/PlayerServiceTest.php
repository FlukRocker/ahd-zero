<?php

namespace Tests\Unit;

use App\Services\PlayerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

class PlayerServiceTest extends TestCase
{
    public function test_extracts_drive_id_from_file_d_url(): void
    {
        $this->assertSame(
            '1ABCxyz_9-test',
            $this->extractDriveId('https://drive.google.com/file/d/1ABCxyz_9-test/view?usp=sharing'),
        );
    }

    public function test_extracts_drive_id_from_open_id_url(): void
    {
        $this->assertSame(
            'abcDEF123',
            $this->extractDriveId('https://drive.google.com/open?id=abcDEF123'),
        );
    }

    public function test_extracts_drive_id_from_uc_direct_url(): void
    {
        $this->assertSame(
            '1hashhash',
            $this->extractDriveId('https://drive.google.com/uc?export=download&id=1hashhash'),
        );
    }

    public function test_returns_null_for_non_drive_url(): void
    {
        $this->assertNull($this->extractDriveId('https://example.com/video.mp4'));
    }

    public function test_non_drive_url_passes_through_as_player_url(): void
    {
        $service = new PlayerService;
        $this->assertSame(
            'https://example.com/video.mp4',
            $service->getPlayerUrl('https://example.com/video.mp4'),
        );
    }

    public function test_null_url_returns_null(): void
    {
        $service = new PlayerService;
        $this->assertNull($service->getPlayerUrl(null));
        $this->assertNull($service->getPlayerUrl(''));
    }

    public function test_unrecognized_drive_url_does_not_leak_through(): void
    {
        $service = new PlayerService;

        // Drive URL the regex doesn't match (folder share, viewer URL, etc).
        // Strict mode must return null instead of passing the raw URL through
        // to the iframe — Drive blocks iframes anyway and the URL itself is
        // sensitive (exposes folder/file IDs).
        $this->assertNull(
            $service->getPlayerUrl('https://drive.google.com/drive/folders/1abcXYZ'),
        );
        $this->assertNull(
            $service->getPlayerUrl('https://Drive.Google.com/some/new/format'),
        );
    }

    public function test_fetch_caches_ready_watch_url(): void
    {
        Cache::flush();
        Http::fake([
            '*api/videos/player/CACHED1' => Http::response([
                'id' => 'db2019e9-92a2-46e6-a2e5-a273a2cfe4d4',
                'title' => 'Ep 1',
                'status' => 'ready',
                'watchUrl' => '/watch/db2019e9-92a2-46e6-a2e5-a273a2cfe4d4',
            ], 200),
        ]);

        config([
            'services.akuma_stream.url' => 'https://app.akuma-stream.com',
            'services.akuma_stream.admin_token' => 'test-admin-token',
        ]);

        $service = new PlayerService;
        $url = $service->getPlayerUrl('https://drive.google.com/file/d/CACHED1/view');

        $this->assertSame('https://app.akuma-stream.com/watch/db2019e9-92a2-46e6-a2e5-a273a2cfe4d4', $url);
        $this->assertSame(
            'https://app.akuma-stream.com/watch/db2019e9-92a2-46e6-a2e5-a273a2cfe4d4',
            Cache::get('player:stream:v1:CACHED1'),
        );

        Http::assertSent(fn ($request) => $request->hasHeader('x-admin-token', 'test-admin-token'));
    }

    public function test_not_ready_video_returns_null_and_short_caches(): void
    {
        Cache::flush();
        Http::fake([
            '*api/videos/player/*' => Http::response([
                'id' => 'db2019e9-92a2-46e6-a2e5-a273a2cfe4d4',
                'title' => 'Ep 1',
                'status' => 'transcoding',
                'watchUrl' => null,
            ], 200),
        ]);

        config([
            'services.akuma_stream.url' => 'https://app.akuma-stream.com',
            'services.akuma_stream.admin_token' => 'test-admin-token',
        ]);

        $service = new PlayerService;

        $this->assertNull($service->getPlayerUrl('https://drive.google.com/file/d/PENDING1/view'));
        // Failure sentinel cached as empty string (60s TTL).
        $this->assertSame('', Cache::get('player:stream:v1:PENDING1'));
    }

    public function test_unknown_ref_404_returns_null(): void
    {
        Cache::flush();
        Http::fake([
            '*api/videos/player/*' => Http::response(['error' => 'not found'], 404),
        ]);

        config([
            'services.akuma_stream.url' => 'https://app.akuma-stream.com',
            'services.akuma_stream.admin_token' => 'test-admin-token',
        ]);

        $service = new PlayerService;

        $this->assertNull($service->getPlayerUrl('https://drive.google.com/file/d/MISSING1/view'));
    }

    public function test_bare_uuid_is_used_as_ref(): void
    {
        Cache::flush();
        Http::fake([
            '*api/videos/player/db2019e9-92a2-46e6-a2e5-a273a2cfe4d4' => Http::response([
                'id' => 'db2019e9-92a2-46e6-a2e5-a273a2cfe4d4',
                'title' => 'Ep 1',
                'status' => 'ready',
                'watchUrl' => '/watch/db2019e9-92a2-46e6-a2e5-a273a2cfe4d4',
            ], 200),
        ]);

        config([
            'services.akuma_stream.url' => 'https://app.akuma-stream.com',
            'services.akuma_stream.admin_token' => 'test-admin-token',
        ]);

        $service = new PlayerService;

        $this->assertSame(
            'https://app.akuma-stream.com/watch/db2019e9-92a2-46e6-a2e5-a273a2cfe4d4',
            $service->getPlayerUrl('db2019e9-92a2-46e6-a2e5-a273a2cfe4d4'),
        );
    }

    public function test_missing_admin_token_returns_null(): void
    {
        Cache::flush();
        Http::fake();

        config([
            'services.akuma_stream.url' => 'https://app.akuma-stream.com',
            'services.akuma_stream.admin_token' => '',
        ]);

        $service = new PlayerService;

        $this->assertNull($service->getPlayerUrl('https://drive.google.com/file/d/NOTOKEN1/view'));
        Http::assertNothingSent();
    }

    private function extractDriveId(string $url): ?string
    {
        $ref = new ReflectionClass(PlayerService::class);
        $method = $ref->getMethod('extractRef');
        $method->setAccessible(true);

        return $method->invoke(new PlayerService, $url);
    }
}
