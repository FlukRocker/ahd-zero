<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class BookmarkPageTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 801, 'cat_title' => 'Mine Alone', 'cat_type' => 1, 'cat_update' => now()],
            ['cat_id' => 802, 'cat_title' => 'Someone Elses', 'cat_type' => 1, 'cat_update' => now()],
        ]);
    }

    private function member(string $email): Member
    {
        return Member::create([
            'name' => 'Rin',
            'email' => $email,
            'password' => 'password123!',
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/member/bookmarks')->assertRedirect('/member/login');
    }

    public function test_page_shows_only_the_authenticated_members_bookmarks(): void
    {
        $mine = $this->member('mine@example.test');
        $theirs = $this->member('theirs@example.test');

        Bookmark::create(['member_id' => $mine->id, 'cat_id' => 801]);
        Bookmark::create(['member_id' => $theirs->id, 'cat_id' => 802]);

        $response = $this->actingAs($mine, 'member')->get('/member/bookmarks');

        $response->assertOk();
        $response->assertSee('Mine Alone', false);
        $response->assertDontSee('Someone Elses', false);
    }

    public function test_page_is_noindex(): void
    {
        $response = $this->actingAs($this->member('rin@example.test'), 'member')->get('/member/bookmarks');

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    public function test_bookmark_with_a_missing_anime_row_is_skipped(): void
    {
        $member = $this->member('rin@example.test');
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 801]);
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 999999]);

        $response = $this->actingAs($member, 'member')->get('/member/bookmarks');

        $response->assertOk();
        $response->assertSee('Mine Alone', false);
    }

    public function test_empty_state_renders(): void
    {
        $response = $this->actingAs($this->member('rin@example.test'), 'member')->get('/member/bookmarks');

        $response->assertOk();
        $response->assertSee('ยังไม่มีรายการที่บันทึกไว้', false);
    }
}
