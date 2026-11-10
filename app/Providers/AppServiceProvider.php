<?php

namespace App\Providers;

use App\Listeners\LinkAdminToMember;
use App\View\Composers\GlobalComposer;
use App\View\Composers\SidebarComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(
            \App\Contracts\ChatHistory::class,
            \App\Support\RedisChatHistory::class,
        );
        $this->app->bind(\App\Contracts\ReportTargetResolver::class, \App\Support\EloquentReportTargets::class);
        $this->app->bind(\App\Contracts\AdminFeed::class, \App\Services\AdminFeeds::class);
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

        // Per member, not per IP: the member guard is not the default guard,
        // so the stock `throttle:N,M` would key on IP and punish shared NATs.
        RateLimiter::for('chat-send', fn (Request $request): Limit => Limit::perMinute(20)
            ->by('chat-send:'.($request->user('member')?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('chat-presence', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('chat-presence:'.($request->user('member')?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('reports:'.($request->user('member')?->getAuthIdentifier() ?? $request->ip())));

        // Admin login at /login also signs the linked Member in.
        Event::listen(Login::class, LinkAdminToMember::class);

        View::composer('*', GlobalComposer::class);
        // Scoped to the one component so admin/Inertia views never run the
        // genre and analytics queries behind it.
        View::composer('components.sidebar', SidebarComposer::class);
    }
}
