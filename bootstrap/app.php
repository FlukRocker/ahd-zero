<?php

use App\Http\Middleware\AuthenticateMemberOrAdmin;
use App\Http\Middleware\CacheBuildAssets;
use App\Http\Middleware\ForceFullVisitToBladePages;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackPageView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
            // Turn an Inertia visit that resolved to a Blade page into a full
            // page visit (409 + X-Inertia-Location) instead of a modal popup.
            ForceFullVisitToBladePages::class,
        ]);

        // Long-lived cache headers for hashed Vite assets in /build/*
        // (covers `php artisan serve` / php-fpm-served static; nginx fronts
        // typically serve these directly and need a separate config block).
        $middleware->prepend(CacheBuildAssets::class);

        $middleware->alias([
            'auth.memberOrAdmin' => AuthenticateMemberOrAdmin::class,
            'track' => TrackPageView::class,
        ]);

        // Guards other than `web` still fall back to the admin Fortify login by
        // default. Anything under /member belongs to the member guard, so send
        // those guests to the member login instead.
        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('member/*')
                ? route('member.login')
                : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
