# Blade SSR Phase 2a-1 — Presenters + Content Components + Index — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the server-side presenter layer (card normalization, Bunny image URLs, JSON-LD schema) and the static content Blade components, then convert the home page (`IndexController` → `resources/views/index.blade.php`) to server-rendered Blade rendering through them.

**Architecture:** Ports the Vue `lib/` helpers to PHP (`App\Support\CardPresenter`, `App\Support\Img`, `App\Support\Schema`) so all card/SEO data is computed server-side into HTML. Blade components (`x-poster-card`, `x-section-header`, `x-card-grid`, `x-rail`, `x-hero`, `x-about-seo`) reproduce the existing AHD markup reusing the CSS classes already in `resources/css/app.css`, minus decorative JS motion (static decision). Index renders through the Phase-1 `layouts.app` (with its stub header/footer — real interactive chrome is Phase 2a-2). Additive/non-breaking: Inertia untouched; only `IndexController` flips from `Inertia::render` to `view()`.

**Tech Stack:** Laravel 12, PHP 8.4, Blade components, Tailwind v4, existing AHD tokens.

## Global Constraints

- Laravel 12 / PHP 8.4; pnpm; tests on in-memory sqlite (`vendor/bin/phpunit`).
- **Additive/non-breaking:** do NOT touch `resources/js/*` (Inertia stays), `ssr.ts`, `ecosystem.config.cjs`, `config/inertia.php`, `HandleInertiaRequests`, or the Inertia root `resources/views/app.blade.php`. The ONLY Inertia-related change is `IndexController::renderIndex` switching `Inertia::render('Index', ...)` → `view('index', ...)`.
- **Static motion:** no pointer-tilt, no parallax, no scroll-reveal, no hero carousel autoplay. Keep CSS-only hover (`group-hover` scale/opacity) which is already in the reused classes. Do NOT add Alpine to content components in this plan (chrome/interactivity is 2a-2).
- **Reuse existing CSS classes** from `resources/css/app.css`: `poster-card`, `halo`, `sticker`, `grad-bot`, `rating-pill`, `btn`, `btn-primary`, `font-display`, `font-mono`. `about-block` and `ads-navbar` styles were Vue `<style scoped>` — port them into `app.css` (Task 6).
- **Design tokens** referenced as `hsl(var(--token))`: `--bg`, `--bg-soft`, `--bg-elev`, `--fg`, `--fg-muted`, `--fg-faint`, `--accent`, `--border-ahd`.
- Bunny image rule: only `img-cdn.shirokami.me` URLs get rewritten to `https://img-cdn-proxy.shirokami.me` with optimizer params; every other host passes through unchanged. Strip `.md`/`.th` variant suffix before proxying. Default quality 80.
- Card tag/ep Thai strings (verbatim): `cat_type 1 → ซับไทย`, `2 → พากย์ไทย`, `3 → มูฟวี่`; `anime_status` `Currently Airing`/`airing → กำลังฉาย`; ep string `"{count} ตอน"`, or `มูฟวี่` when `cat_type === 3`.
- Never migrate `yu_anime_*` tables. Redis clears match `CACHE_PREFIX` only.

## File Structure

- `app/Support/CardPresenter.php` — Anime→card-array normalizer (ports `resources/js/lib/animeCard.ts`).
- `app/Support/Img.php` — Bunny Optimizer URL + srcset builder (ports `resources/js/lib/img.ts`).
- `app/Support/Schema.php` — JSON-LD array builders (ports `resources/js/lib/schema.ts`), absolute URLs server-side.
- `resources/views/components/json-ld.blade.php` — renders a schema array as a safe `<script type="application/ld+json">`.
- `resources/views/pagination/ahd.blade.php` — AHD-themed paginator view.
- `resources/views/components/{section-header,poster-card,card-grid,rail,hero,about-seo}.blade.php` — content components.
- `resources/views/index.blade.php` — home page.
- `resources/css/app.css` — append `.about-block` + `.ads-navbar` styles (ported from the Vue scoped styles).
- Tests: `tests/Unit/CardPresenterTest.php`, `tests/Unit/ImgTest.php`, `tests/Unit/SchemaTest.php`, `tests/Feature/IndexPageTest.php`.

---

### Task 1: CardPresenter (ports animeCard.ts)

**Files:**
- Create: `app/Support/CardPresenter.php`
- Test: `tests/Unit/CardPresenterTest.php`

**Interfaces:**
- Consumes: an `Illuminate\Database\Eloquent\Model` (Anime) or an associative array with keys `cat_id, cat_title, cat_image, cat_type, anime_status, episodes, anime_type, banner_md, cover_md, cover_th, title_japanese, episode_list_count`.
- Produces: `CardPresenter::make($item): array` returning keys `id, title, poster, landscape, tag, ep, kanji, genre, href, cat_type`; and `CardPresenter::collection(iterable $items): array` (list of those).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/CardPresenterTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Support\CardPresenter;
use PHPUnit\Framework\TestCase;

class CardPresenterTest extends TestCase
{
    public function test_poster_fallback_chain_prefers_cover_md(): void
    {
        $card = CardPresenter::make([
            'cat_id' => 5,
            'cat_title' => 'X',
            'cover_md' => 'https://img-cdn.shirokami.me/a.md.jpg',
            'cat_image' => 'https://img-cdn.shirokami.me/b.jpg',
        ]);

        $this->assertSame('https://img-cdn.shirokami.me/a.md.jpg', $card['poster']);
        $this->assertSame('/anime/5', $card['href']);
        $this->assertSame(5, $card['id']);
    }

    public function test_poster_falls_back_to_placeholder_when_all_missing(): void
    {
        $card = CardPresenter::make(['cat_id' => 1, 'cat_title' => 'Y']);
        $this->assertSame('/images/placeholder-poster.webp', $card['poster']);
        $this->assertSame('/images/placeholder-poster.webp', $card['landscape']);
    }

    public function test_tag_by_type_and_status(): void
    {
        $this->assertSame('กำลังฉาย', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'anime_status' => 'Currently Airing'])['tag']);
        $this->assertSame('ซับไทย', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 1])['tag']);
        $this->assertSame('พากย์ไทย', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 2])['tag']);
        $this->assertSame('มูฟวี่', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 3])['tag']);
    }

    public function test_ep_string_uses_count_or_movie_label(): void
    {
        $this->assertSame('12 ตอน', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 1, 'episode_list_count' => 12])['ep']);
        $this->assertSame('มูฟวี่', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 3, 'episodes' => 1])['ep']);
        $this->assertSame('', CardPresenter::make(['cat_id' => 1, 'cat_title' => 'A', 'cat_type' => 1])['ep']);
    }

    public function test_collection_maps_each_item(): void
    {
        $out = CardPresenter::collection([
            ['cat_id' => 1, 'cat_title' => 'A'],
            ['cat_id' => 2, 'cat_title' => 'B'],
        ]);
        $this->assertCount(2, $out);
        $this->assertSame('/anime/2', $out[1]['href']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=CardPresenterTest`
Expected: FAIL — `Class "App\Support\CardPresenter" not found`.

- [ ] **Step 3: Implement CardPresenter**

Create `app/Support/CardPresenter.php`:
```php
<?php

namespace App\Support;

class CardPresenter
{
    private const PLACEHOLDER = '/images/placeholder-poster.webp';

    /**
     * Normalize an Anime model or array into the card shape used by every
     * poster/landscape component. Ports resources/js/lib/animeCard.ts.
     *
     * @param  \Illuminate\Database\Eloquent\Model|array<string,mixed>  $item
     * @return array<string,mixed>
     */
    public static function make($item): array
    {
        $get = static fn (string $key, $default = null) => data_get($item, $key, $default);

        $catType = (int) ($get('cat_type') ?? 1);

        return [
            'id' => $get('cat_id'),
            'title' => (string) $get('cat_title'),
            'poster' => self::resolvePoster($get),
            'landscape' => self::resolveLandscape($get),
            'tag' => self::resolveTag($get, $catType),
            'ep' => self::resolveEp($get, $catType),
            'kanji' => (string) ($get('title_japanese') ?? ''),
            'genre' => (string) ($get('anime_type') ?? ''),
            'href' => '/anime/'.$get('cat_id'),
            'cat_type' => $catType,
        ];
    }

    /**
     * @param  iterable<mixed>  $items
     * @return list<array<string,mixed>>
     */
    public static function collection(iterable $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = self::make($item);
        }

        return $out;
    }

    private static function resolvePoster(callable $get): string
    {
        return $get('cover_md') ?: $get('cat_image') ?: $get('cover_th') ?: self::PLACEHOLDER;
    }

    private static function resolveLandscape(callable $get): string
    {
        return $get('banner_md') ?: $get('banner_original') ?: $get('cat_image') ?: self::PLACEHOLDER;
    }

    private static function resolveTag(callable $get, int $catType): ?string
    {
        $status = $get('anime_status');
        if ($status === 'Currently Airing' || $status === 'airing') {
            return 'กำลังฉาย';
        }

        return match ($catType) {
            1 => 'ซับไทย',
            2 => 'พากย์ไทย',
            3 => 'มูฟวี่',
            default => null,
        };
    }

    private static function resolveEp(callable $get, int $catType): string
    {
        if ($catType === 3) {
            return 'มูฟวี่';
        }

        $count = $get('episode_list_count');
        if ($count === null) {
            $count = $get('episodes');
        }

        return $count === null ? '' : $count.' ตอน';
    }
}
```
(`data_get` is a global Laravel helper — no import needed.)

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter=CardPresenterTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/CardPresenter.php tests/Unit/CardPresenterTest.php
git commit -m "feat(blade): add CardPresenter (ports animeCard.ts to PHP)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 2: Img helper (ports img.ts / Bunny Optimizer)

**Files:**
- Create: `app/Support/Img.php`
- Test: `tests/Unit/ImgTest.php`

**Interfaces:**
- Consumes: an image URL string (or null).
- Produces: `Img::url(?string $url, array $opts = []): ?string` (single optimized URL); `Img::srcset(?string $url, array $widths, array $opts = []): ?string`; width/size constants `Img::POSTER_WIDTHS`, `Img::LANDSCAPE_WIDTHS`, `Img::HERO_WIDTHS`, `Img::POSTER_SIZES`, `Img::LANDSCAPE_SIZES`, `Img::HERO_SIZES`. `$opts` keys: `width, height, quality, format, aspect, crop`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ImgTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Support\Img;
use PHPUnit\Framework\TestCase;

class ImgTest extends TestCase
{
    public function test_non_proxied_host_passes_through_unchanged(): void
    {
        $u = 'https://cdn.myanimelist.net/images/a.jpg';
        $this->assertSame($u, Img::url($u, ['width' => 360]));
    }

    public function test_null_returns_null(): void
    {
        $this->assertNull(Img::url(null, ['width' => 360]));
    }

    public function test_proxied_host_is_rewritten_with_optimizer_params(): void
    {
        $out = Img::url('https://img-cdn.shirokami.me/images/2024/a.md.png', ['width' => 480, 'format' => 'webp']);
        $this->assertStringStartsWith('https://img-cdn-proxy.shirokami.me/images/2024/a.png?', $out);
        $this->assertStringContainsString('width=480', $out);
        $this->assertStringContainsString('format=webp', $out);
        $this->assertStringContainsString('quality=80', $out);
        // .md variant suffix stripped before proxying
        $this->assertStringNotContainsString('.md.png', $out);
    }

    public function test_default_quality_is_80_and_auto_format_not_pinned(): void
    {
        $out = Img::url('https://img-cdn.shirokami.me/x.jpg', ['width' => 360]);
        $this->assertStringContainsString('quality=80', $out);
        $this->assertStringNotContainsString('format=', $out);
    }

    public function test_srcset_builds_width_descriptors(): void
    {
        $out = Img::srcset('https://img-cdn.shirokami.me/x.jpg', [240, 480], ['format' => 'webp']);
        $this->assertStringContainsString('width=240', $out);
        $this->assertStringContainsString(' 240w', $out);
        $this->assertStringContainsString(' 480w', $out);
        $this->assertStringContainsString(', ', $out);
    }

    public function test_srcset_null_for_non_proxied_returns_descriptors_of_passthrough(): void
    {
        // Non-proxied host: each entry is the original URL with a width descriptor.
        $out = Img::srcset('https://cdn.myanimelist.net/x.jpg', [240, 480]);
        $this->assertStringContainsString('https://cdn.myanimelist.net/x.jpg 240w', $out);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=ImgTest`
Expected: FAIL — `Class "App\Support\Img" not found`.

- [ ] **Step 3: Implement Img**

Create `app/Support/Img.php`:
```php
<?php

namespace App\Support;

class Img
{
    private const BUNNY_PROXY = 'https://img-cdn-proxy.shirokami.me';

    private const PROXIED_SOURCE_HOST = 'img-cdn.shirokami.me';

    /** @var list<int> */
    public const POSTER_WIDTHS = [240, 360, 480, 600];

    /** @var list<int> */
    public const LANDSCAPE_WIDTHS = [340, 520, 760, 1020];

    /** @var list<int> */
    public const HERO_WIDTHS = [800, 1200, 1600, 2000];

    public const POSTER_SIZES = '(max-width: 600px) 45vw, (max-width: 1200px) 25vw, 200px';

    public const LANDSCAPE_SIZES = '(max-width: 800px) 90vw, (max-width: 1400px) 45vw, 340px';

    public const HERO_SIZES = '100vw';

    /**
     * Build a Bunny Optimizer URL. Non-shirokami-proxied origins pass through
     * unchanged. Ports resources/js/lib/img.ts `bunnyImg`.
     *
     * @param  array{width?:int,height?:int,quality?:int,format?:string,aspect?:string,crop?:string}  $opts
     */
    public static function url(?string $url, array $opts = []): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['host'])) {
            return $url;
        }

        if ($parsed['host'] !== self::PROXIED_SOURCE_HOST) {
            return $url;
        }

        $path = self::stripVariant($parsed['path'] ?? '');

        $params = [];
        if (isset($opts['width'])) {
            $params['width'] = (string) $opts['width'];
        }
        if (isset($opts['height'])) {
            $params['height'] = (string) $opts['height'];
        }
        if (isset($opts['aspect'])) {
            $params['aspect_ratio'] = $opts['aspect'];
        }
        if (isset($opts['crop'])) {
            $params['crop'] = $opts['crop'];
        }
        $params['quality'] = (string) ($opts['quality'] ?? 80);
        if (isset($opts['format']) && $opts['format'] !== 'auto') {
            $params['format'] = $opts['format'];
        }

        // Preserve any pre-existing query params (e.g. signed-URL signatures)
        // without overriding our optimizer params.
        if (isset($parsed['query']) && $parsed['query'] !== '') {
            parse_str($parsed['query'], $existing);
            foreach ($existing as $k => $v) {
                if (! array_key_exists($k, $params)) {
                    $params[$k] = is_array($v) ? implode(',', $v) : (string) $v;
                }
            }
        }

        return self::BUNNY_PROXY.$path.'?'.http_build_query($params);
    }

    /**
     * Build a srcset from a list of widths. Ports `bunnySrcset`.
     *
     * @param  list<int>  $widths
     * @param  array<string,mixed>  $opts
     */
    public static function srcset(?string $url, array $widths, array $opts = []): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $entries = [];
        foreach ($widths as $w) {
            // ['width' => $w] first so the per-descriptor width always wins
            // over any width in $opts (PHP array-union keeps the left operand).
            $u = self::url($url, ['width' => $w] + $opts);
            if ($u !== null) {
                $entries[] = $u.' '.$w.'w';
            }
        }

        return $entries === [] ? null : implode(', ', $entries);
    }

    private static function stripVariant(string $path): string
    {
        // Strip a Chevereto .md / .th variant suffix so Bunny resizes from the
        // canonical original: /a.md.png -> /a.png
        $result = preg_replace('/\.(md|th)(\.[a-z0-9]+)$/i', '$2', $path);

        return $result ?? $path;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter=ImgTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Img.php tests/Unit/ImgTest.php
git commit -m "feat(blade): add Img helper (ports Bunny Optimizer img.ts to PHP)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 3: Schema builders + json-ld component (ports schema.ts)

**Files:**
- Create: `app/Support/Schema.php`
- Create: `resources/views/components/json-ld.blade.php`
- Test: `tests/Unit/SchemaTest.php`

**Interfaces:**
- Consumes: plain scalars/arrays (names, urls, crumbs).
- Produces: `Schema::tvSeries(array $input): array`, `Schema::videoObject(array $input): array`, `Schema::breadcrumb(array $crumbs): array` — each returns a JSON-LD array with absolute URLs. Blade usage: `<x-json-ld :data="\App\Support\Schema::breadcrumb([...])" />`.
- Note: WebSite + Organization JSON-LD is ALREADY emitted globally by Phase 1's `partials/schema/website.blade.php` (included in `layouts.app`). Do NOT re-emit them per page — the home page needs no page-level schema.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SchemaTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Support\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    public function test_breadcrumb_resolves_absolute_urls_and_positions(): void
    {
        config(['app.url' => 'https://anime-hdzero.com']);
        $b = Schema::breadcrumb([
            ['name' => 'หน้าแรก', 'url' => '/'],
            ['name' => 'Naruto', 'url' => '/anime/12'],
        ]);

        $this->assertSame('BreadcrumbList', $b['@type']);
        $this->assertSame(1, $b['itemListElement'][0]['position']);
        $this->assertSame('https://anime-hdzero.com', $b['itemListElement'][0]['item']);
        $this->assertSame('https://anime-hdzero.com/anime/12', $b['itemListElement'][1]['item']);
    }

    public function test_tvseries_omits_empty_optionals_and_keeps_absolute_url(): void
    {
        config(['app.url' => 'https://anime-hdzero.com']);
        $t = Schema::tvSeries([
            'name' => 'Naruto',
            'url' => '/anime/12',
            'genre' => ['Action'],
            'productionCompany' => [['name' => 'Pierrot']],
        ]);

        $this->assertSame('TVSeries', $t['@type']);
        $this->assertSame('https://anime-hdzero.com/anime/12', $t['url']);
        $this->assertSame(['Action'], $t['genre']);
        $this->assertSame('Organization', $t['productionCompany'][0]['@type']);
        $this->assertArrayNotHasKey('description', $t);
    }

    public function test_video_object_maps_series_and_urls(): void
    {
        config(['app.url' => 'https://anime-hdzero.com']);
        $v = Schema::videoObject([
            'name' => 'Ep 1',
            'embedUrl' => 'https://player.example/watch/abc',
            'partOfSeries' => ['name' => 'Naruto', 'url' => '/anime/12'],
        ]);

        $this->assertSame('VideoObject', $v['@type']);
        $this->assertSame('https://player.example/watch/abc', $v['embedUrl']);
        $this->assertSame('https://anime-hdzero.com/anime/12', $v['partOfSeries']['url']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=SchemaTest`
Expected: FAIL — `Class "App\Support\Schema" not found`.

- [ ] **Step 3: Implement Schema**

Create `app/Support/Schema.php`:
```php
<?php

namespace App\Support;

class Schema
{
    /**
     * @param  array{name:string,alternateName?:?string,description?:?string,image?:?string,url:string,numberOfEpisodes?:?int,startDate?:?string,endDate?:?string,genre?:array<int,string>,actor?:array<int,array{name:string}>,productionCompany?:array<int,array{name:string}>}  $input
     * @return array<string,mixed>
     */
    public static function tvSeries(array $input): array
    {
        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'TVSeries',
            'name' => $input['name'],
            'url' => self::absolute($input['url']),
        ];

        foreach (['alternateName', 'description', 'image', 'startDate', 'endDate'] as $key) {
            if (! empty($input[$key])) {
                $payload[$key] = $input[$key];
            }
        }
        if (isset($input['numberOfEpisodes']) && $input['numberOfEpisodes'] !== null) {
            $payload['numberOfEpisodes'] = $input['numberOfEpisodes'];
        }
        if (! empty($input['genre'])) {
            $payload['genre'] = array_values($input['genre']);
        }
        if (! empty($input['actor'])) {
            $payload['actor'] = array_map(
                fn (array $a) => ['@type' => 'Person', 'name' => $a['name']],
                $input['actor'],
            );
        }
        if (! empty($input['productionCompany'])) {
            $payload['productionCompany'] = array_map(
                fn (array $c) => ['@type' => 'Organization', 'name' => $c['name']],
                $input['productionCompany'],
            );
        }

        return $payload;
    }

    /**
     * @param  array{name:string,description?:?string,thumbnailUrl?:?string,uploadDate?:?string,contentUrl?:?string,embedUrl?:?string,partOfSeries?:array{name:string,url:string}}  $input
     * @return array<string,mixed>
     */
    public static function videoObject(array $input): array
    {
        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $input['name'],
        ];

        foreach (['description', 'thumbnailUrl', 'uploadDate', 'contentUrl', 'embedUrl'] as $key) {
            if (! empty($input[$key])) {
                $payload[$key] = $input[$key];
            }
        }
        if (! empty($input['partOfSeries'])) {
            $payload['partOfSeries'] = [
                '@type' => 'TVSeries',
                'name' => $input['partOfSeries']['name'],
                'url' => self::absolute($input['partOfSeries']['url']),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<int,array{name:string,url:string}>  $crumbs
     * @return array<string,mixed>
     */
    public static function breadcrumb(array $crumbs): array
    {
        $items = [];
        foreach (array_values($crumbs) as $i => $c) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => self::absolute($c['url']),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private static function absolute(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base = rtrim((string) config('app.url'), '/');
        $trimmed = ltrim($path, '/');

        return $trimmed === '' ? $base : $base.'/'.$trimmed;
    }
}
```
Note: `absolute('/')` yields `https://host` (no trailing slash), matching the
Phase-1 canonical home URL (`url()->current()`), so the home breadcrumb item
and the canonical link agree.

- [ ] **Step 4: Create the json-ld render component**

Create `resources/views/components/json-ld.blade.php`:
```blade
@props(['data'])
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter=SchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Support/Schema.php resources/views/components/json-ld.blade.php tests/Unit/SchemaTest.php
git commit -m "feat(blade): add Schema JSON-LD builders + x-json-ld component

Ports schema.ts to PHP with server-resolved absolute URLs (fixes the
client-only absoluteUrl no-op) and JSON_HEX_TAG-safe output.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 4: AHD-themed pagination view

**Files:**
- Create: `resources/views/pagination/ahd.blade.php`

**Interfaces:**
- Consumes: a Laravel `LengthAwarePaginator` (`$paginator`).
- Produces: a themed paginator rendered via `{{ $paginator->links('pagination.ahd') }}`.

- [ ] **Step 1: Create the pagination view**

Create `resources/views/pagination/ahd.blade.php` (mirrors the styling of the old `Pagination.vue`: active pill = solid fg-on-bg, others = soft w/ border; disabled = faded):
```blade
@if ($paginator->hasPages())
    <nav class="mt-10 flex flex-wrap items-center justify-center gap-1" role="navigation" aria-label="Pagination">
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-3 py-2 font-mono text-[13px] opacity-40" style="color: hsl(var(--fg-muted))">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="inline-block rounded-full px-3 py-2 font-mono text-[13px]" style="background: hsl(var(--fg)); color: hsl(var(--bg));">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="inline-block rounded-full px-3 py-2 font-mono text-[13px] transition-colors" style="background: hsl(var(--bg-soft)); color: hsl(var(--fg-muted)); border: 1px solid hsl(var(--border-ahd));">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
    </nav>
@endif
```

- [ ] **Step 2: Verify it compiles**

Run: `php artisan view:clear` (real render happens in Task 7's Index test).
Expected: no error.

- [ ] **Step 3: Commit**

```bash
git add resources/views/pagination/ahd.blade.php
git commit -m "feat(blade): add AHD-themed pagination view

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 5: Content Blade components (static — section-header, poster-card, card-grid, rail, hero, about-seo)

**Files:**
- Create: `resources/views/components/section-header.blade.php`
- Create: `resources/views/components/poster-card.blade.php`
- Create: `resources/views/components/card-grid.blade.php`
- Create: `resources/views/components/rail.blade.php`
- Create: `resources/views/components/hero.blade.php`
- Create: `resources/views/components/about-seo.blade.php`
- Modify: `resources/css/app.css` (append `.about-block` styles)

**Interfaces:**
- Consumes: `CardPresenter` card arrays (`$item`), `Img` (Task 2). `x-hero` consumes one card array (`$item`) + optional `$eager` flag for LCP.
- Produces: reusable components `<x-section-header>`, `<x-poster-card :item="...">`, `<x-card-grid>...</x-card-grid>`, `<x-rail :items="[...]">`, `<x-hero :item="..." eager>`, `<x-about-seo />`.

- [ ] **Step 1: Create section-header** (ports `SectionHeader.vue`)

Create `resources/views/components/section-header.blade.php`:
```blade
@props(['eyebrow' => null, 'title', 'link' => null, 'linkHref' => null])

<div class="mb-6 flex items-end justify-between">
    <div>
        @if ($eyebrow)
            <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">{{ $eyebrow }}</div>
        @endif
        <h2 class="font-display text-[36px] leading-none italic md:text-[44px]">{{ $title }}</h2>
    </div>
    @if ($link)
        <a href="{{ $linkHref ?? '#' }}" class="u-grow hidden font-mono text-[13px] tracking-wider uppercase md:block">{{ $link }} →</a>
    @endif
</div>
```

- [ ] **Step 2: Create poster-card** (ports `PosterCard.vue`, static — Link→`<a>`, no tilt, no rank/rating unless present)

Create `resources/views/components/poster-card.blade.php`:
```blade
@props(['item', 'eager' => false])
@php
    $src = \App\Support\Img::url($item['poster'], ['width' => 360, 'format' => 'webp']) ?? $item['poster'];
    $srcset = \App\Support\Img::srcset($item['poster'], \App\Support\Img::POSTER_WIDTHS, ['format' => 'webp']);
@endphp
<a href="{{ $item['href'] }}" class="group block">
    <div class="poster-card">
        <div class="halo"></div>
        <img
            src="{{ $src }}"
            @if ($srcset) srcset="{{ $srcset }}" sizes="{{ \App\Support\Img::POSTER_SIZES }}" @endif
            alt="{{ $item['title'] }}"
            loading="{{ $eager ? 'eager' : 'lazy' }}"
            @if ($eager) fetchpriority="high" @endif
            decoding="async"
            width="300"
            height="450"
        >
        @if ($item['tag'])
            <span class="sticker">{{ $item['tag'] }}</span>
        @endif
        <div class="grad-bot"></div>
        <div class="absolute right-3 bottom-3 left-3 text-white">
            <div class="flex items-center gap-2 font-mono text-[11px] opacity-90">
                @if ($item['ep'])<span>{{ $item['ep'] }}</span>@endif
                @if ($item['ep'] && $item['genre'])<span>·</span>@endif
                @if ($item['genre'])<span>{{ $item['genre'] }}</span>@endif
            </div>
        </div>
        <div class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:opacity-100" style="background: rgba(0,0,0,0.25)">
            <span class="btn btn-primary">▶ Watch</span>
        </div>
    </div>
    <div class="mt-3">
        @if ($item['kanji'])
            <div class="mb-1 font-mono text-[11px]" style="color: hsl(var(--fg-faint))">{{ $item['kanji'] }}</div>
        @endif
        <div class="font-display text-[20px] leading-tight">{{ $item['title'] }}</div>
    </div>
</a>
```

- [ ] **Step 3: Create card-grid** (replaces StaggerGrid — plain responsive grid, no stagger JS)

Create `resources/views/components/card-grid.blade.php`:
```blade
<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6']) }}>
    {{ $slot }}
</div>
```

- [ ] **Step 4: Create rail** (CSS scroll-snap, no JS)

Create `resources/views/components/rail.blade.php`:
```blade
@props(['items' => []])
<div class="-mx-6 flex snap-x snap-mandatory gap-5 overflow-x-auto px-6 pb-4 lg:-mx-10 lg:px-10" style="scrollbar-width: thin;">
    @foreach ($items as $item)
        <div class="w-[46vw] shrink-0 snap-start sm:w-[240px]">
            <x-poster-card :item="$item" />
        </div>
    @endforeach
</div>
```

- [ ] **Step 5: Create hero** (static LCP hero — single image, first featured item; NOT a carousel)

Create `resources/views/components/hero.blade.php`:
```blade
@props(['item', 'eager' => true])
@php
    $bg = \App\Support\Img::url($item['landscape'], ['width' => 1600, 'format' => 'webp']) ?? $item['landscape'];
@endphp
<section class="relative mx-auto mt-6 max-w-[1440px] overflow-hidden rounded-3xl px-6 lg:px-10">
    <div class="relative grid gap-8 rounded-3xl border p-8 md:grid-cols-[1.3fr_1fr] md:p-12"
         style="border-color: hsl(var(--border-ahd)); background: hsl(var(--bg-elev));">
        <div class="flex flex-col justify-center">
            <div class="mb-3 font-mono text-[11px] tracking-[0.22em] uppercase" style="color: hsl(var(--accent))">แนะนำ</div>
            <h2 class="font-display text-[40px] leading-none italic md:text-[64px]">{{ $item['title'] }}</h2>
            @if ($item['kanji'])
                <div class="mt-3 font-mono text-[13px]" style="color: hsl(var(--fg-faint))">{{ $item['kanji'] }}</div>
            @endif
            <div class="mt-6 flex items-center gap-3">
                <a href="{{ $item['href'] }}" class="btn btn-primary">▶ ดูเลย</a>
                @if ($item['ep'])
                    <span class="font-mono text-[13px]" style="color: hsl(var(--fg-muted))">{{ $item['ep'] }}</span>
                @endif
            </div>
        </div>
        <div class="relative aspect-[16/10] overflow-hidden rounded-2xl md:aspect-auto">
            <img src="{{ $bg }}" alt="{{ $item['title'] }}"
                 @if ($eager) fetchpriority="high" loading="eager" @else loading="lazy" @endif
                 decoding="async" class="h-full w-full object-cover">
        </div>
    </div>
</section>
```

- [ ] **Step 6: Create about-seo** (ports `AboutSeo.vue` — static SEO copy; drop `.reveal`)

Create `resources/views/components/about-seo.blade.php` with the four `<article class="about-block">` blocks. Copy the Thai copy verbatim from `resources/js/components/ahd/AboutSeo.vue` (four `<h3>` + `<p>` articles under the `เกี่ยวกับ ANIME HD ZERO` heading), wrapped in `<aside class="mx-auto mt-24 mb-20 max-w-[920px] px-6 lg:px-10" role="complementary" aria-labelledby="about-anime-hd-zero">`. Do NOT include the `reveal` class (static decision). Structure:
```blade
<aside class="mx-auto mt-24 mb-20 max-w-[920px] px-6 lg:px-10" role="complementary" aria-labelledby="about-anime-hd-zero">
    <h2 id="about-anime-hd-zero" class="font-mono text-[11px] tracking-[0.25em] uppercase" style="color: hsl(var(--fg-muted))">เกี่ยวกับ ANIME HD ZERO</h2>

    <article class="about-block">
        <h3 class="font-display italic">ดูการ์ตูนที่ ANIME HD ZERO ได้อะไรบ้าง</h3>
        <p>{{-- verbatim paragraph 1 from AboutSeo.vue --}}</p>
    </article>
    {{-- repeat for the remaining 3 articles, verbatim copy from AboutSeo.vue --}}
</aside>
```
(The implementer must copy the exact Thai text of all four paragraphs from `resources/js/components/ahd/AboutSeo.vue` lines 24–115 — do not paraphrase.)

- [ ] **Step 7: Append `.about-block` styles to app.css**

Append to `resources/css/app.css` (ported from `AboutSeo.vue`'s `<style scoped>`):
```css
/* AboutSeo block (ported from AboutSeo.vue scoped styles) */
.about-block {
    margin-top: 36px;
    padding-left: 20px;
    border-left: 2px solid hsl(var(--accent));
}
.about-block h3 {
    font-size: clamp(24px, 3.4vw, 34px);
    line-height: 1.2;
    margin-bottom: 14px;
    color: hsl(var(--fg));
}
.about-block p {
    font-size: 15px;
    line-height: 1.85;
    color: hsl(var(--fg-muted));
    word-break: break-word;
}
@media (max-width: 640px) {
    .about-block {
        padding-left: 14px;
    }
    .about-block p {
        font-size: 14px;
        line-height: 1.8;
    }
}
```

- [ ] **Step 8: Verify components compile**

Run: `php artisan view:cache && php artisan view:clear`
Expected: compiles with no Blade errors (real render in Task 6).

- [ ] **Step 9: Commit**

```bash
git add resources/views/components/section-header.blade.php resources/views/components/poster-card.blade.php resources/views/components/card-grid.blade.php resources/views/components/rail.blade.php resources/views/components/hero.blade.php resources/views/components/about-seo.blade.php resources/css/app.css
git commit -m "feat(blade): add static content components (card/section/rail/hero/about-seo)

Ports the AHD card + section + hero + SEO-copy components to Blade, reusing
existing CSS classes, minus decorative JS motion (static: no tilt/parallax/
carousel). Hero is a single preloaded LCP image.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 6: Index page + controller conversion + feature test

**Files:**
- Create: `resources/views/index.blade.php`
- Modify: `app/Http/Controllers/IndexController.php` (render call only)
- Test: `tests/Feature/IndexPageTest.php`

**Interfaces:**
- Consumes: `x-hero`, `x-section-header`, `x-rail`, `x-card-grid`, `x-poster-card`, `x-about-seo`, `CardPresenter`, `pagination.ahd`.
- Produces: the home page at `/` rendering server HTML through `layouts.app`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/IndexPageTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndexPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // IndexController caches paginators in the array store, which persists
        // across tests in one process — flush so each test sees fresh DB rows.
        Cache::flush();
    }

    public function test_home_renders_server_side_blade_with_anime_and_seo(): void
    {
        DB::table('yu_anime_catagory')->insert([
            'cat_title' => 'Cowboy Bebop Test',
            'cat_type' => 1,
            'cat_update' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('index');
        // Real server-rendered content — the anime title is in the raw HTML.
        $response->assertSee('Cowboy Bebop Test', false);
        // SEO from layout + page: canonical + global WebSite JSON-LD.
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('application/ld+json', false);
        // Not an Inertia shell.
        $response->assertDontSee('id="app" data-page', false);
    }

    public function test_home_renders_with_empty_database(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewIs('index');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=IndexPageTest`
Expected: FAIL — the controller still returns an Inertia response (`assertViewIs('index')` fails / component is 'Index').

- [ ] **Step 3: Convert IndexController render call**

In `app/Http/Controllers/IndexController.php`:
- Remove `use Inertia\Inertia;`.
- Replace the final return:
```php
        return Inertia::render('Index', [
            'anime' => $anime,
            'recommended' => $recommended,
            'popular' => $popular,
        ]);
```
with:
```php
        return view('index', [
            'anime' => $anime,
            'recommended' => $recommended,
            'popular' => $popular,
        ]);
```
Leave the data-building (cache, `getFeatured`, transforms) exactly as-is.

- [ ] **Step 4: Create the Index view**

Create `resources/views/index.blade.php`:
```blade
@extends('layouts.app')

@section('title', 'ดูอนิเมะออนไลน์ ซับไทย พากย์ไทย เดอะมูฟวี่ HD')
@section('description', 'ดูอนิเมะออนไลน์ฟรี รวมอนิเมะใหม่ล่าสุด ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD ดูง่าย ลื่นไหล อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero')

@php
    use App\Support\CardPresenter;

    // Hero source mirrors Index.vue: >=3 recommended → recommended, else latest.
    $heroSource = (! empty($recommended) && count($recommended) >= 3) ? $recommended : $anime->items();
    $heroItems = CardPresenter::collection(array_slice($heroSource, 0, 5));
    $popularItems = CardPresenter::collection(! empty($popular) ? $popular : array_slice($anime->items(), 0, 10));
    $latestItems = CardPresenter::collection($anime->items());
@endphp

@if (! empty($heroItems))
    @push('head')
        @php $hero0 = $heroItems[0]; $heroPreload = \App\Support\Img::url($hero0['landscape'], ['width' => 1600, 'format' => 'webp']) ?? $hero0['landscape']; @endphp
        <link rel="preload" as="image" href="{{ $heroPreload }}" fetchpriority="high">
    @endpush
@endif

@section('content')
    {{-- SEO-hidden keyword-rich H1 (visually hidden, readable by crawlers). --}}
    <h1 style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;">ดูอนิเมะออนไลน์ ซับไทย พากย์ไทย เดอะมูฟวี่ HD - Anime HD Zero</h1>

    @if (! empty($heroItems))
        <x-hero :item="$heroItems[0]" eager />
    @endif

    @if (! empty($popularItems))
        <section class="mx-auto mt-20 max-w-[1440px] px-6 lg:px-10">
            <x-section-header eyebrow="กำลังมาแรง" title="ยอดนิยม" />
            <x-rail :items="$popularItems" />
        </section>
    @endif

    <section class="mx-auto mt-24 max-w-[1440px] px-6 lg:px-10">
        <x-section-header eyebrow="อัปเดตล่าสุด" title="ตอนใหม่ล่าสุด" />
        <x-card-grid>
            @foreach ($latestItems as $item)
                <x-poster-card :item="$item" />
            @endforeach
        </x-card-grid>
        {{ $anime->links('pagination.ahd') }}
    </section>

    <x-about-seo />
@endsection
```
Note on hero image: the `x-hero` component fetches its own `landscape` at width 1600 with `eager`, and the `@push('head')` preload targets the identical URL so the preload matches the eventual `<img src>` (no double fetch).

- [ ] **Step 5: Run the feature test to verify it passes**

Run: `vendor/bin/phpunit --filter=IndexPageTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full suite (no regression)**

Run: `vendor/bin/phpunit`
Expected: all PASS — no Inertia test relied on the Index Inertia component (Index had no dedicated Inertia assertion test).

- [ ] **Step 7: Lint/format the PHP touched**

Run: `./vendor/bin/pint app/Support app/Http/Controllers/IndexController.php`
Expected: clean.

- [ ] **Step 8: Commit**

```bash
git add resources/views/index.blade.php app/Http/Controllers/IndexController.php tests/Feature/IndexPageTest.php
git commit -m "feat(blade): render home page via Blade SSR (IndexController -> view)

Converts the home page from Inertia to server-rendered Blade using the new
presenters + content components. Preloads the single hero image as LCP.
Inertia pipeline otherwise untouched.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

## Self-Review

**1. Spec coverage (2a items delivered here):** CardPresenter ✓ (T1), Bunny image helper ✓ (T2), Schema builders + JSON-LD render ✓ (T3), themed pagination ✓ (T4), static card/section/rail/hero/about-seo components ✓ (T5), Index page + controller conversion ✓ (T6). **Deferred to 2a-2 (noted):** interactive site chrome — `x-site-header` (mobile drawer/user menu/theme toggle), `x-search-overlay` (⌘K), real `x-site-footer`, `x-ads-navbar`, `x-ads-floating`. Index renders through the Phase-1 stub header/footer meanwhile; its content + SEO are fully correct. This split right-sizes the plan (Alpine-heavy chrome gets its own review), same approach as Phase 1.

**2. Placeholder scan:** One deliberate "copy verbatim" instruction in T5 Step 6 (about-seo Thai copy) — the source file + exact line range are named; this is a transcription directive, not a vague placeholder, because reproducing ~40 lines of marketing Thai prose inline would be error-prone to retype and the source is authoritative. Every code step shows complete code. No TBD/TODO.

**3. Type/name consistency:** `CardPresenter::make`/`collection` (T1) used in T5 poster-card/rail/hero and T6 index. `Img::url`/`srcset` + constants (T2) used in T5 poster-card/hero and T6 preload. `Schema::*` (T3) not consumed until 2b/2c/2d (home needs no page schema — WebSite/Org are global from Phase 1); built now as foundation. `pagination.ahd` (T4) used in T6. `x-*` component names match between T5 definitions and T6 usage. Card array keys (`id,title,poster,landscape,tag,ep,kanji,genre,href,cat_type`) are consistent across presenter and all consumers.

**4. Non-breaking:** Only `IndexController` return changes on the Inertia side; `resources/js/*`, Inertia root, SSR files untouched. Full-suite re-run in T6 Step 6 guards regressions. Array-cache cross-test leak handled via `Cache::flush()` in the Index test setUp.

**5. Risk note carried into build:** `$anime` is a cached `LengthAwarePaginator`; `->items()` and `->links('pagination.ahd')` must work on the cached instance. If `->links()` errors on the cached object, the fix is to call `->withPath('/')` or regenerate — flag if the Index test surfaces it.
