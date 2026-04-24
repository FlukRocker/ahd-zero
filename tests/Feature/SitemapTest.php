<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_returns_text_plain_and_references_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $response->assertSee('Sitemap:', false);
        $response->assertSee('Disallow: /dashboard', false);
    }

    public function test_sitemap_index_returns_xml_urlset(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('sitemap-pages.xml', false);
    }

    public function test_sitemap_pages_contains_core_routes(): void
    {
        $response = $this->get('/sitemap-pages.xml');

        $response->assertOk();
        $response->assertSee('/category/1', false);
        $response->assertSee('/category/2', false);
        $response->assertSee('/studios', false);
    }
}
