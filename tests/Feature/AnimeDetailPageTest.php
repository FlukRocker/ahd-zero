<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnimeDetailPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_anime_detail_renders_server_side_with_episodes_and_schema(): void
    {
        $id = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Detailed Anime Title',
            'cat_desc' => '<p>A great synopsis for the anime.</p>',
            'cat_type' => 1,
            'anime_status' => 'Finished Airing',
            'episodes' => 12,
            'cat_update' => now(),
        ]);
        DB::table('yu_anime_list')->insert([
            ['catagory_id' => $id, 'list_title' => 'ตอนที่ 1', 'uuid' => 'u1', 'adddate' => now()],
            ['catagory_id' => $id, 'list_title' => 'ตอนที่ 2', 'uuid' => 'u2', 'adddate' => now()],
        ]);

        $response = $this->get('/anime/'.$id);

        $response->assertOk();
        $response->assertViewIs('anime');
        // Server-rendered content.
        $response->assertSee('Detailed Anime Title', false);
        $response->assertSee('A great synopsis for the anime.', false);
        // Episode links to the watch route.
        $response->assertSee('/anime/'.$id.'/episode/', false);
        $response->assertSee('ตอนที่ 1', false);
        // SEO: TVSeries + breadcrumb JSON-LD, and no Inertia shell.
        $response->assertSee('TVSeries', false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertDontSee('id="app" data-page', false);
    }

    public function test_missing_anime_returns_404(): void
    {
        $this->get('/anime/99999')->assertStatus(404);
    }

    /**
     * A sub and a dub record share one synopsis and differ only in the variant
     * word at the very end of the title, so both the <title> and the meta
     * description used to come out identical for the pair.
     *
     * @return array{0:string,1:string}
     */
    private function seoFor(string $catTitle): array
    {
        $id = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => $catTitle,
            'cat_desc' => '<p>เรื่องราวของ ไคลน์ โมเรตติ&nbsp;ยังเป็นสมาชิก</p>',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);

        $html = $this->get("/anime/{$id}")->getContent();
        preg_match('#<title>(.*?)</title>#s', $html, $t);
        preg_match('#<meta name="description" content="(.*?)"#s', $html, $d);

        // Returned raw, exactly as served: the entity bug was a double escape
        // ("&amp;amp;nbsp;"), which decoding here would hide.
        return [$t[1] ?? '', $d[1] ?? ''];
    }

    public function test_long_sub_and_dub_titles_do_not_collide(): void
    {
        $base = 'Tsuihou sareta Tensei Juukishi wa Game Chishiki de Musou suru เกิดใหม่เป็นอัศวินเกราะหนักผู้ถูกขับไล่ ';
        [$subTitle, $subDesc] = $this->seoFor($base.'ซับไทย');
        [$dubTitle, $dubDesc] = $this->seoFor($base.'พากย์ไทย');

        $this->assertNotSame($subTitle, $dubTitle, '<title> must distinguish sub from dub');
        $this->assertNotSame($subDesc, $dubDesc, 'meta description must distinguish sub from dub');

        // The variant is the highest-volume keyword set; truncation must keep it.
        $this->assertStringContainsString('ซับไทย', $subTitle);
        $this->assertStringContainsString('พากย์ไทย', $dubTitle);
    }

    public function test_meta_description_decodes_entities_from_the_imported_synopsis(): void
    {
        [, $desc] = $this->seoFor('Lord of Mysteries ราชันเร้นลับ ซับไทย');

        // The synopsis holds "&nbsp;", which was escaped twice on the way out
        // and reached the SERP snippet as the literal text "&amp;nbsp;".
        $this->assertStringNotContainsString('nbsp', $desc);
        $this->assertStringNotContainsString('&amp;amp;', $desc);
        $this->assertStringNotContainsString('<p>', $desc);
    }

    public function test_related_anime_rail_lists_only_titles_we_host(): void
    {
        $mk = fn (string $title): int => DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => $title, 'cat_type' => 1, 'cat_update' => now(),
        ]);

        $anime = $mk('Main Series ซับไทย');
        $sequel = $mk('Sequel Series ซับไทย');

        DB::table('anime_relations')->insert([
            // Hosted — earns a card.
            ['anime_id' => $anime, 'related_anime_id' => $sequel, 'related_mal_id' => 1,
                'related_title' => 'Sequel Series ซับไทย', 'relation_type' => 'Sequel'],
            // Not in the catalogue: a card here would link nowhere.
            ['anime_id' => $anime, 'related_anime_id' => null, 'related_mal_id' => 54857,
                'related_title' => 'Unhosted Prequel', 'relation_type' => 'Prequel'],
        ]);

        $html = $this->get("/anime/{$anime}")->getContent();

        $this->assertStringContainsString('อนิเมะที่เกี่ยวข้อง', $html);
        $this->assertStringContainsString("/anime/{$sequel}", $html);
        $this->assertStringNotContainsString('Unhosted Prequel', $html);
    }

    public function test_related_rail_is_hidden_when_nothing_related_is_hosted(): void
    {
        $anime = DB::table('yu_anime_catagory')->insertGetId([
            'cat_title' => 'Lonely Series ซับไทย', 'cat_type' => 1, 'cat_update' => now(),
        ]);
        DB::table('anime_relations')->insert([
            ['anime_id' => $anime, 'related_anime_id' => null, 'related_mal_id' => 54857,
                'related_title' => 'Unhosted Prequel', 'relation_type' => 'Prequel'],
        ]);

        $html = $this->get("/anime/{$anime}")->getContent();

        $this->assertStringNotContainsString('อนิเมะที่เกี่ยวข้อง', $html);
    }
}
