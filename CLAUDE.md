# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Laravel 12 + Inertia.js 2 + Vue 3 (TypeScript) SPA. Vite + Tailwind CSS v4. AHD design tokens via HSL CSS vars (light/dark/density/typePairing). Motion One for animation. `@unhead/vue` for SEO heads. Laravel Fortify for `web` (admin) auth + 2FA; separate `member` guard for end-user accounts. Wayfinder for typed route helpers. Ziggy for client-side route URLs. PHP 8.4. Default DB MariaDB (anime tables `yu_anime_catagory`/`yu_anime_list` are imported, shared with kurokami).

## Commands

Dev (server + queue + pail + vite concurrently): `composer dev`. SSR dev: `composer dev:ssr`.

Frontend only: `pnpm dev` · Build: `pnpm build` · SSR: `pnpm build:ssr` · Tests: `pnpm test` (Vitest)

Lint/format: `pnpm lint` (ESLint --fix), `pnpm format` (Prettier), `./vendor/bin/pint` (PHP), `vendor/bin/phpstan analyse` (level 4)

Backend tests: `vendor/bin/phpunit` (in-memory sqlite). Single: `vendor/bin/phpunit --filter=NameTest`

## Server (production)

The shared host uses non-default PHP/Composer binaries: **`php84`** and **`composer84`** instead of `php`/`composer`. When deploying or running artisan on the server, use those names. PM2 ecosystem (`ecosystem.config.cjs`) reads `PHP_BIN` env to pick the interpreter:

```bash
pnpm install --prod=false
pnpm build:ssr
composer84 install --no-dev --optimize-autoloader
PHP_BIN=php84 pm2 start ecosystem.config.cjs --env production
pm2 save && pm2 startup
```

PM2 supervises two processes:
- `ahd-ssr` — `node bootstrap/ssr/ssr.js` (Inertia SSR daemon)
- `ahd-queue` — `php84 artisan queue:work` w/ recycling

## Architecture

### Request flow
- `routes/web.php` — public site (`/`, `/category/{type}`, `/anime/{id}`, `/anime/{id}/episode/{listId}`, `/search/results`, `/studios`, `/voice-actors`, `/staff`, `/studio/{id}`, `/voice-actor/{id}`) + sitemap/robots + legacy 301s (`/cat/{id}`, `/catagory/{id}`, `/watch/{id}`, `/search?search=`)
- `routes/member.php` — `/member/{login,register,logout}` under `member` guard
- `routes/settings.php` — admin profile/password/appearance/2FA
- Wayfinder (`@laravel/vite-plugin-wayfinder`) auto-generates typed route helpers into `resources/js/{actions,routes,wayfinder}` at build — never hand-edit; change Laravel routes instead.

### Frontend layout split
- `layouts/FrontLayout.vue` — public site (SiteHeader + Footer + SearchOverlay + page transition). Used by Index/Anime/Episode/Category/Search/Studio/VoiceActor/directory/Member.
- `layouts/AppLayout.vue` — admin dashboard chrome. Uses legacy shadcn-style vars bridged from AHD tokens in `app.css`.

Alias: `@/*` → `resources/js/*`.

### Eloquent models
- `App\Models\Anime` → table `yu_anime_catagory`, PK `cat_id`, soft-deletes, `$timestamps=false`. Rich relations: `episodeList`, `genres` (Tag pivot), `studios`/`producers`/`licensors` (anime_studio with role pivot), `characters`, `staff`, `relations`. Image variant accessors via `ImageVariantService` (Chevereto `.md`/`.th` suffix).
- `App\Models\Episode` → table `yu_anime_list`, PK `list_id`, FK `catagory_id` (matches DB column spelling — keep).
- `App\Models\Member` — separate auth from `User`, UUID PK, `members` table.
- All anime aux models (`Studio`, `VoiceActor`, `Staff`, `Character`, `Series`, `Tag`, `FeaturedAnime`, `AnimeRelation`) live in shared DB. **`AnimeController::buildAnimeDetail` wraps every relation in a `safe()` try/catch** so a missing pivot table doesn't 500 the page — empty collection instead.

Don't write migrations against `yu_anime_*` tables in prod — schema is imported. The local-only shim migrations (`2024_01_01_000001_*`, `2024_01_01_000002_*`) are guarded with `Schema::hasTable()` so they no-op against the real DB and only run for in-memory sqlite during tests.

### Inertia shared props
`HandleInertiaRequests` injects: `name`, `appUrl`, `auth.user`, `memberAuth.member`, `playerConfig.adsEmbedUrl`, `siteConfig.registrationEnabled`, `quote`, `sidebarOpen`. Front pages read these via `usePage<{...}>()`.

### Design tokens
HSL triplets on `:root` in `resources/css/app.css`: `--bg`, `--fg`, `--accent`, `--chip`, etc. Themes via `[data-theme='dark']` (and legacy `.dark` class). `[data-type='alt']` swaps to Fraunces. `[data-density='compact']` shrinks rail widths. Accent + density + typePairing managed via `useAppearance` composable, persisted to `localStorage['ahd.config']`. Early FOUC-prevention script in `app.blade.php` applies attrs before paint.

### SEO
Every public page calls `useSeo(() => ({ title, description, image, type, robots, schema }))` from `@/composables/useSeo`. Emits canonical link + OG/Twitter tags + JSON-LD via `@unhead/vue`. Schema builders in `@/lib/schema` (WebSite, Organization, TVSeries, VideoObject, BreadcrumbList).

Sitemaps streamed via `SitemapController` (`/sitemap.xml` index, paginated episodes 45k/page, hour-cached). Robots.txt allows AI crawlers explicitly.

### Site/registration toggle
Two-layer flag in `App\Support\SiteSettings::registrationEnabled()`:
1. `config('site.registration_enabled')` — env-driven hard cap (`REGISTRATION_ENABLED=false` to lock OFF)
2. `storage/app/site_settings.json` — admin Site Settings UI override (cannot exceed env cap)

### Cache (shared Redis)
Cache and sessions share a Redis instance with kurokami. `CACHE_PREFIX` keeps keys distinct (set per app via env). `SiteSettingsController::clearCache` uses `SCAN`+`DEL` matching the local prefix only — **never `FLUSHDB`** — to avoid wiping kurokami's cache.
