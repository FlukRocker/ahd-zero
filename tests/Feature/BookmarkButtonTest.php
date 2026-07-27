<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class BookmarkButtonTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // AnimeController caches detail data in the array store.
        Cache::flush();

        DB::table('yu_anime_catagory')->insert([
            'cat_id' => 701, 'cat_title' => 'Button Show', 'cat_type' => 1, 'cat_update' => now(),
        ]);
    }

    private function member(string $email = 'rin@example.test'): Member
    {
        return Member::create([
            'name' => 'Rin',
            'email' => $email,
            'password' => 'password123!',
        ]);
    }

    public function test_guest_sees_a_login_link_instead_of_a_toggle(): void
    {
        $response = $this->get('/anime/701');

        $response->assertOk();
        // data-bookmarked="guest" is unique to this component. Asserting on a
        // bare /member/login href would pass on the site header's login link
        // alone, even if the component never rendered.
        $response->assertSee('data-bookmarked="guest"', false);
        $response->assertDontSee('data-bookmarked="false"', false);
    }

    public function test_member_sees_an_unset_toggle_when_not_bookmarked(): void
    {
        $response = $this->actingAs($this->member(), 'member')->get('/anime/701');

        $response->assertOk();
        $response->assertSee('data-bookmarked="false"', false);
    }

    public function test_member_sees_a_set_toggle_when_bookmarked(): void
    {
        $member = $this->member();
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 701]);

        $response = $this->actingAs($member, 'member')->get('/anime/701');

        $response->assertOk();
        $response->assertSee('data-bookmarked="true"', false);
    }

    public function test_one_members_bookmark_does_not_leak_to_another(): void
    {
        $owner = $this->member('owner@example.test');
        Bookmark::create(['member_id' => $owner->id, 'cat_id' => 701]);

        // The owner's request populates AnimeController's shared
        // `anime:detail:v2:{id}` cache entry (do not flush between requests —
        // the whole point of this test is that the second request below
        // hits that same warm cache).
        $ownerResponse = $this->actingAs($owner, 'member')->get('/anime/701');
        $ownerResponse->assertOk();
        $ownerResponse->assertSee('data-bookmarked="true"', false);

        // A different member requesting the same page — while the cache is
        // still warm from the owner's request above — must see their own
        // (unbookmarked) state, not the owner's. If member-specific data
        // were ever baked into the shared cache closure, this request would
        // incorrectly see "true".
        $other = $this->member('other@example.test');
        $otherResponse = $this->actingAs($other, 'member')->get('/anime/701');

        $otherResponse->assertOk();
        $otherResponse->assertSee('data-bookmarked="false"', false);
    }
}
