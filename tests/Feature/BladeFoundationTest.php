<?php

namespace Tests\Feature;

use Tests\TestCase;

class BladeFoundationTest extends TestCase
{
    public function test_404_page_renders_server_side_blade_html(): void
    {
        $this->withoutVite();

        $response = $this->get('/definitely-not-a-real-route-xyz-123');

        $response->assertStatus(404);
        // Real server-rendered content in the raw HTML body.
        $response->assertSee('404', false);
        // Server HTML carries the SEO title tag (from partials.seo).
        $response->assertSee('<title>', false);
        // NOT an Inertia client-only shell.
        $response->assertDontSee('id="app" data-page', false);
    }

    public function test_global_composer_shares_site_data_with_blade_views(): void
    {
        $this->withoutVite();

        // Render an inline Blade string through the view factory so the
        // '*' composer runs against it.
        $rendered = view('errors.404')->render();

        $this->assertStringContainsString(config('app.name'), $rendered);
    }

    public function test_seo_partial_emits_canonical_and_og_tags(): void
    {
        $this->withoutVite();

        $rendered = view('errors.404')->render();

        $this->assertStringContainsString('rel="canonical"', $rendered);
        $this->assertStringContainsString('property="og:title"', $rendered);
        $this->assertStringContainsString('application/ld+json', $rendered);
    }
}
