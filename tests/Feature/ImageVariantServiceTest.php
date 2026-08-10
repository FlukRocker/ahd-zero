<?php

namespace Tests\Feature;

use App\Services\ImageVariantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Override;
use Tests\TestCase;

/**
 * The one guarantee that matters here: rendering a page never waits on a
 * network round-trip to decide which image URL to print. A page full of cards
 * would otherwise serialise dozens of 3s probes into a single response.
 */
class ImageVariantServiceTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private const ORIGINAL = 'https://img.shirokami.me/2026/01/01/abc.webp';

    private const VARIANT = 'https://img.shirokami.me/2026/01/01/abc.md.webp';

    public function test_an_uncached_variant_does_not_probe_during_the_request(): void
    {
        Http::fake();

        $result = (new ImageVariantService)->getVariant(self::ORIGINAL, 'md');

        Http::assertNothingSent();
        $this->assertSame(self::ORIGINAL, $result);
    }

    public function test_a_known_good_variant_is_used(): void
    {
        Http::fake();
        Cache::put('img_variant:'.md5(self::VARIANT), 'valid', 60);

        $result = (new ImageVariantService)->getVariant(self::ORIGINAL, 'md');

        Http::assertNothingSent();
        $this->assertSame(self::VARIANT, $result);
    }

    public function test_a_known_missing_variant_falls_back_to_the_original(): void
    {
        Http::fake();
        Cache::put('img_variant:'.md5(self::VARIANT), 'invalid', 60);

        $result = (new ImageVariantService)->getVariant(self::ORIGINAL, 'md');

        Http::assertNothingSent();
        $this->assertSame(self::ORIGINAL, $result);
    }

    public function test_many_images_on_one_page_still_send_nothing(): void
    {
        Http::fake();
        $service = new ImageVariantService;

        // A sidebar window plus a rail is easily this many images; before the
        // change each one cost a blocking probe.
        for ($i = 0; $i < 40; $i++) {
            $service->getVariant("https://img.shirokami.me/2026/01/01/{$i}.webp", 'md');
        }

        Http::assertNothingSent();
    }

    public function test_only_one_probe_is_scheduled_per_url(): void
    {
        $service = new ImageVariantService;
        $service->getVariant(self::ORIGINAL, 'md');

        // The in-flight marker is what stops every concurrent request for the
        // same uncached image queueing its own probe.
        $this->assertNotNull(Cache::get('img_variant:'.md5(self::VARIANT).':probing'));
    }

    public function test_bunny_proxied_images_are_never_probed_or_variant_resolved(): void
    {
        Http::fake();
        // Img::url() strips .md before Bunny sees the path, so resolving the
        // variant here would be discarded downstream. This is the host almost
        // every anime image lives on, so it is where the cost was.
        $url = 'https://img-cdn.shirokami.me/2026/07/27/abc.webp';

        $result = (new ImageVariantService)->getVariant($url, 'md');

        $this->assertSame($url, $result);
        Http::assertNothingSent();
        // Not even a background probe should be scheduled for these.
        $this->assertNull(Cache::get('img_variant:'.md5('https://img-cdn.shirokami.me/2026/07/27/abc.md.webp').':probing'));
    }

    public function test_non_chevereto_urls_are_passed_through_untouched(): void
    {
        Http::fake();

        $url = 'https://example.com/poster.webp';

        $this->assertSame($url, (new ImageVariantService)->getVariant($url, 'md'));
        Http::assertNothingSent();
    }
}
