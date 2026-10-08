<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

/**
 * Cloudflare reaches this origin over plain http, so without a forced scheme
 * every generated URL is http:// — and the Vite preload `Link:` response
 * header is the one place Cloudflare's Automatic HTTPS Rewrites cannot fix,
 * so `style-src 'self'` blocked the stylesheet it preloaded.
 */
class HttpsUrlSchemeTest extends TestCase
{
    public function test_generated_urls_follow_an_https_app_url(): void
    {
        config(['app.url' => 'https://ahdzero.com']);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', asset('build/assets/app.css'));
        $this->assertStringStartsWith('https://', url('/anime/1'));
    }

    public function test_a_plain_http_app_url_is_left_alone(): void
    {
        config(['app.url' => 'http://localhost']);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('http://', asset('build/assets/app.css'));
    }
}
