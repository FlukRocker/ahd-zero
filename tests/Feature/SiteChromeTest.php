<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SiteChromeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_guest_header_and_footer_render(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // Primary nav (server-rendered).
        $response->assertSee('ซับไทย', false);
        $response->assertSee('เดอะมูฟวี่', false);
        // Search overlay form points at the results route.
        $response->assertSee('action="'.route('search.results').'"', false);
        // Theme toggle present.
        $response->assertSee('สลับธีมสว่าง/มืด', false);
        // Guest sees a login link, not a logout form.
        $response->assertSee('/member/login', false);
        $response->assertDontSee('action="/member/logout"', false);
        // Footer.
        $response->assertSee('© '.date('Y').' Anime HD Zero', false);
    }

    public function test_member_header_shows_user_menu_and_logout(): void
    {
        $member = Member::create([
            'name' => 'Rin Tester',
            'email' => 'rin-chrome@example.test',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->actingAs($member, 'member')->get('/');

        $response->assertOk();
        // First name in the user-menu trigger.
        $response->assertSee('Rin', false);
        // Logout form present.
        $response->assertSee('action="/member/logout"', false);
    }
}
