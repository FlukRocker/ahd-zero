<?php

namespace Tests\Unit;

use App\Models\EpisodePlayerUrl;
use Tests\TestCase;

/**
 * The stored rows hold an absolute URL, so the akuma-stream hostname is baked
 * into ~48k rows. When that host changed (control. → app.) every one of them
 * kept pointing at a domain that no longer resolves, and nothing short of the
 * sweep rewrote them. Reading rebases the stored path onto the configured
 * host so the config stays the single source of truth for where the player
 * lives.
 */
class EpisodePlayerUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.akuma_stream.url' => 'https://app.akuma-stream.com']);
    }

    public function test_a_retired_akuma_stream_host_is_replaced_with_the_configured_one(): void
    {
        $this->assertSame(
            'https://app.akuma-stream.com/watch/e7d4c0d8-179e-4f60-a797-e5aa61db1882',
            EpisodePlayerUrl::rebase('https://control.akuma-stream.com/watch/e7d4c0d8-179e-4f60-a797-e5aa61db1882'),
        );
    }

    public function test_an_already_correct_url_is_untouched(): void
    {
        $url = 'https://app.akuma-stream.com/watch/e7d4c0d8-179e-4f60-a797-e5aa61db1882';

        $this->assertSame($url, EpisodePlayerUrl::rebase($url));
    }

    public function test_a_third_party_player_url_is_left_alone(): void
    {
        // 264 rows hold a direct link on someone else's host. Those are not
        // akuma-stream videos and must not be pointed at it.
        $url = 'https://app.koofr.net/links/0d083efe-8bb7-43f2-a7a1-e225a6109841';

        $this->assertSame($url, EpisodePlayerUrl::rebase($url));
    }

    public function test_junk_left_by_the_old_pass_through_is_dropped(): void
    {
        // 208 rows hold what was in list_url before PlayerService started
        // rejecting non-URLs: a dash, or the episode title. Returning null
        // sends the page down the live-resolve path instead of putting that
        // string in an iframe src.
        $this->assertNull(EpisodePlayerUrl::rebase('-'));
        $this->assertNull(EpisodePlayerUrl::rebase('---'));
        $this->assertNull(EpisodePlayerUrl::rebase('Boruto Naruto Next Generations โบรูโตะ 10 พากย์ไทย'));
        $this->assertNull(EpisodePlayerUrl::rebase(null));
        $this->assertNull(EpisodePlayerUrl::rebase(''));
    }

    public function test_a_query_string_survives_the_rebase(): void
    {
        $this->assertSame(
            'https://app.akuma-stream.com/watch/abc?t=90',
            EpisodePlayerUrl::rebase('https://control.akuma-stream.com/watch/abc?t=90'),
        );
    }

    public function test_the_configured_host_is_honoured_even_if_it_changes_again(): void
    {
        config(['services.akuma_stream.url' => 'https://stream2.akuma-stream.com/']);

        $this->assertSame(
            'https://stream2.akuma-stream.com/watch/abc',
            EpisodePlayerUrl::rebase('https://app.akuma-stream.com/watch/abc'),
        );
    }
}
