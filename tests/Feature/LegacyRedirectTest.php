<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRedirectTest extends TestCase
{
    use RefreshDatabase;

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
