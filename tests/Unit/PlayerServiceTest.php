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

    public function test_fetch_caches_successful_response(): void
    {
        Cache::flush();
        Http::fake([
            '*get-datas*' => Http::response(['result' => ['uid' => 'XYZUID']], 200),
        ]);

        config([
            'services.akuma_player.url' => 'https://api.test',
            'services.akuma_player.token' => 'test-token',
            'services.akuma_player.player_domain' => 'https://player.test',
        ]);

        $service = new PlayerService;
        $url = $service->getPlayerUrl('https://drive.google.com/file/d/CACHED1/view');

        $this->assertSame('https://player.test/play/XYZUID', $url);
        $this->assertSame('https://player.test/play/XYZUID', Cache::get('player:drive:CACHED1'));
    }

    private function extractDriveId(string $url): ?string
    {
        $ref = new ReflectionClass(PlayerService::class);
        $method = $ref->getMethod('extractDriveId');
        $method->setAccessible(true);

        return $method->invoke(new PlayerService, $url);
    }
}
