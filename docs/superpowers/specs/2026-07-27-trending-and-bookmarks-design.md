# Trending Rail + Member Bookmarks — Design

**Date:** 2026-07-27
**Status:** Approved

## Problem

Two gaps in the public Blade site:

1. **Trending is invisible to users.** `AnalyticsService::getTrendingAnime()` already aggregates real
   traffic from the Mongo `page_views` collection, but only the admin dashboard consumes it. The
   homepage shows two hand-curated rails (`recommended`, `popular`) from `featured_anime`, so what is
   actually popular never surfaces to visitors.
2. **Members cannot save anything.** The `member` guard supports registration, login, email
   verification, settings, and commenting — but a member has no way to keep a list of anime to return
   to.

## Part A — Trending Rail

### Scope

A "trending now" rail on the homepage (`resources/views/index.blade.php`), driven by real page-view
analytics. Anime only. No new rails on category/anime/episode pages.

### Approach

Add `AnalyticsService::getTrendingCards(int $days = 7, int $limit = 12): array`.

`DashboardController::index` (lines 42–50) already performs exactly this hydration inline: take the
`{cat_id, views}` rows from `getTrendingAnime()`, load the matching `Anime` rows, and re-attach the
view counts. Putting that in the service gives one implementation for both callers instead of a
second copy in `IndexController`.

The method:

1. Calls the existing `getTrendingAnime($days, $limit)`.
2. Loads matching anime with the same column list `getFeatured()` uses, so the output array shape is
   identical and `<x-rail>` / `<x-poster-card>` need no changes.
3. **Re-sorts to the Mongo view order.** `whereIn('cat_id', ...)` returns rows in database order, not
   view-count order — without an explicit re-sort the rail is not ranked by popularity.
4. Drops ids that no longer resolve to an anime row (deleted or soft-deleted).

`DashboardController` is refactored to call the same method, keeping its `views` field.

### Caching

`Cache::remember('trending:cards:7d', 600, ...)` — 10 minutes.

The existing homepage rails use a 60s TTL because admin edits in kurokami must land quickly.
Trending has no such requirement: the underlying data is a 7-day rolling aggregate, so a 60s TTL
would re-run the Mongo aggregation constantly for output that barely moves.

### Failure behaviour

`AnalyticsService::safe()` already converts a downed Mongo or missing collection into an empty
collection, so no new try/catch is needed. What is needed is a **content** fallback: if fewer than 6
cards resolve, the rail falls back to the existing `popular` featured list. A half-empty or missing
rail on the homepage looks broken; a curated fallback does not.

### Rendering

New `<x-rail>` section on the homepage, placed below the hero and above the main paginated grid.
Reuses the existing rail and poster-card components — no new CSS, no new JavaScript. Images stay
lazy-loaded, so the hero remains the LCP element and the recent Core Web Vitals work is unaffected.

## Part B — Member Bookmarks

### Scope

Logged-in members bookmark an **anime** (not an episode). Bookmarking from the anime detail page,
plus a `/member/bookmarks` list page. No watch-progress tracking, no guest localStorage, no sync
layer — deliberately excluded to keep the first iteration small.

### Schema

New migration, table `member_bookmarks`:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `member_id` | bigint, FK → `members.id`, cascade on delete | |
| `cat_id` | unsigned int | |
| `created_at` | timestamp | |

- `UNIQUE (member_id, cat_id)` — the database, not application code, is what guarantees no duplicates
  under concurrent double-clicks.
- `INDEX (member_id, created_at)` — serves the list page's "newest first" ordering.

Two deliberate choices:

- **FK targets `members.id`, not `members.uuid`.** `Member` uses `HasUuids` with
  `uniqueIds() = ['uuid']`, which auto-fills a `uuid` column but does not change the primary key —
  the `members` table still has an auto-increment bigint `id`. A bigint FK is narrower and faster
  than a char(36) one.
- **No FK on `cat_id`.** `yu_anime_catagory` is an imported table shared with kurokami; per CLAUDE.md
  this app does not write schema against it. The list page filters out ids that no longer resolve.

MariaDB rather than Mongo: the list page joins bookmarks to anime rows, `members` already lives in
MariaDB, and tests run against in-memory sqlite with no Mongo available.

### Components

- `App\Models\Bookmark` — `member_bookmarks`, `created_at` only (no `updated_at`).
- `Member::bookmarks()` — `hasMany`.
- `App\Http\Controllers\Member\BookmarkController` — `store`, `destroy`, `index`.
- `<x-bookmark-button :cat-id="..." :bookmarked="..." />` — Alpine, optimistic toggle, CSRF token from
  the meta tag, reverts its own state if the request fails.

### Routes

In `routes/member.php`, inside the existing `auth:member` group, with `throttle:60,1`:

| Method | Path | Response |
|---|---|---|
| `POST` | `/member/bookmarks` (`{cat_id}`) | `{bookmarked: true}` |
| `DELETE` | `/member/bookmarks/{catId}` | `{bookmarked: false}` |
| `GET` | `/member/bookmarks` | Paginated grid page |

Both writes are idempotent — `store` uses `firstOrCreate`, `destroy` deletes without requiring a
prior existence check — so a double-tap or a retried request cannot 500 or duplicate.

`cat_id` is validated as an integer that exists in `yu_anime_catagory`, preventing rows that point at
nothing.

### Page cache interaction

The anime detail page is CDN-cacheable, which normally makes per-member state in the HTML dangerous.
It is safe here: `SecurityHeaders` (lines 38–48) applies `public, max-age=60, s-maxage=300` **only
when the request has no authenticated user or member**. Logged-in members receive no shared-cache
header, so their bookmark state is never stored in a shared cache.

Separately, `Cache::remember("anime:detail:v2:{$id}")` caches anime *data*, not the rendered
response. The controller resolves the member's bookmark state outside that cache, so no member state
can leak into the shared entry.

### Guests

The button renders as a plain link to `/member/login` with identical styling. No JavaScript, no
modal, no layout shift between the guest and member variants.

### Testing

Feature tests:

- Store creates a bookmark; storing twice does not duplicate and still returns 200.
- Destroy removes it; destroying a bookmark that does not exist still returns 200.
- Guests are rejected on both write routes and redirected from the list page.
- `cat_id` that does not exist is rejected with a validation error.
- The list page shows only the authenticated member's bookmarks.
- A bookmark whose anime row is gone is skipped rather than rendering a broken card.

Trending tests:

- `getTrendingCards()` preserves view-count ordering rather than database order.
- It returns an empty array when Mongo is unavailable (`safe()` fallback path).
- The homepage falls back to the `popular` rail when fewer than 6 trending cards resolve.

## Out of scope

Watch progress / continue-watching, guest bookmarks in localStorage, bookmark counts on cards,
bookmark-driven recommendations, and trending rails on pages other than the homepage.
