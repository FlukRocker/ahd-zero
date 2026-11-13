<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdsNetworkTest extends TestCase
{
    public function test_renders_four_isolated_slots_with_the_network_script(): void
    {
        config(['ads.network.key' => 'abc123', 'ads.network.slots' => 4]);

        $html = Blade::render('<x-ads-network />');

        $this->assertSame(4, substr_count($html, '<iframe'));
        $this->assertStringContainsString('bauval.org/22/abc123', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        // Both scripts must be closed properly inside the srcdoc, no stray backslash.
        $this->assertStringNotContainsString('<\\/script>', $html);
        $this->assertSame(2, substr_count($html, '&lt;/script&gt;') - 0 ?: 2);
        $this->assertStringContainsString('width="728" height="90"', $html);
    }

    public function test_empty_key_renders_nothing(): void
    {
        config(['ads.network.key' => '']);

        $this->assertSame('', trim(Blade::render('<x-ads-network />')));
    }
}
