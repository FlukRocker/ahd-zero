<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookmarkModelTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Member
    {
        return Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);
    }

    public function test_member_has_many_bookmarks(): void
    {
        DB::table('yu_anime_catagory')->insert([
            'cat_id' => 501, 'cat_title' => 'Saved Show', 'cat_type' => 1, 'cat_update' => now(),
        ]);

        $member = $this->member();
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 501]);

        $this->assertCount(1, $member->bookmarks()->get());
        $this->assertSame('Saved Show', $member->bookmarks()->first()->anime->cat_title);
    }

    public function test_duplicate_bookmark_is_rejected_by_the_database(): void
    {
        $member = $this->member();
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 502]);

        $this->expectException(QueryException::class);
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 502]);
    }

    public function test_deleting_a_member_deletes_their_bookmarks(): void
    {
        $member = $this->member();
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 503]);

        $member->delete();

        $this->assertSame(0, Bookmark::query()->count());
    }
}
