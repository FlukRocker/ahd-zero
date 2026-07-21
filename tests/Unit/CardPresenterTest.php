<?php

namespace Tests\Unit;

use App\Support\CardPresenter;
use PHPUnit\Framework\TestCase;

class CardPresenterTest extends TestCase
{
    public function test_poster_fallback_chain_prefers_cover_md(): void
    {
        $card = CardPresenter::make([
            'cat_id' => 5,
            'cat_title' => 'X',
            'cover_md' => 'https://img-cdn.shirokami.me/a.md.jpg',
            'cat_image' => 'https://img-cdn.shirokami.me/b.jpg',
        ]);

        $this->assertSame('https://img-cdn.shirokami.me/a.md.jpg', $card['poster']);
        $this->assertSame('/anime/5', $card['href']);
        $this->assertSame(5, $card['id']);
    }

    public function test_poster_falls_back_to_placeholder_when_all_missing(): void
    {
        $card = CardPresenter::make(['cat_id' => 1, 'cat_title' => 'Y']);
        $this->assertSame('/images/placeholder-poster.webp', $card['poster']);
        $this->assertSame('/images/placeholder-poster.webp', $card['landscape']);
    }

    public function test_tag_by_type_and_status(): void
    {
        $this->assertSame('กำลังฉาย', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'anime_status' => 'Currently Airing'])['tag']);
        $this->assertSame('ซับไทย', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 1])['tag']);
        $this->assertSame('พากย์ไทย', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 2])['tag']);
        $this->assertSame('มูฟวี่', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 3])['tag']);
    }

    public function test_ep_string_uses_count_or_movie_label(): void
    {
        $this->assertSame('12 ตอน', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 1, 'episode_list_count' => 12])['ep']);
        $this->assertSame('มูฟวี่', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 3, 'episodes' => 1])['ep']);
        $this->assertSame('', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 1])['ep']);
    }

    public function test_collection_maps_each_item(): void
    {
        $out = CardPresenter::collection([
            ['cat_id' => 1, 'cat_title' => 'A'],
            ['cat_id' => 2, 'cat_title' => 'B'],
        ]);
        $this->assertCount(2, $out);
        $this->assertSame('/anime/2', $out[1]['href']);
    }
}
