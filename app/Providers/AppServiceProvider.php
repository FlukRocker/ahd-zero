<?php

namespace App\Providers;

use App\View\Composers\GlobalComposer;
use App\View\Composers\SidebarComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('*', GlobalComposer::class);
        // Scoped to the one component so admin/Inertia views never run the
        // genre and analytics queries behind it.
        View::composer('components.sidebar', SidebarComposer::class);
    }
}
