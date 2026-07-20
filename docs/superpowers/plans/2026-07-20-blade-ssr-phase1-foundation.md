# Blade SSR Migration — Phase 1: Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Blade server-rendering foundation (layout, Alpine+Motion asset pipeline, shared view data, SEO head + JSON-LD partials) alongside the existing Inertia app, and render the error pages as the first real server-rendered Blade pages to prove the pipeline end-to-end.

**Architecture:** Additive / non-breaking. Inertia (`app.blade.php` root + Vue pages) keeps running untouched. A new Blade asset entry (`resources/js/blade.js` — Alpine + Motion) and a new front layout (`resources/views/layouts/app.blade.php`) are added in parallel. A `View::composer('*')` supplies the same shared data the Inertia middleware provides. Phase 1 ships the Laravel error pages (`resources/views/errors/*`) through the new layout. **The Node SSR daemon and Inertia removal are NOT touched in this phase** — that is the final cleanup phase after all pages are migrated, so unmigrated Inertia pages keep their SSR.

**Tech Stack:** Laravel 12, Blade, Alpine.js 3, Motion (`motion` v12, already installed), Tailwind CSS v4 (`@tailwindcss/vite`), Vite 7.

## Global Constraints

- Laravel 12 + PHP 8.4. Local dev binaries `php`/`composer`; **production server uses `php84`/`composer84`** — never assume plain `php` on the server.
- Package manager is **pnpm** (`pnpm add`, `pnpm build`). Not npm/yarn.
- **Additive only this phase:** do NOT remove or edit `resources/js/app.ts`, `resources/js/ssr.ts`, `bootstrap/ssr/*`, `ecosystem.config.cjs`, `config/inertia.php`, or `HandleInertiaRequests`. Inertia must keep working.
- Tailwind v4 auto-scans compiled Blade via existing `@source '../../storage/framework/views/*.php'` in `resources/css/app.css` — new Blade classes are picked up after first render; no config change needed.
- CSP (`app/Http/Middleware/SecurityHeaders.php`) already allows `script-src 'unsafe-eval'` — standard Alpine build works, do NOT switch to the Alpine CSP build.
- Reuse existing AHD design tokens in `resources/css/app.css` as-is (HSL vars, `[data-theme]`, `[data-density]`, `[data-type]`). Do not redefine tokens.
- Never write migrations against `yu_anime_*` tables. Redis cache clears must match the local `CACHE_PREFIX` only — never `FLUSHDB` (not relevant this phase but holds).
- Backend tests run on in-memory sqlite via `vendor/bin/phpunit`.

## File Structure

- Create `resources/js/blade.js` — Alpine + Motion bootstrap, `$reveal` in-view magic, appearance init. The single JS entry for all Blade pages.
- Modify `vite.config.ts` — add `resources/js/blade.js` to the Laravel plugin `input` array (keep `app.ts` + `ssr` + Vue + Wayfinder).
- Create `app/View/Composers/GlobalComposer.php` — supplies shared data (site name, appUrl, member/admin auth, ads navbar, player config, site config, quote) to every Blade view.
- Modify `app/Providers/AppServiceProvider.php` — register `View::composer('*', GlobalComposer::class)`.
- Create `resources/views/partials/seo.blade.php` — `<title>` + description + canonical + OG/Twitter meta from yielded sections, with site defaults.
- Create `resources/views/partials/schema/website.blade.php` — WebSite + Organization JSON-LD (`<script type="application/ld+json">`).
- Create `resources/views/layouts/app.blade.php` — front Blade document: `<head>` (FOUC script, theme-color, font preload, GA4, favicons, SEO partial, `@vite(['resources/js/blade.js'])`, `@stack('head')`), `<body>` with header/footer partials + `@yield('content')` + `@stack('scripts')`.
- Create `resources/views/partials/header.blade.php` — minimal site header stub (brand link). Full header is Phase 2.
- Create `resources/views/partials/footer.blade.php` — minimal site footer stub. Full footer is Phase 2.
- Create `resources/views/errors/layout.blade.php` — shared error page shell extending `layouts.app`.
- Create `resources/views/errors/404.blade.php`, `resources/views/errors/500.blade.php`, `resources/views/errors/503.blade.php` — Laravel auto-resolves these.
- Create `tests/Feature/BladeFoundationTest.php` — asserts error pages render server-side HTML (real content in raw response, not an Inertia empty shell) and that shared data + SEO meta are present.

---

### Task 1: Blade asset entry (Alpine + Motion) and Vite input

**Files:**
- Create: `resources/js/blade.js`
- Modify: `vite.config.ts:9-17` (the `laravel({ input: [...] })` call)
- Add dependency: `alpinejs`

**Interfaces:**
- Consumes: `resources/css/app.css` (existing tokens), `motion` package (installed).
- Produces: a Vite entry `resources/js/blade.js` referenced by `@vite(['resources/js/blade.js'])` in Task 4; a global `window.Alpine`; an Alpine magic `$reveal(el, opts)` usable from Blade `x-init`.

- [ ] **Step 1: Install Alpine**

Run:
```bash
pnpm add alpinejs
```
Expected: `alpinejs` added to `dependencies` in `package.json`.

- [ ] **Step 2: Create the Blade JS entry**

Create `resources/js/blade.js`:
```js
import '../css/app.css';

import Alpine from 'alpinejs';
import { animate, inView } from 'motion';

// Expose for inline Blade usage and debugging.
window.Alpine = Alpine;
window.motionAnimate = animate;
window.motionInView = inView;

// In-view reveal: use in Blade as x-init="$reveal($el)" or
// x-init="$reveal($el, { y: 40, delay: 0.1 })".
// Mirrors the Motion-driven reveals used across ../lnw-anime.
Alpine.magic('reveal', () => (el, opts = {}) => {
    const { y = 24, duration = 0.5, delay = 0 } = opts;
    inView(el, () => {
        animate(
            el,
            {
                opacity: [0, 1],
                transform: [`translateY(${y}px)`, 'translateY(0)'],
            },
            { duration, delay, easing: [0.25, 0.46, 0.45, 0.94] },
        );
    });
});

// Appearance: the <head> FOUC script (in layouts/app.blade.php) already
// applies data-theme / data-density / data-type before paint from
// localStorage. This store exposes a runtime theme toggle for header/UI
// controls added in later phases.
Alpine.store('appearance', {
    get theme() {
        return document.documentElement.getAttribute('data-theme') || 'system';
    },
    setTheme(theme) {
        const resolved =
            theme === 'system'
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light'
                : theme;
        document.documentElement.setAttribute('data-theme', resolved);
        document.documentElement.classList.toggle('dark', resolved === 'dark');
        try {
            localStorage.setItem('appearance', theme);
        } catch {
            /* ignore — private mode / storage disabled */
        }
    },
    toggle() {
        const isDark = document.documentElement.classList.contains('dark');
        this.setTheme(isDark ? 'light' : 'dark');
    },
});

Alpine.start();
```

- [ ] **Step 3: Add the entry to Vite input**

In `vite.config.ts`, change the Laravel plugin input from:
```ts
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
```
to:
```ts
        laravel({
            input: ['resources/js/app.ts', 'resources/js/blade.js'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
```
(Leave `ssr`, `tailwindcss()`, `wayfinder(...)`, and `vue(...)` unchanged.)

- [ ] **Step 4: Build and verify the manifest contains the new entry**

Run:
```bash
pnpm build
```
Expected: build succeeds; then confirm the entry is in the manifest:
```bash
grep -c "resources/js/blade.js" public/build/manifest.json
```
Expected: output `1` (or greater).

- [ ] **Step 5: Commit**

```bash
git add package.json pnpm-lock.yaml resources/js/blade.js vite.config.ts
git commit -m "feat(blade): add Alpine+Motion asset entry for Blade SSR

Adds resources/js/blade.js (Alpine 3 + Motion \$reveal magic + theme
store) and registers it as a second Vite input alongside the Inertia
app.ts entry. Non-breaking: Inertia pipeline untouched.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 2: Shared view data via View Composer

**Files:**
- Create: `app/View/Composers/GlobalComposer.php`
- Modify: `app/Providers/AppServiceProvider.php` (the `boot()` method)
- Test: `tests/Feature/BladeFoundationTest.php` (created in Task 5; this task adds no test of its own — its output is asserted there)

**Interfaces:**
- Consumes: `App\Support\SiteSettings::registrationEnabled()`, `App\Support\AdsNavbar::all()`, `config('services.*')`, `Illuminate\Foundation\Inspiring` — all already used by `HandleInertiaRequests`.
- Produces: Blade view variables available in every view: `$siteName` (string), `$appUrl` (string), `$authUser` (?Authenticatable), `$memberAuth` (?array), `$playerConfig` (array), `$siteConfig` (array), `$navbarAds` (array), `$quote` (array{message,author}).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/BladeFoundationTest.php` (file created fully in Task 5; if running this task first, create the class skeleton shown in Task 5 Step 1). Add this method:
```php
    public function test_global_composer_shares_site_data_with_blade_views(): void
    {
        $this->withoutVite();

        // Render an inline Blade string through the view factory so the
        // '*' composer runs against it.
        $rendered = view('errors.404')->render();

        $this->assertStringContainsString(config('app.name'), $rendered);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
vendor/bin/phpunit --filter=test_global_composer_shares_site_data_with_blade_views
```
Expected: FAIL — either `View [errors.404] not found` (views not built yet) or missing shared data. (This test passes fully once Tasks 2–5 are done; it is the cross-cutting assertion.)

- [ ] **Step 3: Create the composer**

Create `app/View/Composers/GlobalComposer.php`:
```php
<?php

namespace App\View\Composers;

use App\Support\AdsNavbar;
use App\Support\SiteSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalComposer
{
    public function __construct(private Request $request) {}

    public function compose(View $view): void
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $member = $this->request->user('member');

        $view->with([
            'siteName' => config('app.name'),
            'appUrl' => config('app.url'),
            'authUser' => $this->request->user(),
            'memberAuth' => $member ? [
                'id' => $member->uuid,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar,
            ] : null,
            'playerConfig' => [
                'adsEmbedUrl' => config('services.akuma_player.ads_embed_url'),
            ],
            'siteConfig' => [
                'registrationEnabled' => SiteSettings::registrationEnabled(),
                'turnstileSiteKey' => config('services.turnstile.site_key'),
            ],
            'navbarAds' => AdsNavbar::all(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
        ]);
    }
}
```

- [ ] **Step 4: Register the composer**

In `app/Providers/AppServiceProvider.php`, add the import at the top:
```php
use App\View\Composers\GlobalComposer;
use Illuminate\Support\Facades\View;
```
and inside `boot()` add:
```php
        View::composer('*', GlobalComposer::class);
```

- [ ] **Step 5: Verify (test still expected to fail until views exist)**

Run:
```bash
vendor/bin/phpunit --filter=test_global_composer_shares_site_data_with_blade_views
```
Expected: still FAIL with `View [errors.404] not found` (views arrive in Task 5). Composer wiring is correct; proceed.

- [ ] **Step 6: Commit**

```bash
git add app/View/Composers/GlobalComposer.php app/Providers/AppServiceProvider.php
git commit -m "feat(blade): share global view data via View::composer

Ports HandleInertiaRequests shared props (site name, appUrl, member auth,
player/site config, navbar ads, quote) to a '*' Blade view composer so
Blade pages get the same data without Inertia.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 3: SEO head partial and JSON-LD schema partial

**Files:**
- Create: `resources/views/partials/seo.blade.php`
- Create: `resources/views/partials/schema/website.blade.php`

**Interfaces:**
- Consumes: `$siteName` from Task 2; per-page `@section('title')`, `@section('description')`, `@section('og_image')`, `@section('og_type')`, `@section('robots')` (optional — defaults applied when absent).
- Produces: two includable partials: `@include('partials.seo')` (meta tags) and `@include('partials.schema.website')` (JSON-LD), both consumed by the layout in Task 4.

- [ ] **Step 1: Create the SEO partial**

Create `resources/views/partials/seo.blade.php`:
```blade
@php
    $defaultTitle = config('app.name', 'Anime HD Zero');
    $defaultDescription = 'ดูอนิเมะออนไลน์ฟรี ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero';

    $seoTitle = trim($__env->yieldContent('title', $defaultTitle));
    $seoDescription = trim($__env->yieldContent('description', $defaultDescription));
    $seoImage = trim($__env->yieldContent('og_image', asset('og-default.jpg')));
    $seoType = trim($__env->yieldContent('og_type', 'website'));
    $seoRobots = trim($__env->yieldContent('robots', 'index,follow,max-image-preview:large,max-snippet:-1'));
    $seoUrl = url()->current();

    // <title> tag capped at ~60 chars for SERP; og/twitter keep full string.
    $titleTag = mb_strlen($seoTitle) > 60 ? mb_substr($seoTitle, 0, 59) . '…' : $seoTitle;
@endphp

<title>{{ $titleTag }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="canonical" href="{{ $seoUrl }}">

<meta property="og:type" content="{{ $seoType }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image" content="{{ $seoImage }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">
```

- [ ] **Step 2: Create the WebSite/Organization JSON-LD partial**

Create `resources/views/partials/schema/website.blade.php`:
```blade
@php
    $ldWebsite = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => config('app.name'),
        'url' => config('app.url'),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => rtrim(config('app.url'), '/') . '/search/results?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
    $ldOrg = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => config('app.name'),
        'url' => config('app.url'),
        'logo' => rtrim(config('app.url'), '/') . '/favicon.png',
    ];
@endphp
<script type="application/ld+json">{!! json_encode($ldWebsite, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($ldOrg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
```

- [ ] **Step 3: Verify partials compile (rendered via layout in Task 4/5)**

Run:
```bash
php artisan view:clear && php -l resources/views/partials/seo.blade.php >/dev/null 2>&1 || echo "note: .blade.php is not plain PHP; compilation verified when rendered in Task 5"
```
Expected: no fatal output. (Blade compiles at render time; Task 5's feature test is the real check.)

- [ ] **Step 4: Commit**

```bash
git add resources/views/partials/seo.blade.php resources/views/partials/schema/website.blade.php
git commit -m "feat(blade): add server-rendered SEO meta + JSON-LD partials

Ports useSeo()/@unhead client meta to Blade partials.seo (title/desc/
canonical/OG/Twitter from yielded sections) and partials.schema.website
(WebSite + Organization JSON-LD). True server HTML for crawlers.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 4: Front Blade layout + header/footer stubs

**Files:**
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/partials/header.blade.php`
- Create: `resources/views/partials/footer.blade.php`

**Interfaces:**
- Consumes: `@vite(['resources/js/blade.js'])` (Task 1), `@include('partials.seo')` + `@include('partials.schema.website')` (Task 3), `$appearance` (shared by existing `HandleAppearance` middleware), `$siteName` (Task 2).
- Produces: a base layout `layouts.app` that child views extend via `@extends('layouts.app')` + `@section('content')`; head/script injection points `@stack('head')` / `@stack('scripts')`.

- [ ] **Step 1: Create the header stub**

Create `resources/views/partials/header.blade.php`:
```blade
<header class="border-b border-[hsl(var(--border-ahd))] bg-[hsl(var(--bg-elev))]">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <a href="{{ url('/') }}" class="text-lg font-semibold text-[hsl(var(--fg))]">
            {{ $siteName }}
        </a>
        <button
            type="button"
            x-data
            @click="$store.appearance.toggle()"
            class="rounded-md px-3 py-1 text-sm text-[hsl(var(--fg-muted))] hover:text-[hsl(var(--fg))]"
            aria-label="Toggle theme"
        >
            ◐
        </button>
    </div>
</header>
```

- [ ] **Step 2: Create the footer stub**

Create `resources/views/partials/footer.blade.php`:
```blade
<footer class="mt-16 border-t border-[hsl(var(--border-ahd))] bg-[hsl(var(--bg-elev))]">
    <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-[hsl(var(--fg-faint))]">
        © {{ date('Y') }} {{ $siteName }}
    </div>
</footer>
```

- [ ] **Step 3: Create the layout**

Create `resources/views/layouts/app.blade.php` (FOUC script, font preload, and GA4 block ported verbatim from `resources/views/app.blade.php`):
```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ $appearance ?? 'system' }}"
      @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#faf7f0" media="(prefers-color-scheme: light)">
    <meta name="color-scheme" content="light dark">
    <meta name="format-detection" content="telephone=no">

    {{-- Early theme resolver — prevents FOUC. --}}
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('appearance');
                var appearance = saved || '{{ $appearance ?? 'system' }}' || 'system';
                var resolved = appearance;
                if (appearance === 'system') {
                    resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.classList.toggle('dark', resolved === 'dark');
                document.documentElement.setAttribute('data-theme', resolved);

                var cfgRaw = localStorage.getItem('ahd.config');
                if (cfgRaw) {
                    var cfg = JSON.parse(cfgRaw);
                    if (cfg.density) document.documentElement.setAttribute('data-density', cfg.density);
                    if (cfg.typePairing) document.documentElement.setAttribute('data-type', cfg.typePairing === 'fraunces' ? 'alt' : 'default');
                }
            } catch (e) {}
        })();
    </script>

    <style>
        html { background: hsl(40 33% 97%); }
        html.dark, html[data-theme='dark'] { background: hsl(0 0% 4%); }
    </style>

    @include('partials.seo')

    <meta name="application-name" content="{{ config('app.name', 'Anime HD Zero') }}">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Anime HD Zero') }}">

    <link rel="preconnect" href="https://img-cdn-proxy.shirokami.me" crossorigin>
    <link rel="dns-prefetch" href="https://img.shirokami.me">
    <link rel="dns-prefetch" href="https://img-cdn.shirokami.me">
    <link rel="dns-prefetch" href="https://akuma-player.xyz">

    {{-- Critical font preload — hashes change per build; resolved paths cached 1 day. --}}
    @php
        $preloadFonts = \Illuminate\Support\Facades\Cache::remember(
            'font-preload-paths.v1',
            now()->addDay(),
            function () {
                $dir = public_path('build/assets');
                if (!is_dir($dir)) return [];
                $needed = ['instrument-serif-latin-400-normal', 'geist-sans-latin-400-normal'];
                $found = [];
                foreach (glob($dir.'/*.woff2') ?: [] as $path) {
                    $name = basename($path);
                    foreach ($needed as $needle) {
                        if (str_starts_with($name, $needle.'-')) {
                            $found[$needle] = '/build/assets/'.$name;
                            break;
                        }
                    }
                }
                return array_values($found);
            },
        );
    @endphp
    @foreach ($preloadFonts as $fontHref)
        <link rel="preload" href="{{ $fontHref }}" as="font" type="font/woff2" crossorigin>
    @endforeach

    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="shortcut icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" type="image/png" href="/apple-touch-icon.png">

    @if ($gaId = config('services.google_analytics.measurement_id'))
        {{-- GA4 deferred — loads only on real user interaction. --}}
        <script>
            (function () {
                var id = @json($gaId);
                window.dataLayer = window.dataLayer || [];
                function gtag(){ dataLayer.push(arguments); }
                window.gtag = gtag;
                gtag('js', new Date());
                gtag('config', id, { send_page_view: false });

                var loaded = false;
                function load() {
                    if (loaded) return;
                    loaded = true;
                    var s = document.createElement('script');
                    s.async = true;
                    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
                    document.head.appendChild(s);
                    gtag('event', 'page_view', {
                        page_location: location.href,
                        page_path: location.pathname + location.search,
                        page_title: document.title,
                    });
                }

                ['pointerdown', 'touchstart', 'keydown', 'scroll', 'mousemove'].forEach(function (ev) {
                    addEventListener(ev, load, { once: true, passive: true, capture: true });
                });
                setTimeout(load, 30000);
                addEventListener('pagehide', load, { once: true, capture: true });
            })();
        </script>
    @endif

    @include('partials.schema.website')

    @stack('head')

    @vite(['resources/js/blade.js'])
</head>

<body class="min-h-screen bg-[hsl(var(--bg))] font-sans text-[hsl(var(--fg))] antialiased">
    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>

</html>
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/views/partials/header.blade.php resources/views/partials/footer.blade.php
git commit -m "feat(blade): add front Blade layout + header/footer stubs

New layouts.app document: ported FOUC/theme-color/font-preload/GA4 head
from the Inertia root, includes SEO + JSON-LD partials, loads blade.js
(Alpine+Motion), and provides @yield('content') + @stack points. Header/
footer are minimal stubs; full chrome lands in Phase 2.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 5: Error pages as first Blade pages + foundation feature test

**Files:**
- Create: `resources/views/errors/layout.blade.php`
- Create: `resources/views/errors/404.blade.php`
- Create: `resources/views/errors/500.blade.php`
- Create: `resources/views/errors/503.blade.php`
- Create: `tests/Feature/BladeFoundationTest.php`

**Interfaces:**
- Consumes: `layouts.app` (Task 4), the `'*'` composer (Task 2).
- Produces: Laravel-resolved error views (`errors.404` / `errors.500` / `errors.503`) rendered server-side through the Blade layout; the feature test proving the pipeline.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BladeFoundationTest.php`:
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class BladeFoundationTest extends TestCase
{
    public function test_404_page_renders_server_side_blade_html(): void
    {
        $this->withoutVite();

        $response = $this->get('/definitely-not-a-real-route-xyz-123');

        $response->assertStatus(404);
        // Real server-rendered content in the raw HTML body.
        $response->assertSee('404', false);
        // Server HTML carries the SEO title tag (from partials.seo).
        $response->assertSee('<title>', false);
        // NOT an Inertia client-only shell.
        $response->assertDontSee('id="app" data-page', false);
    }

    public function test_global_composer_shares_site_data_with_blade_views(): void
    {
        $this->withoutVite();

        $rendered = view('errors.404')->render();

        $this->assertStringContainsString(config('app.name'), $rendered);
    }

    public function test_seo_partial_emits_canonical_and_og_tags(): void
    {
        $this->withoutVite();

        $rendered = view('errors.404')->render();

        $this->assertStringContainsString('rel="canonical"', $rendered);
        $this->assertStringContainsString('property="og:title"', $rendered);
        $this->assertStringContainsString('application/ld+json', $rendered);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
vendor/bin/phpunit --filter=BladeFoundationTest
```
Expected: FAIL — `View [errors.404] not found` / `View [layouts.app] not found` (if Task 4 not yet applied) or missing content.

- [ ] **Step 3: Create the shared error shell**

Create `resources/views/errors/layout.blade.php`:
```blade
@extends('layouts.app')

@section('title', ($code ?? 'Error') . ' — ' . config('app.name'))
@section('robots', 'noindex,follow')

@section('content')
    <section class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center"
             x-data x-init="$reveal($el)">
        <p class="text-6xl font-semibold text-[hsl(var(--accent))]">{{ $code ?? 'Error' }}</p>
        <h1 class="mt-4 text-2xl font-semibold text-[hsl(var(--fg))]">{{ $title ?? 'Something went wrong' }}</h1>
        <p class="mt-2 text-[hsl(var(--fg-muted))]">{{ $message ?? '' }}</p>
        <a href="{{ url('/') }}"
           class="mt-8 rounded-lg bg-[hsl(var(--accent))] px-5 py-2.5 font-medium text-[hsl(var(--accent-fg))]">
            กลับหน้าแรก
        </a>
    </section>
@endsection
```

- [ ] **Step 4: Create the concrete error pages**

Create `resources/views/errors/404.blade.php`:
```blade
@include('errors.layout', [
    'code' => '404',
    'title' => 'ไม่พบหน้าที่คุณค้นหา',
    'message' => 'หน้านี้อาจถูกย้ายหรือลบไปแล้ว',
])
```

Create `resources/views/errors/500.blade.php`:
```blade
@include('errors.layout', [
    'code' => '500',
    'title' => 'เกิดข้อผิดพลาดของระบบ',
    'message' => 'โปรดลองใหม่อีกครั้งในภายหลัง',
])
```

Create `resources/views/errors/503.blade.php`:
```blade
@include('errors.layout', [
    'code' => '503',
    'title' => 'ระบบกำลังปรับปรุง',
    'message' => 'โปรดกลับมาใหม่อีกครั้งในภายหลัง',
])
```

- [ ] **Step 5: Run the test to verify it passes**

Run:
```bash
vendor/bin/phpunit --filter=BladeFoundationTest
```
Expected: PASS (3 tests). If `errors.500`/`errors.503` interfere with existing behavior, they only render on their status codes.

- [ ] **Step 6: Verify the full suite is still green (no Inertia regression)**

Run:
```bash
vendor/bin/phpunit
```
Expected: all tests PASS — existing Inertia feature tests unaffected (this phase added no changes to Inertia paths).

- [ ] **Step 7: Manual SSR spot-check (optional but recommended)**

Run the dev server and confirm the 404 body is real server HTML:
```bash
php artisan serve &
sleep 2
curl -s http://127.0.0.1:8000/definitely-not-a-real-route-xyz-123 | grep -o '<title>[^<]*</title>'
```
Expected: prints a `<title>404 — ...</title>` line present in the raw response (no JS execution needed). Stop the server afterward.

- [ ] **Step 8: Commit**

```bash
git add resources/views/errors tests/Feature/BladeFoundationTest.php
git commit -m "feat(blade): render error pages via Blade layout + foundation test

Errors 404/500/503 become the first real server-rendered Blade pages
through layouts.app, proving the pipeline (layout, tokens, Alpine \$reveal,
SEO meta, JSON-LD, shared composer data). BladeFoundationTest asserts raw
server HTML + SEO tags and no Inertia shell.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

## Self-Review

**1. Spec coverage (Phase 1 items from the design doc):**
- Front layout (`layouts/app.blade.php`) → Task 4 ✓
- Admin layout → **deferred to Phase 4** (no admin pages render this phase; stub not needed yet). Noted as intentional scope trim.
- Port AHD tokens/Tailwind → reused as-is (no change needed; layout references `hsl(var(--...))`) ✓
- Alpine + Motion bootstrap (`app.js`) → Task 1 (`blade.js`) ✓
- Vite asset pipeline for Blade (`@vite`) → Task 1 + Task 4 ✓
- SEO `<head>` partial + JSON-LD partials → Task 3 ✓
- Shared props via View Composer → Task 2 ✓
- Strip Inertia bootstrap / remove `ahd-ssr` / remove SSR config+env → **intentionally deferred to final cleanup phase** (removing them now breaks unmigrated Inertia pages in Phases 2–4). This refines the spec's phase-1 wording; the goal "no Node SSR daemon" is realized at end of migration, prod stays stable throughout. Called out in the plan's Architecture note.
- Ship one page through the new layout to prove the pipeline → Task 5 (error pages) ✓

**2. Placeholder scan:** No "TBD"/"TODO"/"add error handling"/vague steps. All code blocks are complete and runnable. Thai copy strings are final, not placeholders.

**3. Type/name consistency:** `blade.js` entry name matches between Task 1 (create + Vite input) and Task 4 (`@vite(['resources/js/blade.js'])`). `$reveal` magic defined in Task 1, used in Task 5. Composer variables (`$siteName`, `$appearance`) defined in Task 2 / existing `HandleAppearance`, consumed in Task 4. `layouts.app` created in Task 4, extended in Task 5. `partials.seo` / `partials.schema.website` created in Task 3, included in Task 4. All consistent.

**4. Non-breaking guarantee:** No task edits `app.ts`, `ssr.ts`, `app.blade.php`, `ecosystem.config.cjs`, `config/inertia.php`, or `HandleInertiaRequests`. Vite `input` is appended, not replaced. Full suite re-run in Task 5 Step 6 guards against regression.
