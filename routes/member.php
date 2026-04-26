<?php

use App\Http\Controllers\Member\EmailVerificationController;
use App\Http\Controllers\Member\MemberAuthController;
use App\Http\Controllers\Member\MemberSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:member')->group(function (): void {
    Route::get('/member/login', [MemberAuthController::class, 'showLogin'])->name('member.login');
    Route::post('/member/login', [MemberAuthController::class, 'login']);
    Route::get('/member/register', [MemberAuthController::class, 'showRegister'])->name('member.register');
    Route::post('/member/register', [MemberAuthController::class, 'register']);
});

Route::middleware('auth.memberOrAdmin')->group(function (): void {
    Route::post('/member/logout', [MemberAuthController::class, 'logout'])->name('member.logout');
});

// Member-only settings — `auth:member` keeps admins out (admins use
// /settings/profile via Fortify under the web guard).
Route::middleware('auth:member')->group(function (): void {
    Route::redirect('/member/settings', '/member/settings/profile');

    Route::get('/member/settings/profile', [MemberSettingsController::class, 'profile'])
        ->name('member.settings.profile');
    Route::patch('/member/settings/profile', [MemberSettingsController::class, 'updateProfile'])
        ->name('member.settings.profile.update');

    Route::get('/member/settings/password', [MemberSettingsController::class, 'password'])
        ->name('member.settings.password');
    Route::put('/member/settings/password', [MemberSettingsController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('member.settings.password.update');

    Route::delete('/member/settings/profile', [MemberSettingsController::class, 'destroy'])
        ->name('member.settings.destroy');
});

// Email verification — notice + resend reachable by guests too because login
// kicks unverified members back to /member/email/verify before authenticating.
// The signed verify link must be public (clicked from inbox on any device).
Route::get('/member/email/verify', [EmailVerificationController::class, 'notice'])
    ->name('member.verification.notice');

Route::get('/member/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('member.verification.verify');

Route::post('/member/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:6,1')
    ->name('member.verification.send');
