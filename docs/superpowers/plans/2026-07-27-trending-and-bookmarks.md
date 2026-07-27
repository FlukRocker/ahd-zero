# Trending Rail + Member Bookmarks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Surface analytics-driven trending anime on the homepage, and let logged-in members bookmark anime.

**Architecture:** `AnalyticsService` gains a `getTrendingCards()` method that hydrates the Mongo view-count aggregate into card-shaped arrays; `IndexController` caches and renders it through the existing `<x-rail>` component, and `DashboardController` drops its duplicate inline hydration. Bookmarks are a new MariaDB `member_bookmarks` table with a JSON write API under the `member` guard, an Alpine toggle button on the anime page, and a server-rendered list page at `/member/bookmarks`.

**Tech Stack:** Laravel 12, Blade + Alpine.js, MariaDB (bookmarks), MongoDB (page views), PHPUnit against in-memory sqlite.

## Global Constraints

- PHP 8.4. Run PHP tooling locally as `php`/`composer`; on the production server they are `php84`/`composer84`.
- Never write migrations against `yu_anime_*` tables — the schema is imported and shared with kurokami. `member_bookmarks` is a new app-owned table, so a migration is fine.
- All new PHP must pass `./vendor/bin/pint` and `vendor/bin/phpstan analyse` (level 4). PHPStan level 4 requires docblock array shapes on methods returning arrays.
- Public Blade pages are server-rendered; Alpine only, no Vue, no Motion in `resources/js/blade.js`.
- Thai UI copy — match the existing tone in `resources/views/index.blade.php` and `anime.blade.php`.
- Test command: `vendor/bin/phpunit --filter=NameTest`.

---

### Task 1: `AnalyticsService::getTrendingCards()` + DashboardController de-duplication

**Files:**
- Modify: `app/Services/AnalyticsService.php` (add method after `getTrendingAnime`, ends line 57)
- Modify: `app/Http/Controllers/DashboardController.php:42-60`
- Test: `tests/Feature/TrendingCardsTest.php` (create)

**Interfaces:**
- Consumes: existing `AnalyticsService::getTrendingAnime(int $days, int $limit): Collection` returning rows shaped `['cat_id' => int, 'views' => int]`.
- Produces: `AnalyticsService::getTrendingCards(int $days = 7, int $limit = 12): array` — a list of arrays with keys `cat_id`, `cat_title`, `cat_image`, `cat_type`, `anime_status`, `episodes`, `anime_type`, `banner_md`, `cover_md`, `views`. Ordered by `views` descending. Task 2 consumes this.

**Note:** caching deliberately lives in the caller (Task 2), not in this method — that keeps the method pure and testable, and matches how `IndexController::getFeatured()` is cached by its caller.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TrendingCardsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

/**
 * Stubs the Mongo-backed aggregate so the hydration path can be tested
 * without a Mongo server. Subclassing beats mocking here: AnalyticsService
 * has a typed `$site` property that an un-constructed Mockery partial would
 * leave uninitialized.
 */
final class FakeTrendingAnalytics extends AnalyticsService
{
    /**
     * @param  Collection<int, array{cat_id: int, views: int}>  $rows
     */
    public function __construct(private Collection $rows)
    {
        parent::__construct('test');
    }

    #[Override]
    public function getTrendingAnime(int $days = 7, int $limit = 10): Collection
    {
        return $this->rows;
    }
}

class TrendingCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trending_cards_are_ordered_by_views_not_database_order(): void
    {
        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 101, 'cat_title' => 'Barely Watched', 'cat_type' => 1, 'cat_update' => now()],
            ['cat_id' => 102, 'cat_title' => 'Everyone Watched', 'cat_type' => 2, 'cat_update' => now()],
        ]);

        $analytics = new FakeTrendingAnalytics(collect([
            ['cat_id' => 102, 'views' => 900],
            ['cat_id' => 101, 'views' => 5],
        ]));

        $cards = $analytics->getTrendingCards(7, 12);

        $this->assertSame([102, 101], array_column($cards, 'cat_id'));
        $this->assertSame(900, $cards[0]['views']);
        $this->assertSame('Everyone Watched', $cards[0]['cat_title']);
    }

    public function test_trending_cards_are_empty_when_analytics_returns_nothing(): void
    {
        $analytics = new FakeTrendingAnalytics(collect());

        $this->assertSame([], $analytics->getTrendingCards());
    }

    public function test_trending_cards_skip_ids_with_no_anime_row(): void
    {
        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 101, 'cat_title' => 'Still Here', 'cat_type' => 1, 'cat_update' => now()],
        ]);

        $analytics = new FakeTrendingAnalytics(collect([
            ['cat_id' => 999, 'views' => 900],
            ['cat_id' => 101, 'views' => 5],
        ]));

        $cards = $analytics->getTrendingCards();

        $this->assertSame([101], array_column($cards, 'cat_id'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=TrendingCardsTest`
Expected: FAIL — `Call to undefined method App\Services\AnalyticsService::getTrendingCards()`

- [ ] **Step 3: Add the method to AnalyticsService**

In `app/Services/AnalyticsService.php`, add `use App\Models\Anime;` to the imports, then insert this method directly after `getTrendingAnime()` (which ends at line 57):

```php
    /**
     * Hydrate the trending aggregate into card-shaped rows for the homepage
     * rail and the admin dashboard. Kept cache-free: callers cache with the
     * TTL that suits them.
     *
     * @return list<array{cat_id: int, cat_title: string, cat_image: string|null, cat_type: int, anime_status: string|null, episodes: int|null, anime_type: string|null, banner_md: string|null, cover_md: string|null, views: int}>
     */
    public function getTrendingCards(int $days = 7, int $limit = 12): array
    {
        $trending = $this->getTrendingAnime($days, $limit);

        if ($trending->isEmpty()) {
            return [];
        }

        $viewsById = $trending->pluck('views', 'cat_id');

        return Anime::query()
            ->whereIn('cat_id', $viewsById->keys())
            ->select('cat_id', 'cat_title', 'cat_image', 'cat_type', 'anime_status', 'episodes', 'anime_type', 'cat_banner')
            ->get()
            ->map(fn (Anime $a): array => [
                'cat_id' => (int) $a->cat_id,
                'cat_title' => (string) $a->cat_title,
                'cat_image' => $a->cat_image,
                'cat_type' => (int) $a->cat_type,
                'anime_status' => $a->anime_status,
                'episodes' => $a->episodes,
                'anime_type' => $a->anime_type,
                'banner_md' => $a->banner_md,
                'cover_md' => $a->cover_md,
                'views' => (int) $viewsById->get($a->cat_id, 0),
            ])
            // whereIn returns rows in database order — re-sort so the rail is
            // actually ranked by traffic.
            ->sortByDesc('views')
            ->values()
            ->all();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter=TrendingCardsTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Replace the duplicate hydration in DashboardController**

In `app/Http/Controllers/DashboardController.php`, replace lines 42–60 (from `$trendingIds = ...` through the closing `: collect();`) with a single line:

```php
        $trendingAnime = $analytics->getTrendingCards(7, 10);
```

Then remove any now-unused imports flagged by Pint (`Anime` is still used elsewhere in the file for `$recentAnime` — verify before deleting anything).

- [ ] **Step 6: Verify the dashboard still passes its tests**

Run: `vendor/bin/phpunit --filter=DashboardTest`
Expected: PASS

- [ ] **Step 7: Lint and static analysis**

Run: `./vendor/bin/pint app/Services/AnalyticsService.php app/Http/Controllers/DashboardController.php tests/Feature/TrendingCardsTest.php && vendor/bin/phpstan analyse`
Expected: Pint reports fixed/clean, PHPStan reports no errors.

- [ ] **Step 8: Commit**

```bash
git add app/Services/AnalyticsService.php app/Http/Controllers/DashboardController.php tests/Feature/TrendingCardsTest.php
git commit -m "feat(analytics): add getTrendingCards and drop dashboard's duplicate hydration"
```

---

### Task 2: Trending rail on the homepage

**Files:**
- Modify: `app/Http/Controllers/IndexController.php:11-43`
- Modify: `resources/views/index.blade.php:6-14` and `:27-36`
- Test: `tests/Feature/TrendingRailTest.php` (create)

**Interfaces:**
- Consumes: `AnalyticsService::getTrendingCards(int $days, int $limit): array` from Task 1.
- Produces: a `trending` view variable (the raw card-shaped array) on the `index` view.

**Design notes:**
- The rail renders only when at least 6 cards resolve. Below that it is simply omitted — the existing hand-curated "ยอดนิยม" rail is already unconditional, so the homepage keeps a populated rail either way. This is the fallback the spec calls for; no extra branch is needed.
- Cache TTL is 600s, not the 60s used by the other homepage caches. Those exist so admin edits in kurokami appear quickly; trending is a 7-day rolling aggregate that barely moves, so a 60s TTL would re-run the Mongo aggregation for nothing.
- The existing "ยอดนิยม" section already uses the eyebrow "กำลังมาแรง", so the new rail uses different copy to avoid two identically-labelled rails.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TrendingRailTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class TrendingRailTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // IndexController caches into the array store, which survives across
        // tests in one process.
        Cache::flush();
    }

    /**
     * @param  list<int>  $catIds
     */
    private function seedAnime(array $catIds): void
    {
        foreach ($catIds as $catId) {
            DB::table('yu_anime_catagory')->insert([
                'cat_id' => $catId,
                'cat_title' => "Trending Title {$catId}",
                'cat_type' => 1,
                'cat_update' => now(),
            ]);
        }
    }

    /**
     * @param  list<int>  $catIds
     */
    private function fakeAnalytics(array $catIds): void
    {
        $rows = collect($catIds)->map(fn (int $id, int $i): array => [
            'cat_id' => $id,
            'views' => 1000 - $i,
        ]);

        $this->instance(AnalyticsService::class, new FakeTrendingAnalytics($rows));
    }

    public function test_homepage_renders_trending_rail_from_analytics(): void
    {
        $ids = [201, 202, 203, 204, 205, 206];
        $this->seedAnime($ids);
        $this->fakeAnalytics($ids);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('มาแรงตอนนี้', false);
        $response->assertSee('Trending Title 201', false);
    }

    public function test_trending_rail_hidden_when_too_few_results(): void
    {
        $ids = [301, 302, 303];
        $this->seedAnime($ids);
        $this->fakeAnalytics($ids);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('มาแรงตอนนี้', false);
    }

    public function test_homepage_still_renders_when_analytics_is_empty(): void
    {
        $this->seedAnime([401]);
        $this->instance(AnalyticsService::class, new FakeTrendingAnalytics(collect()));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('มาแรงตอนนี้', false);
        $response->assertSee('Trending Title 401', false);
    }
}
```

This reuses `FakeTrendingAnalytics` from `tests/Feature/TrendingCardsTest.php` (same `Tests\Feature` namespace, autoloaded by PHPUnit).

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=TrendingRailTest`
Expected: FAIL — `Failed asserting that ... contains "มาแรงตอนนี้"`

- [ ] **Step 3: Wire the controller**

In `app/Http/Controllers/IndexController.php`, add `use App\Services\AnalyticsService;` to the imports, change the method signature, and add the trending fetch + view key:

```php
    public function renderIndex(AnalyticsService $analytics)
    {
```

Then after the `$popular` line (currently line 36), add:

```php
        // 600s, not the 60s used above: trending is a 7-day rolling aggregate
        // that barely moves, so a short TTL just re-runs the Mongo pipeline.
        $trending = Cache::remember('trending:cards:7d', 600, fn (): array => $analytics->getTrendingCards(7, 12));
```

And add `'trending' => $trending,` to the `view('index', [...])` array.

- [ ] **Step 4: Render the rail**

In `resources/views/index.blade.php`, inside the `@php` block (after line 12's `$popularItems`), add:

```php
    // Rail is hidden below 6 cards — a half-empty rail reads as broken, and
    // the curated "ยอดนิยม" rail below is already unconditional.
    $trendingItems = count($trending) >= 6 ? CardPresenter::collection($trending) : [];
```

Then insert this section between the hero block (ends line 29) and the popular section (starts line 31):

```blade
    @if (! empty($trendingItems))
        <section class="mx-auto mt-20 max-w-[1440px] px-6 lg:px-10">
            <x-section-header eyebrow="จากยอดวิว 7 วันล่าสุด" title="มาแรงตอนนี้" />
            <x-rail :items="$trendingItems" />
        </section>
    @endif
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter=TrendingRailTest && vendor/bin/phpunit --filter=IndexPageTest`
Expected: PASS both

- [ ] **Step 6: Lint and static analysis**

Run: `./vendor/bin/pint app/Http/Controllers/IndexController.php tests/Feature/TrendingRailTest.php && vendor/bin/phpstan analyse`
Expected: no errors

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/IndexController.php resources/views/index.blade.php tests/Feature/TrendingRailTest.php
git commit -m "feat(index): add analytics-driven trending rail to the homepage"
```

---

### Task 3: `member_bookmarks` table and Bookmark model

**Files:**
- Create: `database/migrations/2026_07_27_000001_create_member_bookmarks_table.php`
- Create: `app/Models/Bookmark.php`
- Modify: `app/Models/Member.php` (add relation after `casts()`, before `uniqueIds()`)
- Test: `tests/Feature/BookmarkModelTest.php` (create)

**Interfaces:**
- Produces: `App\Models\Bookmark` with `$fillable = ['member_id', 'cat_id']`, relations `anime()` (BelongsTo `Anime` on `cat_id`) and `member()`; `Member::bookmarks(): HasMany`. Tasks 4–6 consume these.

**Design notes:**
- FK targets `members.id` (bigint), not `members.uuid`. `Member` uses `HasUuids` with `uniqueIds() = ['uuid']`, which only auto-fills the `uuid` column — the primary key is still the auto-increment `id` from `2026_03_22_000001_create_members_table.php`.
- No FK on `cat_id`: `yu_anime_catagory` is imported and shared with kurokami.
- The unique index — not application code — is what prevents duplicates under a double-click.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BookmarkModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookmarkModelTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Member
    {
        return Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);
    }

    public function test_member_has_many_bookmarks(): void
    {
        DB::table('yu_anime_catagory')->insert([
            'cat_id' => 501, 'cat_title' => 'Saved Show', 'cat_type' => 1, 'cat_update' => now(),
        ]);

        $member = $this->member();
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 501]);

        $this->assertCount(1, $member->bookmarks()->get());
        $this->assertSame('Saved Show', $member->bookmarks()->first()->anime->cat_title);
    }

    public function test_duplicate_bookmark_is_rejected_by_the_database(): void
    {
        $member = $this->member();
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 502]);

        $this->expectException(QueryException::class);
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 502]);
    }

    public function test_deleting_a_member_deletes_their_bookmarks(): void
    {
        $member = $this->member();
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 503]);

        $member->delete();

        $this->assertSame(0, Bookmark::query()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=BookmarkModelTest`
Expected: FAIL — `Class "App\Models\Bookmark" not found`

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_27_000001_create_member_bookmarks_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            // No FK: yu_anime_catagory is imported and shared with kurokami.
            $table->unsignedInteger('cat_id');
            $table->timestamp('created_at')->nullable();

            // The database, not the controller, is what makes a double-click
            // safe.
            $table->unique(['member_id', 'cat_id']);
            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_bookmarks');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/Bookmark.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $member_id
 * @property int $cat_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Anime|null $anime
 */
class Bookmark extends Model
{
    protected $table = 'member_bookmarks';

    /** Insert-only rows — there is nothing to update. */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'member_id',
        'cat_id',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'cat_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Anime, $this>
     */
    public function anime(): BelongsTo
    {
        return $this->belongsTo(Anime::class, 'cat_id', 'cat_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
```

- [ ] **Step 5: Add the Member relation**

In `app/Models/Member.php`, add `use Illuminate\Database\Eloquent\Relations\HasMany;` to the imports and insert this method after `casts()` (ends line 66):

```php
    /**
     * @return HasMany<Bookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter=BookmarkModelTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Lint and static analysis**

Run: `./vendor/bin/pint app/Models/Bookmark.php app/Models/Member.php database/migrations/2026_07_27_000001_create_member_bookmarks_table.php tests/Feature/BookmarkModelTest.php && vendor/bin/phpstan analyse`
Expected: no errors

- [ ] **Step 8: Commit**

```bash
git add app/Models/Bookmark.php app/Models/Member.php database/migrations/2026_07_27_000001_create_member_bookmarks_table.php tests/Feature/BookmarkModelTest.php
git commit -m "feat(bookmarks): add member_bookmarks table and Bookmark model"
```

---

### Task 4: Bookmark write API

**Files:**
- Create: `app/Http/Controllers/Member/BookmarkController.php`
- Modify: `routes/member.php` (inside the existing `auth:member` group)
- Test: `tests/Feature/BookmarkApiTest.php` (create)

**Interfaces:**
- Consumes: `App\Models\Bookmark` from Task 3.
- Produces: routes `member.bookmarks.store` (`POST /member/bookmarks`, body `{cat_id: int}`, returns `{"bookmarked": true}`) and `member.bookmarks.destroy` (`DELETE /member/bookmarks/{catId}`, returns `{"bookmarked": false}`). Task 5's button calls both.

**Design notes:**
- Both writes are idempotent: `store` uses `firstOrCreate`, `destroy` deletes without checking existence first. A retried or double-tapped request must not 500 or duplicate.
- `cat_id` is validated against `yu_anime_catagory` so rows cannot point at nothing.
- `/member/*` paths are already in the skip list in `SecurityHeaders.php:41-46`, so these responses never get a shared-cache header.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BookmarkApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class BookmarkApiTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        DB::table('yu_anime_catagory')->insert([
            'cat_id' => 601, 'cat_title' => 'Bookmarkable', 'cat_type' => 1, 'cat_update' => now(),
        ]);

        $this->member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);
    }

    public function test_member_can_bookmark_an_anime(): void
    {
        $response = $this->actingAs($this->member, 'member')
            ->postJson('/member/bookmarks', ['cat_id' => 601]);

        $response->assertOk()->assertJson(['bookmarked' => true]);
        $this->assertDatabaseHas('member_bookmarks', [
            'member_id' => $this->member->id,
            'cat_id' => 601,
        ]);
    }

    public function test_bookmarking_twice_does_not_duplicate_or_error(): void
    {
        $this->actingAs($this->member, 'member')->postJson('/member/bookmarks', ['cat_id' => 601]);
        $second = $this->actingAs($this->member, 'member')->postJson('/member/bookmarks', ['cat_id' => 601]);

        $second->assertOk()->assertJson(['bookmarked' => true]);
        $this->assertSame(1, Bookmark::query()->count());
    }

    public function test_member_can_remove_a_bookmark(): void
    {
        Bookmark::create(['member_id' => $this->member->id, 'cat_id' => 601]);

        $response = $this->actingAs($this->member, 'member')
            ->deleteJson('/member/bookmarks/601');

        $response->assertOk()->assertJson(['bookmarked' => false]);
        $this->assertSame(0, Bookmark::query()->count());
    }

    public function test_removing_a_bookmark_that_does_not_exist_still_succeeds(): void
    {
        $response = $this->actingAs($this->member, 'member')
            ->deleteJson('/member/bookmarks/601');

        $response->assertOk()->assertJson(['bookmarked' => false]);
    }

    public function test_unknown_anime_is_rejected(): void
    {
        $response = $this->actingAs($this->member, 'member')
            ->postJson('/member/bookmarks', ['cat_id' => 999999]);

        $response->assertStatus(422);
    }

    public function test_guests_cannot_write_bookmarks(): void
    {
        $this->postJson('/member/bookmarks', ['cat_id' => 601])->assertStatus(401);
        $this->deleteJson('/member/bookmarks/601')->assertStatus(401);
    }

    public function test_a_member_cannot_delete_another_members_bookmark(): void
    {
        $other = Member::create([
            'name' => 'Other',
            'email' => 'other@example.test',
            'password' => 'password123!',
        ]);
        Bookmark::create(['member_id' => $other->id, 'cat_id' => 601]);

        $this->actingAs($this->member, 'member')->deleteJson('/member/bookmarks/601')->assertOk();

        $this->assertSame(1, Bookmark::query()->where('member_id', $other->id)->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=BookmarkApiTest`
Expected: FAIL — 404 responses, since the routes do not exist yet.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Member/BookmarkController.php`:

```php
<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function response;

class BookmarkController extends Controller
{
    /**
     * Idempotent: firstOrCreate plus the unique index means a double-tap or a
     * retried request returns 200 instead of a duplicate-key 500.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cat_id' => ['required', 'integer', 'exists:yu_anime_catagory,cat_id'],
        ]);

        Bookmark::firstOrCreate([
            'member_id' => $this->member()->id,
            'cat_id' => (int) $data['cat_id'],
        ]);

        return response()->json(['bookmarked' => true]);
    }

    /**
     * Scoped to the authenticated member, so one member's request can never
     * touch another's row. Deleting a non-existent bookmark is a no-op, not an
     * error — the client's desired end state is already true.
     */
    public function destroy(int $catId): JsonResponse
    {
        Bookmark::query()
            ->where('member_id', $this->member()->id)
            ->where('cat_id', $catId)
            ->delete();

        return response()->json(['bookmarked' => false]);
    }

    private function member(): Member
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return $member;
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/member.php`, add `use App\Http\Controllers\Member\BookmarkController;` to the imports, then add these routes inside the existing `Route::middleware('auth:member')->group(...)` block (the one containing the settings routes):

```php
    Route::post('/member/bookmarks', [BookmarkController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('member.bookmarks.store');

    Route::delete('/member/bookmarks/{catId}', [BookmarkController::class, 'destroy'])
        ->whereNumber('catId')
        ->middleware('throttle:60,1')
        ->name('member.bookmarks.destroy');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter=BookmarkApiTest`
Expected: PASS (7 tests)

- [ ] **Step 6: Lint and static analysis**

Run: `./vendor/bin/pint app/Http/Controllers/Member/BookmarkController.php routes/member.php tests/Feature/BookmarkApiTest.php && vendor/bin/phpstan analyse`
Expected: no errors

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Member/BookmarkController.php routes/member.php tests/Feature/BookmarkApiTest.php
git commit -m "feat(bookmarks): add idempotent member bookmark write API"
```

---

### Task 5: Bookmark button on the anime page

**Files:**
- Create: `resources/views/components/bookmark-button.blade.php`
- Modify: `app/Http/Controllers/AnimeController.php:27-40`
- Modify: `resources/views/anime.blade.php:102`
- Test: `tests/Feature/BookmarkButtonTest.php` (create)

**Interfaces:**
- Consumes: `App\Models\Bookmark` (Task 3) and the routes from Task 4.
- Produces: `<x-bookmark-button :cat-id="int" :bookmarked="bool" />`, and a `bookmarked` boolean view variable on the `anime` view.

**Design notes:**
- `anime.blade.php:102` currently holds a dead placeholder — `<button class="btn btn-ghost" type="button">+ เพิ่มในรายการ</button>` — which this replaces.
- Rendering per-member state into this page is safe: `SecurityHeaders.php:38-48` only applies `public, max-age=60, s-maxage=300` when the request has **no** authenticated user or member, so a member's page never lands in a shared cache.
- The bookmark lookup goes **outside** `Cache::remember("anime:detail:v2:{$id}")`, which caches anime data only. Putting member state inside that key would serve one member's state to everyone.
- `$memberAuth` is already shared with every Blade view by `GlobalComposer`.
- The CSRF meta tag exists at `layouts/app.blade.php:9`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BookmarkButtonTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class BookmarkButtonTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // AnimeController caches detail data in the array store.
        Cache::flush();

        DB::table('yu_anime_catagory')->insert([
            'cat_id' => 701, 'cat_title' => 'Button Show', 'cat_type' => 1, 'cat_update' => now(),
        ]);
    }

    private function member(string $email = 'rin@example.test'): Member
    {
        return Member::create([
            'name' => 'Rin',
            'email' => $email,
            'password' => 'password123!',
        ]);
    }

    public function test_guest_sees_a_login_link_instead_of_a_toggle(): void
    {
        $response = $this->get('/anime/701');

        $response->assertOk();
        $response->assertSee('href="/member/login"', false);
    }

    public function test_member_sees_an_unset_toggle_when_not_bookmarked(): void
    {
        $response = $this->actingAs($this->member(), 'member')->get('/anime/701');

        $response->assertOk();
        $response->assertSee('data-bookmarked="false"', false);
    }

    public function test_member_sees_a_set_toggle_when_bookmarked(): void
    {
        $member = $this->member();
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 701]);

        $response = $this->actingAs($member, 'member')->get('/anime/701');

        $response->assertOk();
        $response->assertSee('data-bookmarked="true"', false);
    }

    public function test_one_members_bookmark_does_not_leak_to_another(): void
    {
        $owner = $this->member('owner@example.test');
        Bookmark::create(['member_id' => $owner->id, 'cat_id' => 701]);

        $other = $this->member('other@example.test');
        $response = $this->actingAs($other, 'member')->get('/anime/701');

        $response->assertOk();
        $response->assertSee('data-bookmarked="false"', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=BookmarkButtonTest`
Expected: FAIL — `data-bookmarked` is not in the response.

- [ ] **Step 3: Write the component**

Create `resources/views/components/bookmark-button.blade.php`:

```blade
@props(['catId', 'bookmarked' => false])

@if ($memberAuth)
    <button type="button" class="btn btn-ghost"
        data-bookmarked="{{ $bookmarked ? 'true' : 'false' }}"
        x-data="bookmarkToggle({{ (int) $catId }}, {{ $bookmarked ? 'true' : 'false' }})"
        x-on:click="toggle()"
        x-bind:disabled="busy"
        x-bind:aria-pressed="on ? 'true' : 'false'">
        <span x-text="on ? '✓ อยู่ในรายการ' : '+ เพิ่มในรายการ'">{{ $bookmarked ? '✓ อยู่ในรายการ' : '+ เพิ่มในรายการ' }}</span>
    </button>
@else
    {{-- Guests get a plain link, styled identically so there is no shift. --}}
    <a href="/member/login" class="btn btn-ghost">+ เพิ่มในรายการ</a>
@endif
```

- [ ] **Step 4: Register the Alpine component**

In `resources/js/blade.js`, add this immediately before the `Alpine.start();` call on line 45:

```js
// Bookmark toggle. Optimistic: flip immediately, revert if the write fails,
// so a slow network never makes the button feel dead.
Alpine.data('bookmarkToggle', (catId, initial) => ({
    on: initial,
    busy: false,
    async toggle() {
        if (this.busy) return;
        this.busy = true;
        const next = !this.on;
        this.on = next;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const headers = {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            };
            const res = next
                ? await fetch('/member/bookmarks', {
                      method: 'POST',
                      headers,
                      body: JSON.stringify({ cat_id: catId }),
                  })
                : await fetch(`/member/bookmarks/${catId}`, { method: 'DELETE', headers });
            if (!res.ok) throw new Error(String(res.status));
        } catch {
            this.on = !next;
        } finally {
            this.busy = false;
        }
    },
}));
```

The registration must precede `Alpine.start()` — components registered after start are never picked up.

- [ ] **Step 5: Resolve bookmark state in the controller**

In `app/Http/Controllers/AnimeController.php`, add these imports:

```php
use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;
```

Then in `show()`, after the `Cache::remember(...)` line and before `return view(...)`, add:

```php
        // Deliberately outside the cache above: that key caches anime data and
        // is shared by every visitor. Member state must never enter it.
        /** @var Member|null $member */
        $member = Auth::guard('member')->user();
        $bookmarked = $member !== null && Bookmark::query()
            ->where('member_id', $member->id)
            ->where('cat_id', $id)
            ->exists();
```

And add `'bookmarked' => $bookmarked,` to the `view('anime', [...])` array.

- [ ] **Step 6: Swap the placeholder button**

In `resources/views/anime.blade.php`, replace line 102:

```blade
                    <button class="btn btn-ghost" type="button">+ เพิ่มในรายการ</button>
```

with:

```blade
                    <x-bookmark-button :cat-id="$anime['cat_id']" :bookmarked="$bookmarked" />
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter=BookmarkButtonTest && vendor/bin/phpunit --filter=AnimeDetailPageTest`
Expected: PASS both

- [ ] **Step 8: Build the frontend and lint**

Run: `pnpm build && pnpm lint && ./vendor/bin/pint app/Http/Controllers/AnimeController.php tests/Feature/BookmarkButtonTest.php && vendor/bin/phpstan analyse`
Expected: build succeeds, no lint or analysis errors

- [ ] **Step 9: Commit**

```bash
git add resources/views/components/bookmark-button.blade.php resources/views/anime.blade.php resources/js/blade.js app/Http/Controllers/AnimeController.php tests/Feature/BookmarkButtonTest.php
git commit -m "feat(bookmarks): replace dead placeholder with a working bookmark toggle"
```

---

### Task 6: `/member/bookmarks` list page

**Files:**
- Modify: `app/Http/Controllers/Member/BookmarkController.php` (add `index`)
- Modify: `routes/member.php` (add the GET route)
- Create: `resources/views/member/bookmarks.blade.php`
- Test: `tests/Feature/BookmarkPageTest.php` (create)

**Interfaces:**
- Consumes: `Bookmark`, `Member::bookmarks()` (Task 3), `BookmarkController` (Task 4).
- Produces: route `member.bookmarks` (`GET /member/bookmarks`).

**Design notes:**
- The page is `noindex` — it is per-member content with no search value. The layout's SEO partial reads a `robots` section (`partials/seo.blade.php:9`).
- Bookmarks whose anime row has been deleted upstream are filtered out rather than rendered as broken cards. Because filtering happens after pagination, a page can show fewer than 24 cards; that is acceptable and much cheaper than a join-and-filter across the shared anime table.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BookmarkPageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class BookmarkPageTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        DB::table('yu_anime_catagory')->insert([
            ['cat_id' => 801, 'cat_title' => 'Mine Alone', 'cat_type' => 1, 'cat_update' => now()],
            ['cat_id' => 802, 'cat_title' => 'Someone Elses', 'cat_type' => 1, 'cat_update' => now()],
        ]);
    }

    private function member(string $email): Member
    {
        return Member::create([
            'name' => 'Rin',
            'email' => $email,
            'password' => 'password123!',
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/member/bookmarks')->assertRedirect('/member/login');
    }

    public function test_page_shows_only_the_authenticated_members_bookmarks(): void
    {
        $mine = $this->member('mine@example.test');
        $theirs = $this->member('theirs@example.test');

        Bookmark::create(['member_id' => $mine->id, 'cat_id' => 801]);
        Bookmark::create(['member_id' => $theirs->id, 'cat_id' => 802]);

        $response = $this->actingAs($mine, 'member')->get('/member/bookmarks');

        $response->assertOk();
        $response->assertSee('Mine Alone', false);
        $response->assertDontSee('Someone Elses', false);
    }

    public function test_page_is_noindex(): void
    {
        $response = $this->actingAs($this->member('rin@example.test'), 'member')->get('/member/bookmarks');

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    public function test_bookmark_with_a_missing_anime_row_is_skipped(): void
    {
        $member = $this->member('rin@example.test');
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 801]);
        Bookmark::create(['member_id' => $member->id, 'cat_id' => 999999]);

        $response = $this->actingAs($member, 'member')->get('/member/bookmarks');

        $response->assertOk();
        $response->assertSee('Mine Alone', false);
    }

    public function test_empty_state_renders(): void
    {
        $response = $this->actingAs($this->member('rin@example.test'), 'member')->get('/member/bookmarks');

        $response->assertOk();
        $response->assertSee('ยังไม่มีรายการที่บันทึกไว้', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter=BookmarkPageTest`
Expected: FAIL — 404, the route does not exist.

- [ ] **Step 3: Add the index action**

In `app/Http/Controllers/Member/BookmarkController.php`, add these imports:

```php
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
```

Then add this method after `destroy()`:

```php
    public function index(): View
    {
        /** @var LengthAwarePaginator<int, Bookmark> $bookmarks */
        $bookmarks = Bookmark::query()
            ->where('member_id', $this->member()->id)
            ->with('anime:cat_id,cat_title,cat_image,cat_type,anime_status,episodes,anime_type,cat_banner')
            ->orderByDesc('created_at')
            ->paginate(24);

        // Anime rows can disappear upstream (kurokami owns that table); a
        // dangling bookmark is skipped rather than rendered as a broken card.
        $items = $bookmarks->getCollection()
            ->map(fn (Bookmark $b) => $b->anime)
            ->filter()
            ->values()
            ->all();

        return view('member.bookmarks', [
            'bookmarks' => $bookmarks,
            'items' => $items,
        ]);
    }
```

- [ ] **Step 4: Add the route and point member-area guests at the member login**

In `routes/member.php`, inside the same `auth:member` group, add:

```php
    Route::get('/member/bookmarks', [BookmarkController::class, 'index'])
        ->name('member.bookmarks');
```

A guest hitting this route currently lands on `/login` — the **admin** Fortify login — because Laravel's
`Authenticate` middleware falls back to the `login` route for every guard. That is wrong for the whole
`/member` area, not just this page. Fix it once in `bootstrap/app.php` by adding this inside the
`withMiddleware` closure, after the `$middleware->alias([...])` block (line 42):

```php
        // Guards other than `web` still fall back to the admin Fortify login by
        // default. Anything under /member belongs to the member guard, so send
        // those guests to the member login instead.
        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('member/*')
                ? route('member.login')
                : route('login')
        );
```

Add `use Illuminate\Http\Request;` to that file's imports.

- [ ] **Step 5: Write the view**

Create `resources/views/member/bookmarks.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'รายการที่บันทึกไว้')
@section('description', 'อนิเมะที่คุณบันทึกไว้บน Anime HD Zero')
@section('robots', 'noindex,nofollow')

@php
    use App\Support\CardPresenter;

    $cards = CardPresenter::collection($items);
@endphp

@section('content')
    <section class="mx-auto mt-16 max-w-[1440px] px-6 lg:px-10">
        <x-section-header eyebrow="ของฉัน" title="รายการที่บันทึกไว้" />

        @if (empty($cards))
            <p class="mt-8 text-[15px]" style="color: hsl(var(--fg-muted))">
                ยังไม่มีรายการที่บันทึกไว้ — กด “+ เพิ่มในรายการ” ที่หน้าอนิเมะเพื่อเก็บไว้ดูภายหลัง
            </p>
        @else
            <x-card-grid>
                @foreach ($cards as $item)
                    <x-poster-card :item="$item" />
                @endforeach
            </x-card-grid>
            {{ $bookmarks->links('pagination.ahd') }}
        @endif
    </section>
@endsection
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter=BookmarkPageTest`
Expected: PASS (5 tests)

- [ ] **Step 7: Add a header link to the page**

In `resources/views/components/site-header.blade.php`, inside the `@if ($memberAuth)` dropdown block (starts line 64) — next to the existing account links — add:

```blade
                        <a href="/member/bookmarks" class="block px-3 py-2 text-[13px]" style="color: hsl(var(--fg))">รายการที่บันทึกไว้</a>
```

Match the surrounding markup's exact classes and inline styles; the snippet above is the shape, but copy the neighbouring link's attributes if they differ.

- [ ] **Step 8: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS, no regressions

- [ ] **Step 9: Lint and static analysis**

Run: `./vendor/bin/pint && vendor/bin/phpstan analyse && pnpm lint`
Expected: no errors

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Member/BookmarkController.php routes/member.php bootstrap/app.php resources/views/member/bookmarks.blade.php resources/views/components/site-header.blade.php tests/Feature/BookmarkPageTest.php
git commit -m "feat(bookmarks): add /member/bookmarks list page

Also redirects guests hitting any /member route to the member login rather
than the admin Fortify login, which is where Laravel's default guard
fallback was sending them."
```

---

### Task 7: Local verification

**Files:** none — this task verifies the running app.

- [ ] **Step 1: Run the full backend suite**

Run: `vendor/bin/phpunit`
Expected: all green

- [ ] **Step 2: Run the frontend suite and build**

Run: `pnpm test && pnpm build`
Expected: all green, build succeeds

- [ ] **Step 3: Migrate the local database**

Run: `php artisan migrate`
Expected: `member_bookmarks` table created

- [ ] **Step 4: Boot the app**

Run: `composer dev`
Expected: server on `http://localhost:8000`, Vite running

- [ ] **Step 5: Verify in a browser**

Check each of these:
- `/` — the trending rail appears if Mongo has ≥6 anime page views in the last 7 days; if Mongo is not running locally, confirm the homepage renders normally with no trending rail and no error.
- `/anime/{id}` as a guest — the button is a link to `/member/login`.
- `/anime/{id}` logged in — clicking toggles the label between `+ เพิ่มในรายการ` and `✓ อยู่ในรายการ`, and the state survives a page reload.
- `/member/bookmarks` — shows the bookmarked anime; removing it from the anime page then reloading removes it from the list.
- Browser console — no errors on any of the above.

- [ ] **Step 6: Report results**

Report what passed and what did not, with actual output for any failure. Do not mark the plan complete while anything is red.
