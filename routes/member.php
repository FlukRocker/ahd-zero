<?php

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
