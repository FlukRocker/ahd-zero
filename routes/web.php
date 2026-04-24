<?php

use App\Http\Controllers\Admin\MemberManagementController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\AnimeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\VoiceActorController;
use Illuminate\Support\Facades\Route;

// Sitemap & Robots
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages']);
Route::get('/sitemap-anime.xml', [SitemapController::class, 'anime']);
Route::get('/sitemap-episodes-{page}.xml', [SitemapController::class, 'episodes'])->whereNumber('page');
Route::get('/robots.txt', [SitemapController::class, 'robots']);

// Public anime site
Route::get('/', [IndexController::class, 'renderIndex'])->name('home');
Route::get('/category/{type}', [CategoryController::class, 'index'])->name('category');
Route::get('/anime/{id}', [AnimeController::class, 'show'])->name('anime.show');
Route::get('/anime/{id}/episode/{listId}', [AnimeController::class, 'episode'])->name('anime.episode');

// Directories
Route::get('/studios', [DirectoryController::class, 'studios'])->name('studios.index');
Route::get('/voice-actors', [DirectoryController::class, 'voiceActors'])->name('voice-actors.index');
Route::get('/staff', [DirectoryController::class, 'staff'])->name('staff.index');
Route::get('/studio/{id}', [StudioController::class, 'show'])->name('studio.show');
Route::get('/voice-actor/{id}', [VoiceActorController::class, 'show'])->name('voice-actor.show');

// Search results page
Route::get('/search/results', [SearchController::class, 'index'])->name('search.results');

// ─────────────────────────────────────────────────────────────────────────
// Legacy v1 → v2 301 redirects (anime-hdzero.com path scheme).
// /watch/{id}      → /anime/{cat_id}/episode/{id}  (resolved via Episode lookup)
// /cat/{id}        → /category/{id}
// /catagory/{id}   → /anime/{id}                    (note v1 typo)
// /search?search=  → /search/results?q=
// ─────────────────────────────────────────────────────────────────────────
Route::get('/watch/{listId}', [AnimeController::class, 'legacyWatch'])
    ->whereNumber('listId')
    ->name('legacy.watch');

Route::get('/cat/{id}', function (string $id) {
    return redirect("/category/{$id}", 301);
})->whereNumber('id')->name('legacy.cat');

Route::get('/catagory/{id}', function (string $id) {
    return redirect("/anime/{$id}", 301);
})->whereNumber('id')->name('legacy.catagory');

// Legacy search used `?search=foo`; new search/results uses `?q=foo`. Only
// redirect when the legacy param is present so the JSON `/search` endpoint
// keeps working as the live-suggest API.
Route::get('/search', function (\Illuminate\Http\Request $request) {
    if ($request->filled('search') && ! $request->filled('q')) {
        return redirect()->away(
            url('/search/results?q='.urlencode((string) $request->input('search'))),
            301,
        );
    }

    return app(SearchController::class)->search($request);
})->name('search');

// Authenticated admin area
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('dashboard/members', [MemberManagementController::class, 'index'])->name('admin.members');
    Route::post('dashboard/members/{id}/ban', [MemberManagementController::class, 'ban'])->name('admin.members.ban');
    Route::post('dashboard/members/{id}/unban', [MemberManagementController::class, 'unban'])->name('admin.members.unban');
    Route::delete('dashboard/members/{id}', [MemberManagementController::class, 'destroy'])->name('admin.members.destroy');

    Route::get('dashboard/site-settings', [SiteSettingsController::class, 'index'])->name('admin.settings');
    Route::post('dashboard/site-settings/maintenance', [SiteSettingsController::class, 'toggleMaintenance'])->name('admin.settings.maintenance');
    Route::post('dashboard/site-settings/registration', [SiteSettingsController::class, 'toggleRegistration'])->name('admin.settings.registration');
    Route::post('dashboard/site-settings/clear-cache', [SiteSettingsController::class, 'clearCache'])->name('admin.settings.clearCache');
});

require __DIR__.'/member.php';
require __DIR__.'/settings.php';
