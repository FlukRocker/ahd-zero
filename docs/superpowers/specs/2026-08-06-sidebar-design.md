# Sidebar: genres + popular

**Date:** 2026-08-06
**Status:** approved

## Problem

Two gaps, one shape:

1. The homepage's "ยอดนิยม" rail was removed (commit `7f6094c`) because `featured_anime` holds zero rows of type `popular`, so it silently rendered the newest anime under a label claiming popularity. Real popularity data exists in Mongo via `AnalyticsService` but is only surfaced by the homepage trending rail.
2. The `tags` table holds 38 rows of `type = genre`, wired to anime through the `taggables` pivot, and nothing in the site links to them. They are dead data.

Both belong in a persistent sidebar, alongside the main content, on every public page.

## Decisions

| Question | Decision |
|---|---|
| Sidebar blocks | Genres (38 tags) + popular. Not the category nav. |
| Pages | Every public page. |
| Position vs hero/player | Below them — hero and player keep full width. |
| Time tabs | 7 วัน / 30 วัน / ตลอดเวลา, all three preloaded, Alpine toggles. |
| Side | Right of content on desktop. |
| Mobile | Stacks below content under `lg`. No drawer. |

## Architecture

Five units, each with a single responsibility:

| Unit | Responsibility | Depends on |
|---|---|---|
| `App\Support\SidebarData` | Fetch and cache both datasets | `Tag`, `AnalyticsService` |
| `App\View\Composers\SidebarComposer` | Bind that data to the sidebar component | `SidebarData` |
| `resources/views/components/sidebar.blade.php` | Render both blocks | composer-provided data only |
| `resources/views/components/content-with-sidebar.blade.php` | Two-column grid wrapper | nothing |
| `GenreController` + `resources/views/genre.blade.php` | The `/genre/{slug}` destination | `Tag`, `Anime` |

The composer binds to `components.sidebar` alone, not globally. Admin and Inertia pages never run a sidebar query.

`SidebarData` holds no Blade knowledge; the component runs no queries. Either can be changed without touching the other.

## Data flow

### Genres

```
Tag::where('type', 'genre')->orderBy('order_column')->orderBy('name')
```

Renders `name_th` (falling back to `name`), links to `/genre/{slug}`. Pure MariaDB. Cached 1 hour under `sidebar:genres` — genre lists effectively never change.

### Popular

`AnalyticsService::getTrendingCards()` gains a nullable `$days`:

- `7` → last 7 days
- `30` → last 30 days
- `null` → all time; the `created_at` clause is dropped from the aggregation pipeline entirely

Three cache keys — `sidebar:popular:7`, `sidebar:popular:30`, `sidebar:popular:all` — at the 600s TTL the homepage trending rail already uses. Limit 10 per list.

All three lists render into the HTML. Alpine toggles `x-show`; the 7-day tab is visible by default so the block still works with JavaScript disabled.

### Caching

Sidebar data is identical on `/` and `/anime/123`, so it is computed once and shared across every page — not keyed per-page or per-request.

## Layout

`<x-content-with-sidebar>` wraps a page's below-hero content:

```
lg:grid-cols-[1fr_320px] gap-8
```

Main content comes first in the DOM, so it leads for both crawlers and screen readers regardless of the visual right-hand placement. Below `lg` the grid is a single column and the sidebar stacks underneath.

Per page:

| Page | Sidebar wraps |
|---|---|
| `/` | everything below the hero |
| `/category/{type}` | the listing grid |
| `/search/results` | the results grid |
| `/genre/{slug}` | the listing grid |
| `/studios`, `/voice-actors`, `/staff`, `/studio/{id}`, `/voice-actor/{id}` | the listing grid |
| `/anime/{id}` | everything below the full-bleed hero |
| `/anime/{id}/episode/{listId}` | everything below the player |

Hero and player markup is not modified. The episode page's LCP tuning is untouched.

## Error handling

| Condition | Behavior |
|---|---|
| Mongo down or unreachable | `AnalyticsService::safe()` returns `[]`; popular block omitted entirely, heading included |
| Analytics returns zero rows | Same — block omitted |
| No genre tags | Genres block omitted |
| Both empty | Sidebar renders nothing; wrapper collapses to full width, leaving no orphan empty column |
| `/genre/{unknown}` | 404 |
| Genre with zero anime | Renders the page with an empty-state message, not a 404 |

The omit-when-empty rule matters more here than for the homepage rail: a broken Mongo would otherwise put an empty heading on every page of the site.

## Testing

Feature tests:

1. Genres block renders on a public page, showing Thai names.
2. Popular block is absent when analytics returns empty.
3. All three tab lists render when analytics has data.
4. Sidebar is absent from the admin dashboard.
5. `/genre/{slug}` returns only anime carrying that tag.
6. `/genre/{unknown-slug}` 404s.
7. A genre with no anime renders an empty state rather than erroring.
8. Sidebar markup appears on anime and episode pages below the hero/player.

Existing suite (133 tests) must stay green.

## Out of scope

The reference image's จบแล้ว / ยังไม่จบ / อนิเมะ 18+ / หนัง-ซีรีย์ / Hentai entries. `anime_status` holds values (`Finished Airing`, `Currently Airing`, `Not yet aired`) but has no route, and no adult-content flag exists in the schema. Adding those means new filters and new routes — a separate piece of work.

Curating `featured_anime` rows of type `popular`, and any admin UI for doing so, also remains out of scope. The sidebar's popular block is analytics-driven and needs no curation.
