<?php

use App\Http\Controllers\Member\EmailVerificationController;
use App\Http\Controllers\Member\MemberAuthController;
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
