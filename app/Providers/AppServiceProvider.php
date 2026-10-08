<?php

namespace App\Providers;

use App\View\Composers\GlobalComposer;
use App\View\Composers\SidebarComposer;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

use function str_starts_with;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cloudflare terminates TLS and reaches this origin over plain http,
        // and X-Forwarded-Proto is not trusted here, so every generated URL
        // came out http:// — including the Vite preload `Link:` header. CF's
        // Automatic HTTPS Rewrites patch the HTML body but never the response
        // headers, so that header stayed http://, which is not 'self' for an
        // https page and was blocked by our own `style-src 'self'`.
        // Keyed off APP_URL so local http development is untouched.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('*', GlobalComposer::class);
        // Scoped to the one component so admin/Inertia views never run the
        // genre and analytics queries behind it.
        View::composer('components.sidebar', SidebarComposer::class);
    }
}
