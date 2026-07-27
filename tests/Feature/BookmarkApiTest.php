<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class BookmarkApiTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        DB::table('yu_anime_catagory')->insert([
            'cat_id' => 601, 'cat_title' => 'Bookmarkable', 'cat_type' => 1, 'cat_update' => now(),
        ]);

        $this->member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);
    }

    public function test_member_can_bookmark_an_anime(): void
    {
        $response = $this->actingAs($this->member, 'member')
            ->postJson('/member/bookmarks', ['cat_id' => 601]);

        $response->assertOk()->assertJson(['bookmarked' => true]);
        $this->assertDatabaseHas('member_bookmarks', [
            'member_id' => $this->member->id,
            'cat_id' => 601,
        ]);
    }

    public function test_bookmarking_twice_does_not_duplicate_or_error(): void
    {
        $this->actingAs($this->member, 'member')->postJson('/member/bookmarks', ['cat_id' => 601]);
        $second = $this->actingAs($this->member, 'member')->postJson('/member/bookmarks', ['cat_id' => 601]);

        $second->assertOk()->assertJson(['bookmarked' => true]);
        $this->assertSame(1, Bookmark::query()->count());
    }

    public function test_member_can_remove_a_bookmark(): void
    {
        Bookmark::create(['member_id' => $this->member->id, 'cat_id' => 601]);

        $response = $this->actingAs($this->member, 'member')
            ->deleteJson('/member/bookmarks/601');

        $response->assertOk()->assertJson(['bookmarked' => false]);
        $this->assertSame(0, Bookmark::query()->count());
    }

    public function test_removing_a_bookmark_that_does_not_exist_still_succeeds(): void
    {
        $response = $this->actingAs($this->member, 'member')
            ->deleteJson('/member/bookmarks/601');

        $response->assertOk()->assertJson(['bookmarked' => false]);
    }

    public function test_unknown_anime_is_rejected(): void
    {
        $response = $this->actingAs($this->member, 'member')
            ->postJson('/member/bookmarks', ['cat_id' => 999999]);

        $response->assertStatus(422);
    }

    public function test_guests_cannot_write_bookmarks(): void
    {
        $this->postJson('/member/bookmarks', ['cat_id' => 601])->assertStatus(401);
        $this->deleteJson('/member/bookmarks/601')->assertStatus(401);
    }

    public function test_a_member_cannot_delete_another_members_bookmark(): void
    {
        $other = Member::create([
            'name' => 'Other',
            'email' => 'other@example.test',
            'password' => 'password123!',
        ]);
        Bookmark::create(['member_id' => $other->id, 'cat_id' => 601]);

        $this->actingAs($this->member, 'member')->deleteJson('/member/bookmarks/601')->assertOk();

        $this->assertSame(1, Bookmark::query()->where('member_id', $other->id)->count());
    }
}
