<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminMemberLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'boss@example.test', string $name = 'Boss'): User
    {
        return User::factory()->create([
            'email' => $email,
            'name' => $name,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function test_admin_login_creates_and_signs_in_a_verified_member(): void
    {
        $admin = $this->admin();

        Auth::guard('web')->login($admin);

        $member = Member::where('email', 'boss@example.test')->firstOrFail();
        $this->assertSame('Boss', $member->name);
        $this->assertNotNull($member->email_verified_at);
        $this->assertTrue(Auth::guard('member')->check());
        $this->assertSame($member->id, Auth::guard('member')->id());
    }

    public function test_second_login_reuses_the_member_and_syncs_the_name(): void
    {
        $admin = $this->admin();
        Auth::guard('web')->login($admin);
        Auth::guard('web')->logout();
        Auth::guard('member')->logout();

        $admin->forceFill(['name' => 'New Boss'])->save();
        Auth::guard('web')->login($admin);

        $this->assertSame(1, Member::where('email', 'boss@example.test')->count());
        $this->assertSame('New Boss', Member::where('email', 'boss@example.test')->first()->name);
    }

    public function test_an_existing_verified_member_is_adopted_without_touching_its_password(): void
    {
        $member = Member::create(['name' => 'Old', 'email' => 'boss@example.test', 'password' => 'secret-pass-1']);
        $member->forceFill(['email_verified_at' => now()])->save();

        Auth::guard('web')->login($this->admin());

        $this->assertSame($member->id, Auth::guard('member')->id());
        $this->assertTrue(Hash::check('secret-pass-1', $member->fresh()->password));
    }

    public function test_an_unverified_squatter_is_locked_out_not_adopted_as_is(): void
    {
        $squat = Member::create(['name' => 'Squat', 'email' => 'boss@example.test', 'password' => 'attacker-pass-1']);

        Auth::guard('web')->login($this->admin());

        $squat->refresh();
        $this->assertFalse(Hash::check('attacker-pass-1', $squat->password));
        $this->assertNotNull($squat->email_verified_at);
        $this->assertSame($squat->id, Auth::guard('member')->id());
    }

    public function test_a_banned_member_is_not_signed_in(): void
    {
        $member = Member::create(['name' => 'Banned', 'email' => 'boss@example.test', 'password' => 'secret-pass-1']);
        $member->forceFill(['email_verified_at' => now(), 'banned_at' => now()])->save();

        Auth::guard('web')->login($this->admin());

        $this->assertFalse(Auth::guard('member')->check());
    }

    public function test_a_member_login_does_not_sign_in_an_admin(): void
    {
        $this->admin();
        $member = Member::create(['name' => 'Rin', 'email' => 'boss@example.test', 'password' => 'secret-pass-1']);

        Auth::guard('member')->login($member);

        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_posting_the_admin_login_form_also_signs_in_the_member(): void
    {
        $this->post('/login', ['email' => $this->admin()->email, 'password' => 'password']);

        $this->assertTrue(Auth::guard('web')->check());
        $this->assertTrue(Auth::guard('member')->check());
    }

    public function test_admin_logout_ends_the_member_session_too(): void
    {
        $this->post('/login', ['email' => $this->admin()->email, 'password' => 'password']);

        $this->post('/logout');
        // The next request starts from the invalidated session, not from guard
        // instances cached inside this test process.
        $this->app['auth']->forgetGuards();

        $this->assertFalse(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('member')->check());
    }
}
