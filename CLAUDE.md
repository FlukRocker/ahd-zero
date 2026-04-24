# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Laravel 12 + Inertia.js 2 + Vue 3 (TypeScript) SPA. Vite build, Tailwind CSS v4, PrimeVue (Aura preset, zinc primary, `.dark` selector), reka-ui, Lucide icons. Laravel Fortify for auth (incl. 2FA), Laravel Wayfinder for typed route helpers, Ziggy for client-side route URLs. Default DB is SQLite.

## Commands

Dev (runs server + queue listener + `pail` logs + vite concurrently):
```
composer dev
```
SSR dev: `composer dev:ssr`

Frontend only: `npm run dev` · Build: `npm run build` · SSR build: `npm run build:ssr`

Lint/format: `npm run lint` (ESLint --fix) · `npm run format` (Prettier) · `./vendor/bin/pint` (PHP)

Tests (PHPUnit, in-memory sqlite per `phpunit.xml`):
```
composer test                       # clears config then runs suite
php artisan test                    # run all
php artisan test --filter=Name      # single test/method
php artisan test tests/Feature/DashboardTest.php
```

First-time setup: `composer setup` (install, `.env`, key:generate, migrate, npm install, build).

## Architecture

### Request flow
- `routes/web.php` — public anime site routes (`/`, `/cat/{id}`, `/catagory/{id}`, `/watch/{id}`, `/search`) + `/dashboard` (auth).
- `routes/settings.php` — authed profile/password/appearance/2FA routes, loaded from `web.php`.
- Controllers render Inertia pages: `Inertia::render('PageName', [...])` → resolves `resources/js/pages/PageName.vue` via glob in `resources/js/app.ts`.
- Wayfinder (`@laravel/vite-plugin-wayfinder`) auto-generates typed route/action helpers into `resources/js/{actions,routes,wayfinder}` at build — do not hand-edit these; change the Laravel routes/controllers instead.

### Frontend layout split
Two distinct layouts — pick correctly when adding pages:
- `layouts/FrontLayout.vue` — public anime site chrome (`FrontNavbar`, sidebars, ads, footer). Used by `Index.vue`, `Anime.vue`, `Watch.vue`, `Search.vue`. Expects a `genres` prop from the controller.
- `layouts/AppLayout.vue` — authenticated dashboard chrome. Used by `Dashboard.vue`, `settings/*`, `auth/*` (via `AuthLayout.vue`).

Alias: `@/*` → `resources/js/*` (tsconfig `paths`).

### Legacy DB models
The anime data lives in pre-existing non-Laravel-conventional tables. Eloquent models override defaults:
- `App\Models\Category` → table `yu_anime_catagory`, PK `cat_id`. Ordering field is `cat_update`; grouping field is `cat_type`.
- `App\Models\Watch` → table `yu_anime_list`, PK `list_id`, FK `catagory_id` (note the spelling — it matches the column, keep it).
- `App\Models\Genre` → conventional `genres` table.

No migrations define these tables — they are imported externally. Don't write migrations that would conflict.

### Controllers — known duplication
`CatagoryController::animeListIndex` and `IndexController::animeListIndex` are duplicates (same query, same render). The routing currently dispatches `/` to `IndexController` and the other three anime routes to `CatagoryController`. When changing the index listing behavior, update both or consolidate.

Spelling note: `CatagoryController` (typo) is intentional and matches the DB column `catagory_id`. The Eloquent model class is spelled correctly as `Category`.

### Inertia page props shape
Paginated listings pass a Laravel paginator to Inertia, so Vue pages destructure `categories.data` and `categories.links` (see `pages/Index.vue`, `Pagination.vue`). Follow this pattern for new listing pages.

### Theme
PrimeVue `Aura` preset is customized in `resources/js/app.ts` (zinc-based primary, both light/dark scheme variants). Dark mode is toggled via the `.dark` class on root. Use `useAppearance` composable for user preference.
