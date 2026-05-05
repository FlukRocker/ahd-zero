<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Rules\TurnstileToken;
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
        $rules = [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];

        // Only enforce Turnstile when the secret is configured. Local dev,
        // tests, and any environment without Cloudflare creds skip both
        // the required-ness and the verify() round-trip.
        if (config('services.turnstile.secret_key')) {
            $rules['cf-turnstile-response'] = ['required', 'string', new TurnstileToken];
        }

        $request->validate($rules);

        $credentials = $request->only('email', 'password');

        if (Auth::guard('member')->attempt($credentials, $request->boolean('remember'))) {
            /** @var Member $member */
            $member = Auth::guard('member')->user();

            if ($member->banned_at !== null) {
                Auth::guard('member')->logout();

                return back()->withErrors([
                    'email' => 'บัญชีของคุณถูกระงับ: '.($member->ban_reason ?? 'ติดต่อผู้ดูแลระบบ'),
                ])->onlyInput('email');
            }

            // Email verification is informational, not a gate — unverified
            // members can still log in. The verify mail still goes out on
            // register and the click flow still marks email_verified_at,
            // but downstream features that care can branch on $member
            // ->hasVerifiedEmail() themselves.
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

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:members'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        if (config('services.turnstile.secret_key')) {
            $rules['cf-turnstile-response'] = ['required', 'string', new TurnstileToken];
        }

        $validated = $request->validate($rules);

        // Strip Turnstile token before mass-assigning to Member
        unset($validated['cf-turnstile-response']);

        $member = Member::create($validated);

        // Fire-and-forget verification mail — we no longer block login on
        // verification, but the link still works and marks email_verified_at
        // when clicked. Auto-login the new member straight to the home page.
        $member->sendEmailVerificationNotification();
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
