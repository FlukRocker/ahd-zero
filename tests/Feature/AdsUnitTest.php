<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdsUnitTest extends TestCase
{
    public function test_unit_renders_one_isolated_iframe_with_its_size_and_closed_scripts(): void
    {
        config(['ads.units_enabled' => true]);

        $html = Blade::render('<x-ads-unit name="b300x250" />');

        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertStringContainsString('bauval.org/22/b27efac79307ce9792876f90ca8c5671', $html);
        $this->assertStringContainsString('width="300" height="250"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertSame(2, substr_count($html, '&lt;/script&gt;'));
    }

    public function test_disabled_or_unknown_or_keyless_units_render_nothing(): void
    {
        config(['ads.units_enabled' => false]);
        $this->assertSame('', trim(Blade::render('<x-ads-unit name="b300x250" />')));

        config(['ads.units_enabled' => true]);
        $this->assertSame('', trim(Blade::render('<x-ads-unit name="nope" />')));

        config(['ads.units.b300x250.key' => '']);
        $this->assertSame('', trim(Blade::render('<x-ads-unit name="b300x250" />')));
    }

    public function test_floating_renders_two_rails_and_the_320_unit_in_one_closable_bar(): void
    {
        config(['ads.units_enabled' => true]);

        $html = Blade::render('<x-ads-floating />');

        $this->assertSame(3, substr_count($html, '<iframe'));
        $this->assertStringContainsString('floating-b-dock', $html);
        $this->assertStringContainsString('strip-close', $html);
        $this->assertSame(1, substr_count($html, 'strip-close"'));
        $this->assertStringContainsString('bauval.org/22/bba56c449cea79d568a35635be965663', $html);
        $this->assertStringContainsString('bauval.org/22/1d831b51a62666c84af3ad63d0e4c414', $html);
        $this->assertStringContainsString('bauval.org/22/304b8a073f1f1b759f06c18aa636b3d4', $html);
    }

    public function test_floating_renders_nothing_when_units_are_off_and_no_payload(): void
    {
        config(['ads.units_enabled' => false]);

        $this->assertSame('', trim(Blade::render('<x-ads-floating />')));
    }

    public function test_fit_unit_scales_to_the_available_width(): void
    {
        config(['ads.units_enabled' => true]);

        $html = Blade::render('<x-ads-unit name="b468x60" :fit="true" />');

        $this->assertStringContainsString('class="xu-fit"', $html);
        $this->assertStringContainsString('aspect-ratio:468 / 60', $html);
        $this->assertStringContainsString('clientWidth / 468', $html);
        $this->assertStringContainsString('bauval.org/22/a494aa020bb35c259505733d239fc947', $html);
    }
}
