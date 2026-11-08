<?php

namespace App\Listeners;

use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Signs an admin into the public site as a linked Member.
 *
 * The link is the e-mail address (members.email is unique), so no schema
 * change is needed. The Member row is created on first admin login and its
 * name is re-synced on every later one.
 *
 * Adoption rules for a Member that already owns the address:
 *  - banned: never signed in;
 *  - verified: adopted as is, password untouched;
 *  - unverified: nobody has proven they own the address, so a squatter could
 *    have registered it ahead of the admin. The password is replaced with a
 *    random one and the address marked verified, which locks the squatter out.
 */
class LinkAdminToMember
{
    public function handle(Login $event): void
    {
        $admin = $event->user;

        if ($event->guard !== 'web' || ! $admin instanceof User || $admin->email === '') {
            return;
        }

        $member = Member::where('email', $admin->email)->first();

        if ($member?->banned_at !== null) {
            return;
        }

        if ($member === null) {
            $member = new Member(['email' => $admin->email]);
            $member->forceFill(['email_verified_at' => now()]);
        } elseif ($member->email_verified_at === null) {
            $member->forceFill(['email_verified_at' => now()]);
            $member->password = Str::random(48);
        }

        $member->name = $admin->name;
        if (! $member->exists) {
            $member->password = Str::random(48);
        }
        $member->save();

        Auth::guard('member')->login($member);
    }
}
