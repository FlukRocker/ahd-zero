<?php

namespace Tests\Unit;

use App\Support\Img;
use PHPUnit\Framework\TestCase;

class ImgTest extends TestCase
{
    public function test_non_proxied_host_passes_through_unchanged(): void
    {
        $u = 'https://cdn.myanimelist.net/images/a.jpg';
        $this->assertSame($u, Img::url($u, ['width' => 360]));
    }

    public function test_null_returns_null(): void
    {
        $this->assertNull(Img::url(null, ['width' => 360]));
    }

    public function test_proxied_host_is_rewritten_with_optimizer_params(): void
    {
        $out = Img::url('https://img-cdn.shirokami.me/images/2024/a.md.png', ['width' => 480, 'format' => 'webp']);
        $this->assertStringStartsWith('https://img-cdn-proxy.shirokami.me/images/2024/a.png?', $out);
        $this->assertStringContainsString('width=480', $out);
        $this->assertStringContainsString('format=webp', $out);
        $this->assertStringContainsString('quality=80', $out);
        // .md variant suffix stripped before proxying
        $this->assertStringNotContainsString('.md.png', $out);
    }

    public function test_default_quality_is_80_and_auto_format_not_pinned(): void
    {
        $out = Img::url('https://img-cdn.shirokami.me/x.jpg', ['width' => 360]);
        $this->assertStringContainsString('quality=80', $out);
        $this->assertStringNotContainsString('format=', $out);

        // Explicit 'auto' must also NOT pin a format param.
        $autoOut = Img::url('https://img-cdn.shirokami.me/x.jpg', ['width' => 360, 'format' => 'auto']);
        $this->assertStringNotContainsString('format=', $autoOut);
    }

    public function test_srcset_builds_width_descriptors(): void
    {
        $out = Img::srcset('https://img-cdn.shirokami.me/x.jpg', [240, 480], ['format' => 'webp']);
        $this->assertStringContainsString('width=240', $out);
        $this->assertStringContainsString(' 240w', $out);
        $this->assertStringContainsString(' 480w', $out);
        $this->assertStringContainsString(', ', $out);
    }

    public function test_srcset_null_for_non_proxied_returns_descriptors_of_passthrough(): void
    {
        // Non-proxied host: each entry is the original URL with a width descriptor.
        $out = Img::srcset('https://cdn.myanimelist.net/x.jpg', [240, 480]);
        $this->assertStringContainsString('https://cdn.myanimelist.net/x.jpg 240w', $out);
    }

    public function test_srcset_width_overrides_opts_width(): void
    {
        $out = Img::srcset('https://img-cdn.shirokami.me/x.jpg', [240, 480], ['width' => 999]);
        $this->assertStringContainsString('width=240', $out);
        $this->assertStringContainsString('width=480', $out);
        $this->assertStringNotContainsString('width=999', $out);
    }
}
