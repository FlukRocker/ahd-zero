# Design: Migrate ahd-v2 from Inertia/Vue SSR to Blade Server-Side Rendering

**Date:** 2026-07-20
**Status:** Approved (design), pending implementation plan
**Author:** brainstorming session

## Goal

Convert ahd-v2's frontend from an Inertia.js + Vue 3 SPA (with a separate Node
SSR daemon) to true server-rendered **Blade**, matching the sibling projects
`../lnw-anime` and `../neko-miku`. Eliminate the long-running Node SSR service
(`ahd-ssr` under PM2) and all its operational cost (the 5–9 minute memory-spike
restart loop). After migration, **Node runs at build time only**; production
serves plain server-rendered HTML.

## Why

- The Inertia SSR daemon is a fragile long-running Node process on a shared host
  (memory spikes per render on a 1M-row anime DB, restart loop, port-collision
  management with kurokami).
- The two sibling anime sites already run pure Blade with **zero Node in
  production** and identical feature scope. Unifying on one stack means one
  deploy pattern, one mental model, no SSR service.
- Server-rendered HTML is strictly better for SEO than client-injected
  `@unhead/vue` meta — crawlers see real HTML without executing JS.

## Reference: the actual sibling pattern

Both siblings are **Laravel + Blade + Alpine.js + Motion + Tailwind v4**, no
Inertia, no Vue, no Node runtime.

- `../neko-miku` — Laravel 12, lean (4 pages, no interactive JS framework).
- `../lnw-anime` — Laravel 10, **near-identical feature set to ahd-v2** (same
  controllers: Index/Anime/Category/Search/Directory/Studio/VoiceActor/Comment/
  admin/member/settings). This is the blueprint.

**Correction to the picked option:** lnw-anime has `livewire/livewire` in
composer.json but **uses zero Livewire components**. Its real interactivity
pattern is **Alpine.js `x-data` + `fetch()` calls to JSON controllers**:

- `CommentController` returns `response()->json(...)` for
  index/store/update/destroy/react; the comment thread is an Alpine component
  that `fetch`es these endpoints.
- Admin pages (e.g. `admin/members.blade.php`) use an Alpine component
  (`x-data="memberManager()"`) that `fetch`es admin JSON endpoints.
- Auth/settings are plain Blade `<form method="POST">` posting to Fortify /
  member controllers.

Because the user chose "match lnw-anime," this spec adopts the **Alpine + JSON
API + fetch** pattern (NOT Livewire). This is an even lighter target: no Livewire
dependency, no server round-trip component lifecycle.

## Architecture

Classic Laravel MPA:

```
Request → route → Controller → view('x', $data) → server-rendered Blade HTML
```

- **Blade** renders all HTML server-side. One primary front layout
  (`layouts/app.blade.php`) + an admin layout (`layouts/admin.blade.php`).
- **Alpine.js** (global, ~15KB) for client UI state: search overlay,
  appearance switcher (theme/density/typePairing), header menus, mobile nav,
  dropdowns, dialogs, tabs, comment thread, admin managers.
- **Motion** (already a dependency) exposed through Alpine magics for in-view
  reveal animations — same library used now, driven from Blade instead of Vue.
- **JSON controllers** (`CommentController`, admin endpoints) return JSON for
  Alpine `fetch()`; everything else returns Blade views.
- **Tailwind v4** unchanged. `resources/css/app.css` AHD design tokens (HSL
  vars, `[data-theme]`, `[data-density]`, `[data-type]`) reused nearly as-is —
  the CSS is framework-agnostic. The FOUC-prevention script moves from
  `app.blade.php` into the new Blade layout `<head>`.
- **Node:** build-time only (Vite compiles CSS + Alpine/Motion JS bundle). No
  Node process in production.

## Backend changes (minimal)

Controllers, routes, Eloquent models, Fortify + `member` guard, `SiteSettings`,
`SitemapController`, Redis cache strategy, `AnimeController::buildAnimeDetail`
`safe()` wrapping — **all stay**. The only backend change per controller:

```php
// before
return Inertia::render('Anime', $props);
// after
return view('anime', $data);
```

- `HandleInertiaRequests` shared props (`name`, `appUrl`, `auth.user`,
  `memberAuth.member`, `playerConfig.adsEmbedUrl`, `siteConfig.registrationEnabled`,
  `quote`, `sidebarOpen`) migrate to a **View Composer** / `View::share()` so
  every Blade view gets them.
- Comment + admin JSON endpoints: mostly already return data; expose as JSON
  routes if not already (mirror lnw's `CommentController` JSON API).

## SEO

`useSeo()` (client `@unhead/vue`) → server-rendered Blade `<head>`. Pattern from
lnw `layouts/app.blade.php`: `@php` block computing title/description/og/twitter
from `@yield('title')` / `@section` values each page provides, plus a canonical
link and `<meta name="robots">`. JSON-LD schema builders (`@/lib/schema`:
WebSite, Organization, TVSeries, VideoObject, BreadcrumbList) port to Blade
partials emitting `<script type="application/ld+json">`. This is the SEO win:
real server HTML, no JS execution required.

## Files removed

- `resources/js/ssr.ts`, `bootstrap/ssr/*`
- `ahd-ssr` app entry in `ecosystem.config.cjs` (keep the file for `ahd-queue`
  only, or note queue stays off)
- SSR block in `config/inertia.php`; the whole Inertia config once fully off
- `INERTIA_SSR_*` env vars (`.env`, `.env.example`)
- `build:ssr` / `dev:ssr` scripts in `package.json`; `composer dev:ssr`
- Deps: `@inertiajs/*`, `@unhead/vue`, `reka-ui`, `@laravel/vite-plugin-wayfinder`,
  `ziggy-js` (route helpers → Laravel `route()` in Blade), Vue, vue-tsc, vitest
  (Vue test tooling). Wayfinder-generated `resources/js/{actions,routes,wayfinder}`
  deleted.
- ~28k LOC under `resources/js/{pages,components,composables,layouts}` (Vue).

## Files added

- `resources/views/` Blade tree (~50 views + partials): layouts, front pages,
  admin, auth, member, settings, components (anime-card, header, footer,
  hero-section, section-header, ads/*, seo/*).
- `app/View/Composers/` (or `AppServiceProvider` `View::share`) for shared props.
- `resources/js/app.js` — Alpine bootstrap + Motion magics + theme init
  (mirrors lnw `resources/js/app.js`).

## Interactivity map

| Concern | Mechanism |
|---|---|
| Search overlay | Alpine `x-data` (client filter or `fetch` to search endpoint) |
| Appearance (theme/density/typePairing) | Alpine + `localStorage['ahd.config']`, FOUC script in `<head>` |
| Header menus / mobile nav / dropdowns / dialogs / tabs | Alpine |
| In-view reveal animation | Motion via Alpine magics |
| Comments thread (list/post/edit/delete/react) | Alpine `x-data` + `fetch` → `CommentController` JSON |
| Admin members / comments / site-settings | Alpine manager components + `fetch` → admin JSON endpoints |
| Auth (login/register/reset/2FA), settings | Plain Blade `<form method="POST">` → Fortify / member / settings controllers |
| Turnstile / 2FA challenge | Blade + minimal Alpine |

## Phasing

Each phase is its own spec → plan → build → verify cycle.

1. **Foundation** — `layouts/app.blade.php` + `layouts/admin.blade.php`, port
   AHD tokens/Tailwind config, Alpine + Motion bootstrap (`app.js`), Vite asset
   pipeline for Blade (`@vite`), SEO `<head>` partial + JSON-LD partials, shared
   props via View Composer, strip Inertia bootstrap from `app.blade.php`, remove
   `ahd-ssr` from `ecosystem.config.cjs`, remove SSR config/env. Ship one page
   (e.g. a placeholder home or 404) rendering through the new layout to prove the
   pipeline.
2. **Public read pages** (highest SEO value) — Index, Category, Anime, Episode
   (watch/player), Search, Studio, VoiceActor, directory (Studios/VoiceActors/
   Staff). Includes anime-card, hero, section-header, ads partials, player embed.
3. **Interactive / auth** — member login/register, member settings, admin
   auth/settings, 2FA, comments (Alpine + JSON). Fortify wiring for Blade.
4. **Admin** — dashboard, members, comments, site-settings (Alpine managers +
   JSON endpoints).

After Phase 4: delete remaining Vue/Inertia code, deps, config; final cleanup
commit.

## Testing

- **PHPUnit feature tests** stay and are the primary safety net. Swap
  `assertInertia(...)` assertions → `assertOk()` + `assertViewIs('x')` +
  `assertSee(...)` on rendered HTML. Route coverage, 301 legacy redirects,
  registration toggle, sitemap all keep asserting.
- **Vitest** suite removed with Vue.
- Per-phase: verify live pages render server HTML (view-source shows content,
  not an empty `#app`), SEO meta present in raw HTML, interactive bits work with
  JS.

## Risks & tradeoffs

- **SPA instant transitions → full page reloads.** Siblings accept this (plain
  MPA). Optional later polish: Alpine AJAX / `@vite` prefetch. Not in scope.
- **~28k LOC Vue rewritten** as Blade — the bulk of the work. reka-ui components
  rebuilt as Blade + Alpine (button, dialog, dropdown, sheet, tabs, etc.).
- **Lost client niceties** (`useTilt`, `useReveal`, page transitions) reimplemented
  minimally in Alpine/Motion or dropped.
- **Wayfinder/Ziggy typed routes gone** — Blade uses `route()` directly; no
  client route helpers needed.
- Anime detail pages depend on many aux relations; `safe()` wrapping already
  guards these — Blade must null-check the same way (`@if`/`optional()`).

## Out of scope

- Backend/model refactors beyond swapping the render call and adding JSON routes.
- Redesign — visual output should match the current AHD design (same tokens).
- Migrations against `yu_anime_*` tables (schema is imported; do not touch).
- Turning on `ahd-queue` (stays off; no queued jobs).
