<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

use function abort;
use function abort_if;
use function back;
use function event;
use function hash_equals;
use function redirect;
use function sha1;

/**
 * Member email verification flow. Three endpoints:
 *
 *   GET  /member/email/verify              → notice screen ("check your inbox")
 *   GET  /member/email/verify/{id}/{hash}  → signed link from the mail; marks
 *                                            email_verified_at + logs the
 *                                            user in + redirects home
 *   POST /member/email/verification-notification → resend (rate-limited)
 *
 * The signed verify endpoint accepts unauthenticated visits because users
 * typically click the link from an email client where their session may
 * not exist (e.g. opened on phone after registering on desktop).
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        $member = $request->user('member');

        // Guests reach this page only when redirected from a failed login
        // attempt (unverified). Pull the pending email from session so the
        // notice can show "we sent it to x@y" without exposing it via URL.
        $pendingEmail = $request->session()->get('pending_verification_email');

        if ($member instanceof Member && $member->hasVerifiedEmail()) {
            return redirect('/');
        }

        return Inertia::render('Member/VerifyEmail', [
            'pendingEmail' => $member instanceof Member ? $member->email : $pendingEmail,
        ]);
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        // Signed URL is the auth proof here. Don't require a session; users
        // commonly click the email link on a different device.
        abort_if(! $request->hasValidSignature(), HttpResponse::HTTP_FORBIDDEN, 'ลิงก์ไม่ถูกต้องหรือหมดอายุ');

        $member = Member::query()->find($id);
        abort_if($member === null, HttpResponse::HTTP_NOT_FOUND);

        // Hash must match the email at sign time — guards against email
        // changes between sign and click.
        abort_if(
            ! hash_equals(sha1($member->getEmailForVerification()), $hash),
            HttpResponse::HTTP_FORBIDDEN,
            'ลิงก์ยืนยันไม่ตรงกับอีเมลปัจจุบัน'
        );

        if (! $member->hasVerifiedEmail()) {
            $member->markEmailAsVerified();
            event(new Verified($member));
        }

        // Auto-login on successful verify so the user lands logged in.
        Auth::guard('member')->login($member);
        $request->session()->regenerate();
        $request->session()->forget('pending_verification_email');

        return redirect('/')->with('verified', true);
    }

    public function resend(Request $request): RedirectResponse
    {
        $member = $request->user('member');

        if (! $member instanceof Member) {
            // Guest fallback: pull pending email from session, look up the
            // member, send if unverified. Doesn't leak whether email exists.
            $email = (string) $request->session()->get('pending_verification_email', '');
            if ($email === '') {
                return back();
            }
            $member = Member::query()->where('email', $email)->first();
            if ($member === null) {
                return back();
            }
        }

        if ($member->hasVerifiedEmail()) {
            return redirect('/');
        }

        $key = 'verify-email:'.$member->getKey();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'email' => "ส่งอีเมลถี่เกินไป กรุณารอ {$seconds} วินาที",
            ]);
        }
        RateLimiter::hit($key, 60);

        $member->sendEmailVerificationNotification();

        return back()->with('status', 'sent');
    }
}
