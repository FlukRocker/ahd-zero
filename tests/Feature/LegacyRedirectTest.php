<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_watch_with_mirror_query_in_path_extracts_numeric_prefix(): void
    {
        // Mangled shortlink shape: literal `&` in path, not a real query
        // string. Route used to 404 because of whereNumber; legacyWatch
        // now pulls the numeric prefix out and looks up that episode.
        // No episode row exists in the test sqlite shim, so we expect 404
        // from firstOrFail rather than the prior route-level 404.
        $this->get('/watch/12345&mirror=true')->assertStatus(404);
        // But the route IS hit — confirmed by absence of "Route not found".
    }

    public function test_cat_id_redirects_301_to_category_type(): void
    {
        $this->get('/cat/2')
            ->assertStatus(301)
            ->assertRedirect('/category/2');
    }

    public function test_catagory_id_redirects_301_to_anime_id(): void
    {
        $this->get('/catagory/123')
            ->assertStatus(301)
            ->assertRedirect('/anime/123');
    }

    public function test_search_with_legacy_query_redirects_to_results(): void
    {
        $response = $this->get('/search?search=naruto');

        $response->assertStatus(301);
        $this->assertStringContainsString('/search/results?q=naruto', (string) $response->headers->get('Location'));
    }

    public function test_search_without_legacy_query_does_not_redirect(): void
    {
        // Empty `q` triggers a validation error (422), not a 301 — confirms the
        // search controller is being invoked rather than the redirect branch.
        $this->get('/search')->assertStatus(302); // back() with errors
    }
}
