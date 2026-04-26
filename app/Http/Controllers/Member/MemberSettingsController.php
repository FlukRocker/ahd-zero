<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

use function abort;
use function back;
use function redirect;

class MemberSettingsController extends Controller
{
    public function profile(Request $request): Response
    {
        $member = $request->user('member');
        if (! $member instanceof Member) {
            abort(401);
        }

        return Inertia::render('Member/Settings/Profile', [
            'member' => [
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar,
                'bio' => $member->bio,
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $member = $request->user('member');
        if (! $member instanceof Member) {
            abort(401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:1024', 'url'],
            'bio' => ['nullable', 'string', 'max:500'],
        ]);

        $member->update($validated);

        return back()->with('status', 'profile-updated');
    }

    public function password(Request $request): Response
    {
        return Inertia::render('Member/Settings/Password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $member = $request->user('member');
        if (! $member instanceof Member) {
            abort(401);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $member->password)) {
            return back()->withErrors([
                'current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
            ]);
        }

        $member->update(['password' => $validated['password']]);

        return back()->with('status', 'password-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $member = $request->user('member');
        if (! $member instanceof Member) {
            abort(401);
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->input('password'), $member->password)) {
            return back()->withErrors([
                'password' => 'รหัสผ่านไม่ถูกต้อง',
            ]);
        }

        // Logout first so the deleted user is not still authenticated.
        auth()->guard('member')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $member->delete();

        return redirect('/');
    }
}
