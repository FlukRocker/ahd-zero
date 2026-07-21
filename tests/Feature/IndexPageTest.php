<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndexPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // IndexController caches paginators in the array store, which persists
        // across tests in one process — flush so each test sees fresh DB rows.
        Cache::flush();
    }

    public function test_home_renders_server_side_blade_with_anime_and_seo(): void
    {
        DB::table('yu_anime_catagory')->insert([
            'cat_title' => 'Cowboy Bebop Test',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('index');
        // Real server-rendered content — the anime title is in the raw HTML.
        $response->assertSee('Cowboy Bebop Test', false);
        // SEO from layout + page: canonical + global WebSite JSON-LD.
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('application/ld+json', false);
        // Not an Inertia shell.
        $response->assertDontSee('id="app" data-page', false);
    }

    public function test_home_renders_with_empty_database(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewIs('index');
    }
}
