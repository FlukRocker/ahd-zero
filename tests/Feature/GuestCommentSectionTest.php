<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class GuestCommentSectionTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->instance(AnalyticsService::class, new EmptyAnalytics);

        DB::table('yu_anime_catagory')->insert([
            'cat_id' => 701,
            'cat_title' => 'Commentable Show',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);
    }

    private function member(): Member
    {
        return Member::create([
            'name' => 'Commenter',
            'email' => 'commenter@example.test',
            'password' => 'password-for-test',
            'email_verified_at' => now(),
        ]);
    }

    public function test_guests_see_the_comment_section(): void
    {
        $response = $this->get('/anime/701');

        $response->assertOk();
        $response->assertSee('ความคิดเห็น', false);
    }

    public function test_guests_are_offered_login_and_registration_instead_of_a_composer(): void
    {
        $response = $this->get('/anime/701');

        $response->assertSee('เข้าสู่ระบบเพื่อร่วมแสดงความคิดเห็น', false);
        $response->assertSee('/member/login', false);
        // The composer must not be reachable — the write API would reject it
        // anyway, so offering it to a guest is a dead end.
        $response->assertDontSee('เขียนความคิดเห็น...', false);
    }

    public function test_the_comment_list_is_requested_for_guests_too(): void
    {
        // The whole point: reading is public, so the component must be wired
        // up for signed-out visitors rather than rendered only when logged in.
        $this->get('/anime/701')->assertSee("commentSection('anime', 701, false)", false);
    }

    public function test_members_get_the_composer_and_not_the_login_prompt(): void
    {
        $response = $this->actingAs($this->member(), 'member')->get('/anime/701');

        $response->assertSee('เขียนความคิดเห็น...', false);
        $response->assertDontSee('เข้าสู่ระบบเพื่อร่วมแสดงความคิดเห็น', false);
        $response->assertSee("commentSection('anime', 701, true)", false);
    }

    public function test_registration_link_is_hidden_when_registration_is_disabled(): void
    {
        config(['site.registration_enabled' => false]);

        $response = $this->get('/anime/701');

        $response->assertSee('/member/login', false);
        $response->assertDontSee('/member/register', false);
    }
}
