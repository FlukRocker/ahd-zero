<?php

namespace Tests\Feature;

use App\Models\EpisodePlayerUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function seedEpisode(?string $watchUrl, string $image = 'https://img.example.com/cover.jpg'): int
    {
        $animeId = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Sitemap Anime',
            'cat_desc' => '<p>เรื่องย่อ</p>',
            'cat_image' => $image,
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
        $listId = DB::table('yu_anime_list')->insertGetId([
            'catagory_id' => $animeId,
            'list_title' => 'ตอนที่ 1',
            'uuid' => 'uuid-1',
            'list_url' => 'https://example.com/watch/one',
            'adddate' => now(),
        ]);

        if ($watchUrl !== null) {
            EpisodePlayerUrl::create([
                'list_id' => $listId,
                'watch_url' => $watchUrl,
                'resolved_at' => now(),
            ]);
        }

        return $listId;
    }

    public function test_episode_sitemap_declares_the_video_namespace(): void
    {
        $this->seedEpisode('https://control.akuma-stream.com/watch/abc');

        $response = $this->get('/sitemap-episodes-1.xml');

        $response->assertOk();
        $this->assertStringContainsString(
            'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"',
            $response->streamedContent()
        );
    }

    public function test_episode_with_a_resolved_player_gets_a_video_entry(): void
    {
        $this->seedEpisode('https://control.akuma-stream.com/watch/abc');

        $xml = $this->get('/sitemap-episodes-1.xml')->streamedContent();

        $this->assertStringContainsString('<video:video>', $xml);
        $this->assertStringContainsString(
            '<video:player_loc>https://control.akuma-stream.com/watch/abc</video:player_loc>',
            $xml
        );
        $this->assertStringContainsString('<video:thumbnail_loc>https://img.example.com/cover.jpg</video:thumbnail_loc>', $xml);
        $this->assertStringContainsString('<video:title>Sitemap Anime — ตอนที่ 1</video:title>', $xml);
        $this->assertStringContainsString('<video:description>เรื่องย่อ</video:description>', $xml);
    }

    public function test_episode_without_a_resolved_player_gets_no_video_entry(): void
    {
        $listId = $this->seedEpisode(null);

        $xml = $this->get('/sitemap-episodes-1.xml')->streamedContent();

        // The URL is still listed; only the video tag is withheld.
        $this->assertStringContainsString("/episode/{$listId}", $xml);
        $this->assertStringNotContainsString('<video:video>', $xml);
    }

    public function test_video_entry_is_dropped_when_the_series_has_no_artwork(): void
    {
        $this->seedEpisode('https://control.akuma-stream.com/watch/abc', '');

        $xml = $this->get('/sitemap-episodes-1.xml')->streamedContent();

        // A <video:video> without thumbnail_loc is a sitemap error, so the whole
        // tag is withheld rather than shipped incomplete.
        $this->assertStringNotContainsString('<video:video>', $xml);
    }

    public function test_video_title_and_description_are_xml_escaped(): void
    {
        $animeId = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Tom & Jerry <Special>',
            'cat_desc' => 'A & B',
            'cat_image' => 'https://img.example.com/cover.jpg',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
        $listId = DB::table('yu_anime_list')->insertGetId([
            'catagory_id' => $animeId, 'list_title' => 'EP 1', 'uuid' => 'u',
            'list_url' => 'https://example.com/one', 'adddate' => now(),
        ]);
        EpisodePlayerUrl::create([
            'list_id' => $listId,
            'watch_url' => 'https://control.akuma-stream.com/watch/abc',
            'resolved_at' => now(),
        ]);

        $xml = $this->get('/sitemap-episodes-1.xml')->streamedContent();

        $this->assertStringContainsString('Tom &amp; Jerry &lt;Special&gt;', $xml);
        $this->assertStringNotContainsString('<Special>', $xml);
    }

    public function test_zero_dates_from_the_imported_tables_emit_no_lastmod(): void
    {
        $animeId = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Zero Date Anime',
            'cat_image' => 'https://img.example.com/cover.jpg',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
        DB::table('yu_anime_list')->insert([
            'catagory_id' => $animeId, 'list_title' => 'EP 1', 'uuid' => 'u',
            'list_url' => 'https://example.com/one', 'adddate' => null,
        ]);

        $xml = $this->get('/sitemap-episodes-1.xml')->streamedContent();

        // Carbon renders a zero date as year -0001; that must never ship.
        $this->assertStringNotContainsString('-0001', $xml);
        $this->assertStringNotContainsString('<lastmod></lastmod>', $xml);
    }

    public function test_keyset_pagination_walks_every_episode_exactly_once(): void
    {
        $animeId = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Long Runner',
            'cat_image' => 'https://img.example.com/cover.jpg',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);

        // Chunks are 1000 rows, so this crosses two boundaries — where an
        // off-by-one in the cursor would drop or repeat an episode.
        $rows = [];
        for ($i = 1; $i <= 2500; $i++) {
            $rows[] = [
                'catagory_id' => $animeId, 'list_title' => "EP {$i}",
                'uuid' => "u{$i}", 'list_url' => "https://example.com/{$i}",
                'adddate' => now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('yu_anime_list')->insert($batch);
        }

        $xml = $this->get('/sitemap-episodes-1.xml')->streamedContent();

        $this->assertStringEndsWith('</urlset>', $xml);

        preg_match_all('#<loc>[^<]*/episode/(\d+)</loc>#', $xml, $matches);
        $ids = $matches[1];

        $this->assertCount(2500, $ids, 'every episode should appear');
        $this->assertSame(count($ids), count(array_unique($ids)), 'no episode should appear twice');
    }

    public function test_episodes_with_a_non_numeric_catagory_id_are_skipped(): void
    {
        $animeId = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Good Series',
            'cat_image' => 'https://img.example.com/cover.jpg',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
        DB::table('yu_anime_list')->insert([
            'catagory_id' => $animeId, 'list_title' => 'EP 1', 'uuid' => 'u1',
            'list_url' => 'https://example.com/1', 'adddate' => now(),
        ]);
        // Imported rows sometimes hold a series title here instead of an id.
        // This exact shape — a title containing "&" — made the live
        // 45k-URL sitemap fail XML parsing outright.
        DB::table('yu_anime_list')->insert([
            'catagory_id' => 'Tom and Jerry Cartoons ทอม & เจ', 'list_title' => 'EP 2',
            'uuid' => 'u2', 'list_url' => 'https://example.com/2', 'adddate' => now(),
        ]);

        $xml = $this->get('/sitemap-episodes-1.xml')->streamedContent();

        $this->assertStringContainsString("/anime/{$animeId}/episode/", $xml);
        $this->assertStringNotContainsString('Tom and Jerry', $xml);
        $this->assertStringEndsWith('</urlset>', $xml);
    }

    public function test_episode_sitemap_parses_as_xml(): void
    {
        DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Tom & Jerry <Complete>',
            'cat_image' => 'https://img.example.com/cover.jpg',
            'cat_type' => 1, 'cat_update' => now(),
        ]);
        DB::table('yu_anime_list')->insert([
            'catagory_id' => 'Bad & Broken <id>', 'list_title' => 'EP 1',
            'uuid' => 'u1', 'list_url' => 'https://example.com/1', 'adddate' => now(),
        ]);

        $xml = $this->get('/sitemap-episodes-1.xml')->streamedContent();

        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($parsed, 'sitemap must parse as XML');
        $this->assertSame([], $errors);
    }

    public function test_robots_disallows_every_member_route(): void
    {
        $body = $this->get('/robots.txt')->getContent();

        // /member/settings/* and /member/bookmarks answer a guest with a 302 to
        // login, so crawling any of them is a wasted hop.
        $this->assertStringContainsString('Disallow: /member/', $body);
    }

    public function test_llms_txt_describes_the_site_for_ai_crawlers(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertStringStartsWith('# ', $body);
        $this->assertStringContainsString('/category/1', $body);
        $this->assertStringContainsString('/studios', $body);
        $this->assertStringContainsString('/sitemap.xml', $body);
    }
}
