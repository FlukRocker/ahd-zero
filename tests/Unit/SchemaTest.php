<?php

namespace Tests\Unit;

use App\Support\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    public function test_breadcrumb_resolves_absolute_urls_and_positions(): void
    {
        config(['app.url' => 'https://anime-hdzero.com']);
        $b = Schema::breadcrumb([
            ['name' => 'หน้าแรก', 'url' => '/'],
            ['name' => 'Naruto', 'url' => '/anime/12'],
        ]);

        $this->assertSame('BreadcrumbList', $b['@type']);
        $this->assertSame(1, $b['itemListElement'][0]['position']);
        $this->assertSame('https://anime-hdzero.com', $b['itemListElement'][0]['item']);
        $this->assertSame('https://anime-hdzero.com/anime/12', $b['itemListElement'][1]['item']);
    }

    public function test_tvseries_omits_empty_optionals_and_keeps_absolute_url(): void
    {
        config(['app.url' => 'https://anime-hdzero.com']);
        $t = Schema::tvSeries([
            'name' => 'Naruto',
            'url' => '/anime/12',
            'genre' => ['Action'],
            'productionCompany' => [['name' => 'Pierrot']],
        ]);

        $this->assertSame('TVSeries', $t['@type']);
        $this->assertSame('https://anime-hdzero.com/anime/12', $t['url']);
        $this->assertSame(['Action'], $t['genre']);
        $this->assertSame('Organization', $t['productionCompany'][0]['@type']);
        $this->assertArrayNotHasKey('description', $t);
    }

    public function test_video_object_maps_series_and_urls(): void
    {
        config(['app.url' => 'https://anime-hdzero.com']);
        $v = Schema::videoObject([
            'name' => 'Ep 1',
            'embedUrl' => 'https://player.example/watch/abc',
            'partOfSeries' => ['name' => 'Naruto', 'url' => '/anime/12'],
        ]);

        $this->assertSame('VideoObject', $v['@type']);
        $this->assertSame('https://player.example/watch/abc', $v['embedUrl']);
        $this->assertSame('https://anime-hdzero.com/anime/12', $v['partOfSeries']['url']);
    }
}
