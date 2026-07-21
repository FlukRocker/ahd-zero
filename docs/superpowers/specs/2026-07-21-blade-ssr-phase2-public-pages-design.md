# Design: Blade SSR Migration — Phase 2: Public Read Pages

**Date:** 2026-07-21
**Status:** Approved (design), pending implementation plans
**Depends on:** Phase 1 (Foundation) — merged (Blade layout, Alpine+Motion entry, global composer, SEO/JSON-LD partials, error pages).
**Parent spec:** `docs/superpowers/specs/2026-07-20-blade-ssr-migration-design.md`

## Goal

Port every public **read** page from the Inertia/Vue SPA to server-rendered
Blade, and build the real site chrome (replacing Phase 1's header/footer stubs).
Pages covered: Index (home), Category, Search, Studio, VoiceActor,
directory/{Studios, VoiceActors, Staff}, Anime detail, Episode (watch/player).
Backend controllers keep their data contracts; only the render call changes
(`Inertia::render(...)` → `view(...)`).

Additive/non-breaking still holds: Inertia and Blade coexist. A page is
"migrated" when its controller returns a Blade view instead of an Inertia
response. The Node SSR daemon and Inertia removal remain deferred to the final
cleanup phase.

## Decisions (SEO-optimized, per user directive "pick everything based on SEO score")

1. **Navigation: plain MPA.** Prev/next episode = normal `<a>` links (full page
   load). Directory search-as-you-type = Alpine debounce → full navigation
   (`window.location`). No client router, no fetch-swap. Real URLs, full SSR
   every request, best crawlability. Matches `../lnw-anime`.
2. **Motion: static.** Drop all decorative JS motion — pointer-tilt, hero
   parallax, hero-carousel autoplay, scroll-reveal — because it adds JS weight
   and CLS/LCP/TBT risk for zero SEO value. Keep layout/tokens/spacing identical
   and retain **CSS-only** hover transitions (scale/shadow/gradient) which cost
   nothing and cause no layout shift. The homepage hero becomes a **single,
   server-selected, `<link rel=preload>`'d LCP image**, not a JS carousel — the
   single biggest LCP win on the site.
3. **Comments: deferred to Phase 3.** The comment subsystem is client-fetch
   REST; rendering it client-side helps SEO on neither option, so it stays in
   Phase 3. Phase 2 renders a comment-section **mount point** (an empty
   container) on Anime/Episode pages. Noted Phase-3 SEO enhancement: SSR the
   first page of comments so the UGC is in the initial HTML and crawlable.

## Reference pattern (`../lnw-anime`)

Blade components (`<x-anime-card>`, `<x-hero-section>`, `<x-section-header>`),
an image helper (`signurl` / `signurl_srcset`), and an **SEO-hidden,
keyword-rich `<h1>`** (visually hidden via inline clip style, readable by
Googlebot). We mirror all three. We diverge on motion: lnw cards use an
`$enterAnim` Alpine magic — we omit it (static decision) and rely on CSS hover.

## Architecture

### A. Server-side presenters (port the Vue `lib/` — the SEO-critical layer)

Everything the Vue app computed client-side that lands in HTML must move to PHP.

- **`App\Support\CardPresenter`** — ports `resources/js/lib/animeCard.ts`
  (`toCardItem`). Given an `Anime` (or the array shapes controllers already
  build), returns a normalized card array:
  `id, title, poster, landscape, tag, ep, kanji, genre, href, cat_type`.
  - poster fallback chain: `cover_md → cat_image → cover_th → placeholder`
  - landscape fallback: `banner_md → banner_original → cat_image → placeholder`
  - tag: `Currently Airing/airing → กำลังฉาย`; `cat_type 1 → ซับไทย`,
    `2 → พากย์ไทย`, `3 → มูฟวี่`
  - ep: `cat_type 3 → มูฟวี่`, else `"{episode_list_count ?? episodes} ตอน"`
  - href: `/anime/{cat_id}`
- **Image helper** — extend `App\Services\ImageVariantService` (or a new
  `App\Support\Img` helper) with the Bunny Optimizer transform ported from
  `resources/js/lib/img.ts`: only rewrite `img-cdn.shirokami.me` URLs to the
  `img-cdn-proxy.shirokami.me` pull zone, strip `.md`/`.th` variant suffix,
  append `width/height/quality(80)/aspect/crop`; other hosts pass through
  unchanged. Provide `srcset` builder for responsive images. The existing
  `ImageVariantService::getVariant` (Chevereto `.md`/`.th` HEAD-checked variant)
  stays; the Bunny transform is a separate concern (resize/format).
- **`App\Support\Schema`** — PHP JSON-LD builders porting
  `resources/js/lib/schema.ts`: `tvSeries(...)`, `videoObject(...)`,
  `breadcrumb([...])`. WebSite + Organization already shipped in Phase 1's
  `partials/schema/website.blade.php`. **Bug fixed in the port:**
  `schema.ts`'s `absoluteUrl()` was a client no-op on the server (returned
  relative paths), so SSR JSON-LD emitted relative URLs — the PHP builders
  resolve absolute URLs via `url()`/`config('app.url')`. JSON-LD emitted via a
  Blade partial using `json_encode(..., JSON_UNESCAPED_SLASHES |
  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)` (HEX_TAG guards `</script>` breakout
  now that anime/user titles flow in — carried from Phase 1's note).

### B. Real site chrome (Blade components + Alpine — replaces Phase 1 stubs)

Overwrite `resources/views/partials/header.blade.php` /
`partials/footer.blade.php` stubs with the real components, plus new ones:

- **`x-site-header`** — sticky header; scroll-glass background (CSS
  `backdrop-blur` + a tiny Alpine `x-data` scroll flag, or pure CSS
  `position: sticky`); primary nav; search trigger button; theme toggle
  (`@click="$store.appearance.toggle()"` — the Phase 1 store); mobile nav drawer
  (`x-data` open state); user dropdown menu (Alpine, click-outside) shown when
  `$memberAuth` present with logout `<form method="POST" action="/member/logout">`.
- **`x-search-overlay`** — Alpine modal; ⌘K/Ctrl-K global keydown to open;
  autofocus; Escape + click-outside to close; `<form method="GET"
  action="/search/results">` with an `q` input (native submit → full nav).
- **`x-site-footer`** — real footer (brand blurb, link columns, legal).
- **`x-about-seo`** — static SEO copy block rendered inside `<main>` on public
  pages (ported from `AboutSeo.vue`).
- **`x-ads-navbar`** — SSR sponsored-links bar from `$navbarAds`.
- **`x-ads-floating`** — fixed rail + bottom strip; **only** on Anime/Episode
  (data passed by those controllers, not global); Alpine dismiss (page-local,
  no persistence).

The front layout `layouts/app.blade.php` (Phase 1) is updated to render
`x-site-header` / `x-site-footer` / `x-about-seo` / `x-ads-navbar` in place of
the stub includes, and to keep an SEO-hidden keyword `<h1>` slot pattern.

### C. Card & section partials (Blade components, static/CSS-only)

- `x-poster-card` (vertical 3:4 tile; `srcset`; CSS hover scale + play-overlay;
  `loading="lazy"` except LCP), `x-landscape-card` (16:9), `x-editorial-card`
  (split), `x-section-header` (eyebrow + `<h2>` + optional more-link),
  `x-card-grid` (plain responsive CSS grid), `x-rail` (CSS `scroll-snap`, no JS).
- All consume `CardPresenter` output so every surface shares one normalized
  shape.

### D. Pages (controllers swap render, data unchanged)

Each Blade page `@extends('layouts.app')`, sets `@section('title')` /
`@section('description')` / `@section('robots')` / `@section('og_image')`, emits
its JSON-LD via `@include('partials.schema.<type>')`, and renders content with
the components above. Controllers change only the return call and (where
convenient) pass `CardPresenter`-normalized arrays.

- **Index** — hero (static LCP image = first featured) + recommended/popular/
  latest grids + pagination (plain `<a>` links, Laravel paginator). Schema:
  WebSite + Organization (reuse Phase 1 partial). Preload first hero image
  `fetchpriority=high`.
- **Category** — grid + pagination; breadcrumb JSON-LD; title = categoryName.
- **Search** — results grid + pagination; `@section('robots','noindex,follow')`;
  no schema. (The search *input* lives in the header overlay.)
- **Studio / VoiceActor** — header block + anime grid + pagination; breadcrumb
  JSON-LD.
- **directory/{Studios,VoiceActors,Staff}** — tile list + pagination + a search
  box (Alpine `x-model` + debounce → full navigation to `?q=`).
- **Anime detail** — hero banner, poster, meta (genres/studios/etc.), synopsis
  (`cat_desc` via `{!! !!}` — trusted backend HTML; sanitize if source is
  untrusted), episode list (plain `<a>` grid), characters (first 12), related
  `x-rail`, ads (`x-ads-banner`, `x-ads-floating`), **comment mount point**.
  Schema: TVSeries + breadcrumb.
- **Episode / player** — see E.

### E. Episode / player page (most complex)

- Server computes **both** iframe URLs up front (no client base64 needed):
  - ads mode: `{adsEmbedUrl}?link={urlencode(base64(playerUrl))}`
  - direct mode: `playerUrl` forced to `https://`
- **Alpine player-mode toggle**: `x-data` holds `mode` (init from
  `localStorage['ahd.playerMode']`, default ads); buttons switch mode; the
  `<iframe :src>` binds to the matching precomputed URL; persist to localStorage
  on change. iframe `allow`/`allowfullscreen`/`referrerpolicy` copied verbatim.
- **Prev/next episode**: plain `<a>` full-navigation links (MPA decision).
- **Episode sidebar**: server-rendered list, current highlighted.
- `x-player-ad-slot` top/bottom; `x-ads-banner`; comment mount point.
- Schema: VideoObject + breadcrumb (`upload_date_iso`, `player_url` → embedUrl).

## Plan decomposition (built in order; each is its own plan → subagent-driven build)

- **2a — Foundation for pages:** `CardPresenter`, Bunny image helper, `Schema`
  builders + JSON-LD partials, real site chrome (`x-site-header`,
  `x-search-overlay`, `x-site-footer`, `x-about-seo`, `x-ads-navbar`,
  `x-ads-floating`), card/section components (`x-poster-card`,
  `x-landscape-card`, `x-editorial-card`, `x-section-header`, `x-card-grid`,
  `x-rail`), layout update. Ships with a component-render test + one converted
  page (Index) to exercise the whole chain.
- **2b — List pages:** Category, Search, Studio, VoiceActor,
  directory/{Studios, VoiceActors, Staff}. (Index handled in 2a.)
- **2c — Anime detail.**
- **2d — Episode / player.**

Each plan converts its controllers' render calls and adds feature tests.

## Testing

- **Feature test per page:** `assertOk()`, `assertViewIs('<view>')`,
  `assertSee(...)` real content in raw HTML (title, an anime title, breadcrumb),
  SEO assertions (`rel="canonical"`, correct `robots`, `application/ld+json`
  with the right `@type`), and `assertDontSee('id="app" data-page')` (no Inertia
  shell). Search page asserts `noindex,follow`.
- **Presenter unit tests:** `CardPresenter` fallback chains + tag/ep logic;
  image helper host rewrite/passthrough + variant strip.
- **Legacy redirect tests** stay green.
- **Manual CWV spot-check** (PageSpeed/Lighthouse) on Index + Anime after 2a/2c
  — verify LCP (preloaded hero), no CLS from the static port, JS weight dropped
  vs the Vue SPA.

## Risks & tradeoffs

- **Data-shape drift:** Vue `toCardItem` normalization moves to `CardPresenter`;
  controllers currently pass slightly different array shapes per page (Index
  featured vs paginator vs episode). `CardPresenter` must accept both Eloquent
  `Anime` and the existing array shapes — cover with unit tests.
- **`cat_desc` is raw HTML** rendered via `{!! !!}`. If the backend source is
  ever user-influenced, this is XSS — confirm the source is trusted (admin/
  import) or sanitize (`Purifier`/`strip_tags` allowlist). Flag during 2c.
- **Losing SPA feel:** full reloads on nav (accepted for SEO). CSS hover keeps
  cards feeling responsive.
- **Ad components** read floating ads only on Anime/Episode — keep that scoping
  (don't globalize) to match current placement.
- **Pagination:** Laravel paginator `links()` must be themed to AHD tokens
  (custom pagination view) — the existing `@source` already scans the vendor
  pagination views; provide a Tailwind-styled pagination partial.

## Out of scope

- Comments interactivity (Phase 3), member/auth/admin pages (Phase 3/4).
- Removing Inertia/Vue, the SSR daemon, or deps (final cleanup phase).
- Redesign — match current AHD visual output (tokens/layout), minus dropped
  decorative motion.
- Any migration against `yu_anime_*` tables (imported schema).
