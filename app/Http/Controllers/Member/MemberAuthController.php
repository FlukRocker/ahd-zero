<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

use function abort_if;
use function back;
use function redirect;

class MemberAuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Member/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::guard('member')->attempt($credentials, $request->boolean('remember'))) {
            /** @var Member $member */
            $member = Auth::guard('member')->user();

            if ($member->banned_at !== null) {
                Auth::guard('member')->logout();

                return back()->withErrors([
                    'email' => 'บัญชีของคุณถูกระงับ: '.($member->ban_reason ?? 'ติดต่อผู้ดูแลระบบ'),
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
        ])->onlyInput('email');
    }

    public function showRegister(): Response
    {
        abort_if(! SiteSettings::registrationEnabled(), 403, 'ปิดรับสมาชิกใหม่ในขณะนี้');

        return Inertia::render('Member/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_if(! SiteSettings::registrationEnabled(), 403, 'ปิดรับสมาชิกใหม่ในขณะนี้');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:members'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $member = Member::create($validated);

        Auth::guard('member')->login($member);

        return redirect('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
