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

    public function test_member_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/member/register', [
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
            'password_confirmation' => 'password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs(Member::where('email', 'rin@example.test')->first(), 'member');
    }

    public function test_member_can_login_with_correct_credentials(): void
    {
        Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => Hash::make('password123!'),
        ]);

        $response = $this->post('/member/login', [
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated('member');
    }

    public function test_banned_member_cannot_login(): void
    {
        Member::create([
            'name' => 'Banned',
            'email' => 'banned@example.test',
            'password' => Hash::make('password123!'),
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
