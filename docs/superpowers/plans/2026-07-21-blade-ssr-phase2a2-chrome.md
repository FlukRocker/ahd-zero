# Blade SSR Phase 2a-2 — Interactive Site Chrome — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Phase-1 stub header/footer with the real interactive site chrome — sticky header (scroll-glass, theme toggle, primary nav, search trigger, mobile drawer, member user-menu), ⌘K search overlay, real footer, and the sponsored-links navbar — all as Blade components driven by Alpine.js.

**Architecture:** Ports `FrontLayout.vue` + `SiteHeader.vue` + `SearchOverlay.vue` + `Footer.vue` + `AdsNavbar.vue` to Blade. Interactivity that was Vue refs becomes Alpine: a layout-level `x-data="{ searchOpen }"` scope (with global ⌘K keydown) shared by the header trigger and the overlay; header-local `x-data` for scroll-glass, mobile drawer, and the member dropdown (click-outside). Theme toggle uses an enhanced Phase-1 `appearance` Alpine store (adds a reactive `isDark`). Active nav state is computed server-side. Additive/non-breaking: Inertia untouched; only the Blade `layouts/app.blade.php` + new Blade components change.

**Tech Stack:** Laravel 12, Blade components, Alpine.js 3, Tailwind v4, existing AHD tokens.

## Global Constraints

- Laravel 12 / PHP 8.4; pnpm; tests on in-memory sqlite (`vendor/bin/phpunit`).
- **Additive/non-breaking:** do NOT touch `resources/js/app.ts`, `ssr.ts`, `resources/views/app.blade.php` (Inertia root), `ecosystem.config.cjs`, `config/inertia.php`, `HandleInertiaRequests`, or any `resources/js/pages|components|layouts/*.vue`. Only the Blade `layouts/app.blade.php`, new Blade components, `resources/js/blade.js` (our Blade Alpine entry — enhance the appearance store), and `resources/css/app.css` (append `.ads-navbar`) change.
- **Reuse existing CSS classes** in `resources/css/app.css`: `glass`, `btn`, `btn-ghost`, `u-grow`, `font-display`, `font-mono`, `.fade` (transition). `ads-navbar` is NOT in app.css yet — port it from `AdsNavbar.vue`'s scoped `<style>` (Task 2).
- **Design tokens** `hsl(var(--token))`: `--bg`, `--bg-soft`, `--bg-elev`, `--fg`, `--fg-muted`, `--fg-faint`, `--accent`, `--accent-fg`, `--border-ahd`, `--border-strong`.
- **Data:** header reads `$memberAuth` (from the Phase-1 `GlobalComposer` — an array `{id,name,email,avatar}` when a member is logged in, else `null`). Navbar reads `$navbarAds` (list of `{href,alt,rel}`). These are already shared on every view.
- Nav items (verbatim, from `SiteHeader.vue`): `หน้าแรก`→`/`, `ซับไทย`→`/category/1`, `พากย์ไทย`→`/category/2`, `เดอะมูฟวี่`→`/category/3`, `สตูดิโอ`→`/studios`, `แจ้งปัญหา`→`https://www.facebook.com/animehdzeroo.v2` (external, new tab).
- Logout: `<form method="POST" action="/member/logout">` with `@csrf` (route `member.logout`).
- Search submit: native `<form method="GET" action="{{ route('search.results') }}">` with input `name="q"` (route `/search/results`).
- **Deferred to 2c/2d:** `AdsFloating` (only has data on Anime/Episode pages) — not built here.
- Never migrate `yu_anime_*` tables.

## File Structure

- `resources/views/components/icon.blade.php` — inline-SVG icon (subset: search, close, menu).
- `resources/views/components/ads-navbar.blade.php` — sponsored links bar (SSR).
- `resources/views/components/site-footer.blade.php` — real footer.
- `resources/views/components/site-header.blade.php` — sticky interactive header.
- `resources/views/components/search-overlay.blade.php` — ⌘K search modal.
- `resources/js/blade.js` — enhance the `appearance` Alpine store with a reactive `isDark`.
- `resources/css/app.css` — append `.ads-navbar` styles.
- `resources/views/layouts/app.blade.php` — swap stub includes for the real chrome + layout-level search scope.
- Delete: `resources/views/partials/header.blade.php`, `resources/views/partials/footer.blade.php` (Phase-1 stubs, now unused).
- Test: `tests/Feature/SiteChromeTest.php`.

---

### Task 1: Icon component

**Files:**
- Create: `resources/views/components/icon.blade.php`

**Interfaces:**
- Produces: `<x-icon name="search|close|menu" :size="18" />` → inline SVG.

- [ ] **Step 1: Create the component** (SVG paths copied from `AhdIcon.vue`'s lookup table)

Create `resources/views/components/icon.blade.php`:
```blade
@props(['name', 'size' => 20])
@php
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" fill="none"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    ];
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" style="display:inline-block;vertical-align:middle" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>
```
(`{!! !!}` renders a trusted internal constant — no user input, safe.)

- [ ] **Step 2: Verify it compiles**

Run: `php artisan view:clear`
Expected: no error.

- [ ] **Step 3: Commit**

```bash
git add resources/views/components/icon.blade.php
git commit -m "feat(blade): add x-icon inline-SVG component (search/close/menu)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 2: Ads-navbar component + CSS

**Files:**
- Create: `resources/views/components/ads-navbar.blade.php`
- Modify: `resources/css/app.css` (append `.ads-navbar` styles)

**Interfaces:**
- Consumes: `$navbarAds` (list of `{href,alt,rel}` from the global composer).
- Produces: `<x-ads-navbar />`.

- [ ] **Step 1: Create the component** (ports `AdsNavbar.vue` template; `$navbarAds` replaces `page.props.ads.navbar`)

Create `resources/views/components/ads-navbar.blade.php`:
```blade
@php $items = $navbarAds ?? []; @endphp
@if (! empty($items))
    <div class="ads-navbar" role="navigation" aria-label="Sponsored links">
        <div class="mx-auto flex max-w-[1440px] flex-wrap items-center gap-x-5 gap-y-1 px-6 py-2 lg:px-10">
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase opacity-70">Sponsored</span>
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-1">
                @foreach ($items as $it)
                    <li>
                        <a href="{{ $it['href'] }}" rel="{{ $it['rel'] ?: 'nofollow noopener sponsored noreferrer ugc' }}" target="_blank" class="text-[12px] font-medium hover:underline">{{ $it['alt'] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
```

- [ ] **Step 2: Append `.ads-navbar` CSS** (ported from `AdsNavbar.vue` scoped styles)

Append to `resources/css/app.css`:
```css
/* Ads navbar (ported from AdsNavbar.vue scoped styles) */
.ads-navbar {
    background: hsl(var(--bg-soft));
    border-bottom: 1px solid hsl(var(--border-ahd));
    color: hsl(var(--fg-muted));
    font-size: 12px;
}
.ads-navbar a {
    color: hsl(var(--fg));
}
```

- [ ] **Step 3: Verify + commit**

Run: `php artisan view:clear`
```bash
git add resources/views/components/ads-navbar.blade.php resources/css/app.css
git commit -m "feat(blade): add x-ads-navbar sponsored-links bar + CSS

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 3: Site footer component

**Files:**
- Create: `resources/views/components/site-footer.blade.php`

**Interfaces:**
- Produces: `<x-site-footer />`.

- [ ] **Step 1: Create the component** (ports `Footer.vue`; `Link`→`<a>`, `year`→`date('Y')`)

Create `resources/views/components/site-footer.blade.php`:
```blade
<footer class="relative mt-24 overflow-hidden" style="border-top: 1px solid hsl(var(--border-ahd))">
    <div class="mx-auto max-w-[1440px] px-6 py-14 lg:px-10">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-5">
            <div class="col-span-2">
                <div class="mb-4 flex items-center gap-2">
                    <div class="relative flex h-10 w-10 items-center justify-center rounded-lg" style="background: linear-gradient(135deg, hsl(var(--accent)), hsl(var(--accent) / 0.6));">
                        <span class="font-display text-2xl leading-none text-white italic" style="transform: translateY(-1px)">z</span>
                    </div>
                    <div class="font-display text-[28px] leading-none italic">Anime HD Zero</div>
                </div>
                <p class="max-w-sm text-[14px]" style="color: hsl(var(--fg-muted))">ดูอนิเมะออนไลน์ ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD รับชมได้ทุกเรื่อง</p>
            </div>
            <div>
                <div class="mb-3 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">รับชม</div>
                <ul class="space-y-2 text-[14px]">
                    <li><a href="/category/1" class="u-grow">ซับไทย</a></li>
                    <li><a href="/category/2" class="u-grow">พากย์ไทย</a></li>
                    <li><a href="/category/3" class="u-grow">เดอะมูฟวี่</a></li>
                </ul>
            </div>
            <div>
                <div class="mb-3 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">เรียกดู</div>
                <ul class="space-y-2 text-[14px]">
                    <li><a href="/studios" class="u-grow">สตูดิโอ</a></li>
                    <li><a href="/voice-actors" class="u-grow">นักพากย์</a></li>
                    <li><a href="/staff" class="u-grow">ทีมงาน</a></li>
                </ul>
            </div>
            <div>
                <div class="mb-3 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">บัญชี</div>
                <ul class="space-y-2 text-[14px]">
                    <li><a href="/member/login" class="u-grow">เข้าสู่ระบบ</a></li>
                    <li><a href="/member/settings/profile" class="u-grow">ตั้งค่า</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-12 flex flex-col items-center justify-between gap-4 pt-6 md:flex-row" style="border-top: 1px solid hsl(var(--border-ahd))">
            <div class="font-mono text-[11px]" style="color: hsl(var(--fg-faint))">© {{ date('Y') }} Anime HD Zero</div>
            <div class="flex gap-4 font-mono text-[12px]" style="color: hsl(var(--fg-faint))">
                <a href="#">ข้อตกลง</a>
                <a href="#">ความเป็นส่วนตัว</a>
                <a href="#">DMCA</a>
            </div>
        </div>
    </div>
</footer>
```

- [ ] **Step 2: Verify + commit**

Run: `php artisan view:clear`
```bash
git add resources/views/components/site-footer.blade.php
git commit -m "feat(blade): add x-site-footer (ports Footer.vue)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 4: Enhance appearance Alpine store (reactive isDark)

**Files:**
- Modify: `resources/js/blade.js` (the `Alpine.store('appearance', …)` block only)

**Interfaces:**
- Produces: `$store.appearance.isDark` (reactive boolean) and `$store.appearance.toggle()`. The header (Task 5) binds the theme icon to `isDark` so it flips on toggle.

**Consumes:** the Phase-1 FOUC `<head>` script already applied `data-theme` + `.dark` before paint; this store reads that initial state and keeps `isDark` in sync.

- [ ] **Step 1: Replace the appearance store**

In `resources/js/blade.js`, replace the entire existing `Alpine.store('appearance', { … })` block with:
```js
// Appearance store. The <head> FOUC script (layouts/app.blade.php) already
// applied data-theme / .dark before paint from localStorage; this store reads
// that initial state into a reactive `isDark` and keeps it in sync so header
// controls (theme toggle icon) update reactively.
Alpine.store('appearance', {
    isDark: false,
    init() {
        this.isDark = document.documentElement.classList.contains('dark');
    },
    apply(theme) {
        const resolved =
            theme === 'system'
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light'
                : theme;
        document.documentElement.setAttribute('data-theme', resolved);
        document.documentElement.classList.toggle('dark', resolved === 'dark');
        this.isDark = resolved === 'dark';
        try {
            localStorage.setItem('appearance', theme);
        } catch {
            /* ignore — private mode / storage disabled */
        }
    },
    toggle() {
        this.apply(this.isDark ? 'light' : 'dark');
    },
});
```
(Leave the rest of `blade.js` — the `$reveal` magic, imports, `Alpine.start()` — unchanged. Alpine calls a store's `init()` automatically on start.)

- [ ] **Step 2: Build to verify the entry still compiles**

Run: `pnpm build`
Expected: build succeeds; `grep -c "resources/js/blade.js" public/build/manifest.json` ≥ 1.

- [ ] **Step 3: Commit**

```bash
git add resources/js/blade.js
git commit -m "feat(blade): reactive isDark on appearance store for header theme toggle

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 5: Site header component (Alpine-interactive)

**Files:**
- Create: `resources/views/components/site-header.blade.php`

**Interfaces:**
- Consumes: `$memberAuth` (array-or-null), `<x-icon>` (Task 1), `$store.appearance` (Task 4), the layout-level `searchOpen` (Task 7).
- Produces: `<x-site-header />` — sticky header with functional theme toggle, mobile drawer, member dropdown, and search triggers that set the parent `searchOpen = true`.

- [ ] **Step 1: Create the header** (ports `SiteHeader.vue`; refs→Alpine, `Link`→`<a>`, active nav server-side)

Create `resources/views/components/site-header.blade.php`:
```blade
@php
    $nav = [
        ['label' => 'หน้าแรก', 'href' => '/'],
        ['label' => 'ซับไทย', 'href' => '/category/1'],
        ['label' => 'พากย์ไทย', 'href' => '/category/2'],
        ['label' => 'เดอะมูฟวี่', 'href' => '/category/3'],
        ['label' => 'สตูดิโอ', 'href' => '/studios'],
        ['label' => 'แจ้งปัญหา', 'href' => 'https://www.facebook.com/animehdzeroo.v2'],
    ];
    $cur = request()->getPathInfo(); // '/', '/category/1', ...
    $isActive = fn (string $href): bool => $href === '/' ? $cur === '/' : str_starts_with($cur, $href);
@endphp

<header
    x-data="{ scrolled: false, mobileNavOpen: false, userMenuOpen: false }"
    x-init="scrolled = window.scrollY > 20"
    @scroll.window.passive="scrolled = window.scrollY > 20"
    class="sticky top-0 z-50 transition-all"
    :class="scrolled ? 'glass' : 'bg-transparent'"
    :style="scrolled ? 'border-bottom: 1px solid hsl(var(--border-ahd));' : 'border-bottom: 1px solid transparent;'"
>
    <div class="mx-auto flex h-[68px] max-w-[1440px] items-center gap-8 px-6 lg:px-10">
        <a href="/" class="flex shrink-0 items-center gap-2">
            <div class="relative flex h-8 w-8 items-center justify-center rounded-lg" style="background: linear-gradient(135deg, hsl(var(--accent)), hsl(var(--accent) / 0.6));">
                <span class="font-display text-xl leading-none text-white italic" style="transform: translateY(-1px)">z</span>
            </div>
            <div class="leading-tight">
                <div class="font-display text-[22px] leading-none italic">Zero</div>
                <div class="font-mono text-[9px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-muted))">anime · hd</div>
            </div>
        </a>

        <nav class="hidden items-center gap-6 text-[14px] lg:flex">
            @foreach ($nav as $n)
                @if (str_starts_with($n['href'], 'http'))
                    <a href="{{ $n['href'] }}" target="_blank" rel="noopener noreferrer" class="u-grow py-1" style="color: hsl(var(--fg-muted))">{{ $n['label'] }}</a>
                @else
                    <a href="{{ $n['href'] }}" class="u-grow py-1" style="{{ $isActive($n['href']) ? 'color: hsl(var(--fg)); font-weight: 500;' : 'color: hsl(var(--fg-muted));' }}">{{ $n['label'] }}</a>
                @endif
            @endforeach
        </nav>

        <div class="hidden flex-1 justify-center md:flex">
            <button type="button" class="flex w-[340px] items-center gap-3 rounded-full px-4 py-2" style="background: hsl(var(--bg-soft)); border: 1px solid hsl(var(--border-ahd));" @click="searchOpen = true">
                <x-icon name="search" :size="16" />
                <span class="text-[13px]" style="color: hsl(var(--fg-faint))">ค้นหาอนิเมะ สตูดิโอ นักพากย์…</span>
                <span class="ml-auto rounded px-1.5 py-0.5 font-mono text-[10px]" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));">⌘K</span>
            </button>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full" style="background: hsl(var(--bg-soft))" title="สลับธีม" aria-label="สลับธีมสว่าง/มืด" @click="$store.appearance.toggle()">
                <span x-text="$store.appearance.isDark ? '☼' : '☾'" aria-hidden="true">☾</span>
            </button>
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full lg:hidden" style="background: hsl(var(--bg-soft))" aria-label="เมนู" :aria-expanded="mobileNavOpen" @click="mobileNavOpen = !mobileNavOpen">
                <template x-if="!mobileNavOpen"><x-icon name="menu" :size="18" /></template>
                <template x-if="mobileNavOpen"><x-icon name="close" :size="18" /></template>
            </button>
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full md:hidden" style="background: hsl(var(--bg-soft))" aria-label="ค้นหา" @click="searchOpen = true">
                <x-icon name="search" :size="18" />
            </button>

            @if ($memberAuth)
                <div class="relative" @click.outside="userMenuOpen = false">
                    <button type="button" class="flex items-center gap-2 rounded-full py-1 pr-3 pl-1" style="background: hsl(var(--bg-soft))" aria-label="เมนูผู้ใช้" :aria-expanded="userMenuOpen" @click="userMenuOpen = !userMenuOpen">
                        {{-- Whole ternary result is escaped once by {{ }} (ENT_QUOTES); do NOT also e() the URL or a '&' in query params double-encodes. --}}
                        <div class="font-display flex h-8 w-8 items-center justify-center rounded-full bg-cover italic" style="{{ $memberAuth['avatar'] ? "background-image: url('".$memberAuth['avatar']."');" : 'background: hsl(var(--accent)); color: hsl(var(--accent-fg));' }}">
                            @unless ($memberAuth['avatar'])<span>{{ mb_substr($memberAuth['name'], 0, 1) }}</span>@endunless
                        </div>
                        <span class="hidden text-[13px] md:inline">{{ explode(' ', $memberAuth['name'])[0] }}</span>
                    </button>
                    <div x-show="userMenuOpen" x-transition x-cloak class="absolute right-0 top-full mt-2 w-52 overflow-hidden rounded-xl" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-strong)); box-shadow: 0 12px 32px rgba(0,0,0,0.18); z-index: 60;">
                        <div class="border-b px-4 py-3" style="border-color: hsl(var(--border-ahd))">
                            <div class="font-medium text-[13px] truncate" style="color: hsl(var(--fg))">{{ $memberAuth['name'] }}</div>
                            <div class="font-mono text-[11px] truncate" style="color: hsl(var(--fg-muted))">{{ $memberAuth['email'] }}</div>
                        </div>
                        <a href="/member/settings/profile" class="block px-4 py-2 text-[13px]" style="color: hsl(var(--fg))">ตั้งค่าโปรไฟล์</a>
                        <a href="/member/settings/password" class="block px-4 py-2 text-[13px]" style="color: hsl(var(--fg))">เปลี่ยนรหัสผ่าน</a>
                        <form method="POST" action="/member/logout">
                            @csrf
                            <button type="submit" class="block w-full border-t px-4 py-2 text-left text-[13px]" style="color: hsl(var(--accent)); border-color: hsl(var(--border-ahd));">ออกจากระบบ</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="/member/login" class="btn btn-ghost hidden px-3 py-2 text-[13px] md:inline-flex">เข้าสู่ระบบ</a>
            @endif
        </div>
    </div>

    {{-- Mobile nav drawer --}}
    <div x-show="mobileNavOpen" x-transition x-cloak class="border-t lg:hidden" style="background: hsl(var(--bg)); border-color: hsl(var(--border-ahd));">
        <nav class="mx-auto flex max-w-[1440px] flex-col gap-1 px-6 py-3 lg:px-10">
            @foreach ($nav as $n)
                @if (str_starts_with($n['href'], 'http'))
                    <a href="{{ $n['href'] }}" target="_blank" rel="noopener noreferrer" class="rounded-lg px-3 py-2 text-[14px]" style="color: hsl(var(--fg-muted))" @click="mobileNavOpen = false">{{ $n['label'] }}</a>
                @else
                    <a href="{{ $n['href'] }}" class="rounded-lg px-3 py-2 text-[14px]" style="{{ $isActive($n['href']) ? 'background: hsl(var(--bg-soft)); color: hsl(var(--fg)); font-weight: 500;' : 'color: hsl(var(--fg-muted));' }}" @click="mobileNavOpen = false">{{ $n['label'] }}</a>
                @endif
            @endforeach
            @unless ($memberAuth)
                <a href="/member/login" class="mt-2 rounded-lg px-3 py-2 text-[14px] font-medium" style="background: hsl(var(--accent)); color: hsl(var(--accent-fg));" @click="mobileNavOpen = false">เข้าสู่ระบบ</a>
            @endunless
        </nav>
    </div>
</header>
```

- [ ] **Step 2: Add `x-cloak` CSS if missing** (prevents dropdown/drawer flashing before Alpine init)

Check `resources/css/app.css` for an `[x-cloak]` rule. If absent, append:
```css
[x-cloak] { display: none !important; }
```
(If already present, skip.)

- [ ] **Step 3: Verify + commit**

Run: `php artisan view:cache && php artisan view:clear`
Expected: compiles with no Blade errors.
```bash
git add resources/views/components/site-header.blade.php resources/css/app.css
git commit -m "feat(blade): add interactive x-site-header (ports SiteHeader.vue to Alpine)

Sticky header with scroll-glass, reactive theme toggle, primary nav (active
state server-side), mobile drawer, member dropdown (click-outside), and
search triggers wired to the layout searchOpen scope.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 6: Search overlay component

**Files:**
- Create: `resources/views/components/search-overlay.blade.php`

**Interfaces:**
- Consumes: the layout-level `searchOpen` (Task 7); route `search.results`.
- Produces: `<x-search-overlay />` — a modal bound to `searchOpen`, native GET form submit.

- [ ] **Step 1: Create the overlay** (ports `SearchOverlay.vue`; Vue Transition→Alpine `x-show`/`x-transition`, native form submit)

Create `resources/views/components/search-overlay.blade.php`:
```blade
<div
    x-show="searchOpen"
    x-cloak
    x-transition.opacity
    @keydown.escape.window="searchOpen = false"
    @click.self="searchOpen = false"
    class="fixed inset-0 z-[80] flex items-start justify-center px-6 pt-[12vh]"
    style="background: hsl(var(--bg) / 0.6); backdrop-filter: blur(12px);"
>
    <div class="glass w-full max-w-2xl rounded-3xl p-4">
        <form method="GET" action="{{ route('search.results') }}" class="flex items-center gap-3 px-4 py-3">
            <x-icon name="search" :size="18" />
            <input
                x-ref="searchInput"
                x-effect="if (searchOpen) $nextTick(() => $refs.searchInput.focus())"
                type="text"
                name="q"
                placeholder="ค้นหาอนิเมะ หมวดหมู่ สตูดิโอ…"
                class="flex-1 bg-transparent text-[15px] outline-none"
                autocomplete="off"
            >
            <span class="rounded px-1.5 py-0.5 font-mono text-[10px]" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));">↵</span>
            <button type="button" class="ml-2 opacity-60 hover:opacity-100" aria-label="ปิดค้นหา" @click="searchOpen = false">
                <x-icon name="close" :size="18" />
            </button>
        </form>
        <div class="px-4 pb-3 font-mono text-[12px]" style="color: hsl(var(--fg-faint))">กด Enter เพื่อค้นหา · Esc เพื่อปิด</div>
    </div>
</div>
```

- [ ] **Step 2: Verify + commit**

Run: `php artisan view:clear`
```bash
git add resources/views/components/search-overlay.blade.php
git commit -m "feat(blade): add x-search-overlay (ports SearchOverlay.vue to Alpine)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

### Task 7: Wire chrome into the layout + delete stubs + feature test

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (body region only)
- Delete: `resources/views/partials/header.blade.php`, `resources/views/partials/footer.blade.php`
- Test: `tests/Feature/SiteChromeTest.php`

**Interfaces:**
- Produces: the real chrome on every page; the `searchOpen` Alpine scope + global ⌘K binding shared by header + overlay.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/SiteChromeTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SiteChromeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_guest_header_and_footer_render(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // Primary nav (server-rendered).
        $response->assertSee('ซับไทย', false);
        $response->assertSee('เดอะมูฟวี่', false);
        // Search overlay form points at the results route.
        $response->assertSee('action="'.route('search.results').'"', false);
        // Theme toggle present.
        $response->assertSee('สลับธีมสว่าง/มืด', false);
        // Guest sees a login link, not a logout form.
        $response->assertSee('/member/login', false);
        $response->assertDontSee('action="/member/logout"', false);
        // Footer.
        $response->assertSee('© '.date('Y').' Anime HD Zero', false);
    }

    public function test_member_header_shows_user_menu_and_logout(): void
    {
        $member = Member::create([
            'name' => 'Rin Tester',
            'email' => 'rin-chrome@example.test',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->actingAs($member, 'member')->get('/');

        $response->assertOk();
        // First name in the user-menu trigger.
        $response->assertSee('Rin', false);
        // Logout form present.
        $response->assertSee('action="/member/logout"', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=SiteChromeTest`
Expected: FAIL — the stub header has none of these (`ซับไทย`, search form, logout).

- [ ] **Step 3: Update the layout body**

In `resources/views/layouts/app.blade.php`, replace the current `<body>…</body>` region:
```blade
<body class="min-h-screen bg-[hsl(var(--bg))] font-sans text-[hsl(var(--fg))] antialiased">
    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>
```
with:
```blade
<body class="min-h-screen bg-[hsl(var(--bg))] font-sans text-[hsl(var(--fg))] antialiased">
    <div
        class="flex min-h-screen flex-col"
        x-data="{ searchOpen: false }"
        @keydown.window.meta.k.prevent="searchOpen = true"
        @keydown.window.ctrl.k.prevent="searchOpen = true"
    >
        <x-ads-navbar />
        <x-site-header />

        <main class="flex-1">
            @yield('content')
        </main>

        <x-site-footer />
        <x-search-overlay />
    </div>

    @stack('scripts')
</body>
```

- [ ] **Step 4: Delete the Phase-1 stubs**

```bash
git rm resources/views/partials/header.blade.php resources/views/partials/footer.blade.php
```
(Nothing else references them — the layout no longer includes them, and error pages extend `layouts.app` directly.)

- [ ] **Step 5: Run the feature test to verify it passes**

Run: `vendor/bin/phpunit --filter=SiteChromeTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full suite (no regression)**

Run: `vendor/bin/phpunit`
Expected: all PASS — Index/Error page tests still green (they render through the updated layout).

- [ ] **Step 7: Build assets (verify Alpine store change integrates)**

Run: `pnpm build`
Expected: build succeeds.

- [ ] **Step 8: Commit**

```bash
git add resources/views/layouts/app.blade.php tests/Feature/SiteChromeTest.php
git commit -m "feat(blade): wire real site chrome into layout, drop Phase-1 stubs

Layout now renders x-ads-navbar / x-site-header / x-site-footer /
x-search-overlay in a shared Alpine searchOpen scope with global ⌘K. Deletes
the Phase-1 header/footer stubs. SiteChromeTest covers guest + member states.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HhqmoGkzisv4SWiRMgasMf"
```

---

## Self-Review

**1. Spec coverage (Phase 2a-2 chrome):** `x-site-header` (scroll-glass, theme toggle, nav, mobile drawer, user menu, search trigger) ✓ T5; `x-search-overlay` (⌘K, Escape, click-outside, autofocus, native submit) ✓ T6 + layout ⌘K T7; `x-site-footer` ✓ T3; `x-ads-navbar` ✓ T2; `x-icon` ✓ T1; appearance store reactive toggle ✓ T4; layout wiring + stub removal ✓ T7. **Deferred (noted):** `AdsFloating` → 2c/2d (no data on chrome-only pages).

**2. Placeholder scan:** No TBD/vague steps; every component has complete code copied from the named Vue source with the Inertia bits translated (`Link`→`<a>`, refs→Alpine, `usePage`→`$memberAuth`). Nav labels/hrefs, footer copy, and icon SVG paths are verbatim.

**3. Type/name consistency:** `searchOpen` is defined once in the layout `x-data` (T7) and referenced by the header triggers (T5) and overlay (T6) — Alpine nested scopes resolve it up-tree. `$store.appearance.isDark`/`toggle()` defined in T4, consumed in T5. `<x-icon>` (T1) used in T5/T6. `$memberAuth` (Phase-1 composer) drives header + test (T5/T7). `route('search.results')` used in overlay (T6) + asserted in test (T7).

**4. Non-breaking:** Only Blade layout + new components + `blade.js` store (our Blade entry) + `app.css` append change. No `resources/js/pages|components|layouts/*.vue`, no Inertia root/config/SSR. Full-suite re-run (T7 S6) guards regression; error pages + Index render through the updated layout and must stay green. Array-cache leak handled via `Cache::flush()` in the test setUp.

**5. Risk notes carried into build:**
- Alpine nested-scope write: header buttons set the parent `searchOpen`. If Alpine can't resolve it (scope isolation), fall back to an `Alpine.store('search',{open:false})` in `blade.js`. Flag if the overlay doesn't open in a manual check.
- `x-cloak` must hide the dropdown/drawer/overlay pre-init (T5 S2 adds the rule if missing) — otherwise they flash open on load.
- Theme icon reactivity depends on the store `isDark` (T4); a plain DOM-getter would not update `x-text`.
- Member acting-as uses the `member` guard (`actingAs($member,'member')`) — matches `routes/member.php`.
