<?php

namespace Tests\Unit;

use App\Services\ImageVariantService;
use ReflectionClass;
use Tests\TestCase;

class ImageVariantServiceTest extends TestCase
{
    public function test_null_or_empty_url_returns_null(): void
    {
        $service = new ImageVariantService;
        $this->assertNull($service->getVariant(null, 'md'));
        $this->assertNull($service->getVariant('', 'md'));
    }

    public function test_non_shirokami_url_passes_through_unchanged(): void
    {
        $service = new ImageVariantService;
        $url = 'https://cdn.myanimelist.net/images/anime/1234/abc.jpg';
        $this->assertSame($url, $service->getVariant($url, 'md'));
    }

    public function test_builds_variant_url_with_md_suffix(): void
    {
        $variant = $this->buildVariantUrl(
            'https://img.shirokami.me/images/2024/11/abc.png',
            'md',
        );

        $this->assertSame('https://img.shirokami.me/images/2024/11/abc.md.png', $variant);
    }

    public function test_builds_variant_url_with_th_suffix(): void
    {
        $variant = $this->buildVariantUrl(
            'https://img.shirokami.me/images/2024/11/abc.webp',
            'th',
        );

        $this->assertSame('https://img.shirokami.me/images/2024/11/abc.th.webp', $variant);
    }

    public function test_returns_null_for_string_without_any_dot(): void
    {
        $this->assertNull($this->buildVariantUrl('no-ext', 'md'));
    }

    private function buildVariantUrl(string $url, string $suffix): ?string
    {
        $ref = new ReflectionClass(ImageVariantService::class);
        $method = $ref->getMethod('buildVariantUrl');
        $method->setAccessible(true);

        return $method->invoke(new ImageVariantService, $url, $suffix);
    }
}
