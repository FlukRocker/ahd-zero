<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/member/login')->assertOk();
    }

    public function test_register_page_renders(): void
    {
        $this->get('/member/register')->assertOk();
    }

    public function test_member_register_sends_verification_and_does_not_log_in(): void
    {
        $response = $this->post('/member/register', [
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
            'password_confirmation' => 'password123!',
        ]);

        // Registration must NOT auto-login — user must verify email first.
        $response->assertRedirect('/member/email/verify');
        $this->assertGuest('member');

        $member = Member::where('email', 'rin@example.test')->first();
        $this->assertNotNull($member);
        $this->assertNull($member->email_verified_at);
    }

    public function test_verified_member_can_login(): void
    {
        Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => Hash::make('password123!'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/member/login', [
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated('member');
    }

    public function test_unverified_member_login_redirects_to_verify_page(): void
    {
        Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => Hash::make('password123!'),
            // email_verified_at left null on purpose
        ]);

        $response = $this->post('/member/login', [
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);

        $response->assertRedirect('/member/email/verify');
        $this->assertGuest('member');
    }

    public function test_banned_member_cannot_login(): void
    {
        // Verified email so the unverified-redirect doesn't shadow the
        // banned-account error path under test.
        Member::create([
            'name' => 'Banned',
            'email' => 'banned@example.test',
            'password' => Hash::make('password123!'),
            'email_verified_at' => now(),
            'banned_at' => now(),
            'ban_reason' => 'Spam',
        ]);

        $response = $this->post('/member/login', [
            'email' => 'banned@example.test',
            'password' => 'password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('member');
    }

    public function test_invalid_login_shows_error(): void
    {
        $response = $this->post('/member/login', [
            'email' => 'nobody@example.test',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('member');
    }
}
