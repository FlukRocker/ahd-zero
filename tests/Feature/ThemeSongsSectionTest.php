<?php

namespace Tests\Feature;

use Tests\TestCase;

class ThemeSongsSectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_it_renders_players_built_from_validated_ids(): void
    {
        $view = $this->blade('<x-theme-songs :opening="$opening" :ending="$ending" />', [
            'opening' => [['name' => 'Kaikai Kitan', 'spotify' => 'track/1N3Vhf6z0iJ1Yc1F1aT1z1', 'youtube' => '1tk1pqwrOys']],
            'ending' => ['<p>"Lost in Paradise"<span> by ALI</span></p>'],
        ]);

        $view->assertSee('เพลงประกอบ');
        $view->assertSee('src="https://open.spotify.com/embed/track/1N3Vhf6z0iJ1Yc1F1aT1z1" height="80"', false);
        $view->assertSee('<theme-yt-player video-id="1tk1pqwrOys" song="Kaikai Kitan"></theme-yt-player>', false);
        $view->assertSee('&quot;Lost in Paradise&quot; by ALI', false);
        $view->assertDontSee('&lt;span', false);
    }

    public function test_a_song_name_cannot_inject_markup(): void
    {
        $view = $this->blade('<x-theme-songs :opening="$opening" :ending="[]" />', [
            'opening' => [['name' => 'A "quoted" & <img src=x onerror=alert(1)>song', 'youtube' => '1tk1pqwrOys']],
        ]);

        $view->assertDontSee('onerror', false);
        $view->assertSee('song="A &quot;quoted&quot; &amp; song"', false);
    }

    public function test_nothing_renders_without_songs(): void
    {
        $view = $this->blade('<x-theme-songs :opening="null" :ending="[]" />');

        $view->assertDontSee('เพลงประกอบ');
    }
}
