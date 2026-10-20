<?php

namespace Tests\Unit;

use App\Support\ThemeSongs;
use PHPUnit\Framework\TestCase;

class ThemeSongsTest extends TestCase
{
    private const TRACK = '1N3Vhf6z0iJ1Yc1F1aT1z1';

    public function test_a_legacy_string_becomes_a_song_without_players(): void
    {
        $this->assertSame(
            [['name' => '"Gurenge" by LiSA', 'spotify' => null, 'youtube' => null]],
            ThemeSongs::forDisplay(['"Gurenge" by LiSA'])
        );
    }

    public function test_html_in_a_legacy_string_is_reduced_to_text(): void
    {
        $songs = ThemeSongs::forDisplay(['<p>"10:15 Saturday Night"<span class="theme-song-artist"> by The Cure</span></p>']);

        $this->assertSame('"10:15 Saturday Night" by The Cure', $songs[0]['name']);
    }

    public function test_a_track_builds_the_compact_spotify_embed(): void
    {
        $songs = ThemeSongs::forDisplay([['name' => 'Kaikai Kitan', 'spotify' => 'track/'.self::TRACK, 'youtube' => null]]);

        $this->assertSame(['src' => 'https://open.spotify.com/embed/track/'.self::TRACK, 'height' => 80], $songs[0]['spotify']);
    }

    public function test_an_album_or_playlist_gets_the_taller_embed(): void
    {
        $songs = ThemeSongs::forDisplay([
            ['name' => 'A', 'spotify' => 'album/'.self::TRACK],
            ['name' => 'P', 'spotify' => 'playlist/'.self::TRACK],
        ]);

        $this->assertSame(152, $songs[0]['spotify']['height']);
        $this->assertSame(152, $songs[1]['spotify']['height']);
    }

    public function test_a_valid_youtube_id_is_kept(): void
    {
        $songs = ThemeSongs::forDisplay([['name' => 'Kaikai Kitan', 'youtube' => '1tk1pqwrOys']]);

        $this->assertSame('1tk1pqwrOys', $songs[0]['youtube']);
    }

    public function test_ids_that_are_not_exactly_an_id_are_dropped_but_the_song_stays(): void
    {
        $songs = ThemeSongs::forDisplay([
            ['name' => 'A', 'spotify' => 'track/'.self::TRACK."\n", 'youtube' => "1tk1pqwrOys\n"],
            ['name' => 'B', 'spotify' => 'https://evil.example/'.self::TRACK, 'youtube' => 'javascript:alert(1)'],
            ['name' => 'C', 'spotify' => 'artist/'.self::TRACK, 'youtube' => ['1tk1pqwrOys']],
        ]);

        $this->assertCount(3, $songs);
        foreach ($songs as $song) {
            $this->assertNull($song['spotify']);
            $this->assertNull($song['youtube']);
        }
    }

    public function test_malformed_items_are_skipped(): void
    {
        $this->assertSame([], ThemeSongs::forDisplay([
            42, null, ['spotify' => 'track/'.self::TRACK], ['name' => ['x']], ['name' => '  '], '<p></p>',
        ]));
    }

    public function test_a_column_that_is_not_a_list_gives_no_songs(): void
    {
        $this->assertSame([], ThemeSongs::forDisplay(null));
        $this->assertSame([], ThemeSongs::forDisplay('oops'));
    }

    public function test_names_are_text_not_markup(): void
    {
        $songs = ThemeSongs::forDisplay([['name' => '<script>alert(1)</script>Song']]);

        $this->assertSame('alert(1)Song', $songs[0]['name']);
    }
}
