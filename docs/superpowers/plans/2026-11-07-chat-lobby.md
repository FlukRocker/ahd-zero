# Live Chat Lobby Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a xat-style single-room live chat at `/chat` for logged-in members, with ranks and in-room moderation, admin-authored cosmetics members own and equip, and an admin-granted coin balance.

**Architecture:** Blade page + Alpine on the public site; messages are sent over an ordinary HTTP POST (so they pass auth, validation, throttling and the gag/ban checks) and pushed back out over a Laravel Reverb websocket. A public channel carries messages; a presence channel carries the online roster. The last 200 messages are cached in Redis behind an interface so tests can fake it, with `chat_messages` as the durable record.

**Tech Stack:** Laravel 12, PHP 8.4, Blade + Alpine.js 3, Laravel Reverb, laravel-echo + pusher-js, MariaDB, Redis, Inertia + Vue 3 (admin page only), PHPUnit on in-memory sqlite.

**Spec:** `docs/superpowers/specs/2026-11-07-chat-lobby-design.md` — read it alongside this plan; the plan argues from it.

## Global Constraints

- **PHP binaries on the production box are `php84` and `composer84`**, never `php`/`composer`.
- **Never create a new migration file in this repo.** The real schema is created in ahd-admin (Task 1a). The sqlite test schema is appended to the existing `database/migrations/2024_01_01_000005_create_tag_shim_tables.php`, which is already recorded as run.
- **The sqlite shim must be foreign-key-free.** `2024_01_01_000005` runs *before* `2026_03_22_000001_create_members_table`, so a `foreignId(...)->constrained('members')` in the shim fails. Use plain `unsignedBigInteger`. Real FKs exist only in the ahd-admin migration, and no test may rely on cascade-delete.
- **Every chat event implements `ShouldBroadcastNow`, never `ShouldBroadcast`.** Production `.env` has `QUEUE_CONNECTION=redis` and `ecosystem.config.cjs` leaves the queue worker **off** (`QUEUE_WORKER=1` opt-in). A queued broadcast would sit in Redis forever while every `Event::fake` test still passed.
- **`members.id` is a bigint** (`$table->id()`); `members.uuid` is a separate unique column and `Member::uniqueIds()` returns `['uuid']`. Every FK targets the bigint. The uuid appears only in URLs and broadcast payloads.
- **There is no `MemberFactory`.** Create members exactly as `tests/Feature/BookmarkApiTest.php` does: `Member::create(['name' => ..., 'email' => ..., 'password' => ...])`, and set `email_verified_at` explicitly when the test needs a verified member.
- **Email verification middleware must name the member route:** `verified:member.verification.notice`. Laravel's bare `verified` redirects to `verification.notice`, which is the admin Fortify route.
- **The presence channel is named `chat.roster`**, not the spec's `presence-chat.lobby`. Laravel registers authorization callbacks by bare channel name, so reusing `chat.lobby` for both the public and presence channel is ambiguous. The spec's intent is unchanged; only the name differs.
- **Redis is shared with other apps. Never `FLUSHDB`.** Only the single key `chat:lobby:history` is written, through the Redis facade so it carries the app prefix.
- **The `catagory_id` column spelling is intentional** — do not "fix" it anywhere you touch.
- Every task ends green: `vendor/bin/phpunit`, `./vendor/bin/pint`, and `vendor/bin/phpstan analyse --memory-limit=1G` (level 4, must report `[OK] No errors`).
- Tests that render a page call `$this->withoutVite()` in `setUp`, like every existing feature test.

## Review Focus

Input classes the spec implies, that a naive implementation breaks, and which task pins each one:

1. **A member who has never sent a message buys or is granted something.** They have no `chat_members` row, so `SELECT ... FOR UPDATE` locks nothing and two concurrent writes collide on the primary key. Expected: it just works, once. → Task 7, step "first-time buyer" test.
2. **A message body containing `"` and `<script>` reaching the browser through the initial history handoff.** Expected: rendered as literal text, no element created, no attribute broken out of. → Task 9, escaping test.
3. **A cosmetic payload that is invalid *in the database*** (hand-edited, or written before validation tightened) reaching the renderer. Expected: the renderer refuses it and falls back, because `style="..."` is a CSS context where HTML escaping does nothing. → Task 3, render-side rejection test.
4. **A gag or ban whose timestamp has just expired.** Expected: the member can speak again with no sweep, no cron, no cache bust — the predicate is evaluated per request. → Task 2, expiry tests.
5. **Redis being down or the history key evicted.** Expected: `/chat` still renders from MySQL, excluding soft-deleted rows, and the send path still works. → Task 4, throwing-fake test.

---

## File Structure

**Created in ahd-admin** (separate repo, separate commit — Task 1a):

| File | Responsibility |
|---|---|
| `database/migrations/2026_11_07_000001_create_chat_tables.php` | The six real tables, with real FKs |

**Created in ahd-v2:**

| File | Responsibility |
|---|---|
| `app/Models/ChatMember.php` | Rank, sanctions, balance; the `isBanned()` / `isGagged()` / `isKicked()` predicates |
| `app/Models/ChatMessage.php` | One message, soft-deletable |
| `app/Models/ChatCosmetic.php` | Catalogue item + its slot |
| `app/Models/ChatCosmeticMember.php` | Inventory row |
| `app/Models/ChatTransaction.php` | Coin ledger row |
| `app/Models/ChatModerationLog.php` | Audit row |
| `app/Enums/ChatRank.php` | The rank ladder and its comparison |
| `app/Enums/CosmeticType.php` | The six types and their slot mapping |
| `app/Policies/ChatPolicy.php` | The strictly-lower-rank rule, used by both entry points |
| `app/Support/CosmeticStyle.php` | Validate-on-render → inline custom properties |
| `app/Support/ChatText.php` | Control-character and bidi strip |
| `app/Rules/CosmeticPayload.php` | Admin-side payload validation |
| `app/Contracts/ChatHistory.php` | The history seam (interface) |
| `app/Support/RedisChatHistory.php` | Redis list implementation |
| `app/Support/ArrayChatHistory.php` | In-memory implementation used by tests |
| `app/Services/Chat/ChatService.php` | Send, read history, delete |
| `app/Services/Chat/ModerationService.php` | Gag, kick, ban, rank, logging |
| `app/Services/Chat/StoreService.php` | Buy, equip, grant coins, grant cosmetic |
| `app/Events/Chat/MessageSent.php` | Broadcast payload for one message |
| `app/Events/Chat/MessageDeleted.php` | Broadcast a deletion |
| `app/Events/Chat/MemberSanctioned.php` | Broadcast gag/kick/ban |
| `app/Http/Controllers/ChatController.php` | `GET /chat`, send, delete |
| `app/Http/Controllers/ChatModerationController.php` | `POST /chat/moderate` |
| `app/Http/Controllers/ChatStoreController.php` | Store, buy, equip |
| `app/Http/Controllers/Admin/ChatManagementController.php` | The dashboard console |
| `routes/channels.php` | Public + presence channel authorization |
| `resources/views/chat.blade.php` | The lobby page |
| `resources/views/components/chat/message.blade.php` | One server-rendered message line |
| `resources/views/components/chat/roster.blade.php` | The online list |
| `resources/js/chat/lobby.js` | The Alpine component; lazily imports Echo |
| `resources/js/pages/admin/Chat.vue` | Admin console |
| `public/chat/pawns/*.svg`, `public/chat/backgrounds/*.webp` | Shipped cosmetic assets |

**Modified in ahd-v2:**

| File | Change |
|---|---|
| `database/migrations/2024_01_01_000005_create_tag_shim_tables.php` | Append the six FK-free sqlite shim tables |
| `bootstrap/app.php` | `->withBroadcasting(...)` so `/broadcasting/auth` exists at all |
| `app/Http/Middleware/SecurityHeaders.php:50-55` | Add `chat` to the cache-skip list |
| `routes/web.php` | Public chat routes + the dashboard chat routes |
| `resources/js/blade.js` | Register the `chatLobby` Alpine component |
| `config/broadcasting.php` | New file from the Reverb install, committed |
| `ecosystem.config.cjs` | The `ahd-reverb` process + header comment |
| `.env.example` | `BROADCAST_CONNECTION`, `REVERB_*`, `VITE_REVERB_*` |

---

## Task 1a: Create the real tables in ahd-admin

This task happens in a **different repository**: `~/Project/akuma-player/ahd-admin`. It has its own commit. Nothing in ahd-v2 changes here.

**Files:**
- Create: `~/Project/akuma-player/ahd-admin/database/migrations/2026_11_07_000001_create_chat_tables.php`

**Interfaces:**
- Consumes: nothing.
- Produces: the six tables every later task's models bind to — `chat_members`, `chat_messages`, `chat_cosmetics`, `chat_cosmetic_member`, `chat_transactions`, `chat_moderation_log`.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the ahd-v2 live chat lobby (/chat).
 *
 * They live here because ahd-v2 owns no schema: its `migrations` table is out
 * of sync with the real database and a bare `migrate` there dies on the users
 * table. ahd-v2 carries read-only models plus an FK-free sqlite shim for tests;
 * this file is the authority for column names and types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_members', function (Blueprint $table): void {
            $table->unsignedBigInteger('member_id')->primary();
            $table->enum('rank', ['member', 'mod', 'owner', 'main_owner'])->default('member');
            $table->unsignedInteger('balance')->default(0);
            $table->timestamp('gagged_until')->nullable();
            $table->timestamp('kicked_until')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('banned_until')->nullable();
            $table->string('ban_reason')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->text('body');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->unsignedBigInteger('deleted_by_admin_id')->nullable();

            $table->index('created_at');
            $table->index('member_id');
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });

        Schema::create('chat_cosmetics', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['name_color', 'name_gradient', 'glow', 'pawn', 'smiley', 'background']);
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('payload');
            $table->unsignedInteger('price')->default(0);
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('chat_cosmetic_member', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('cosmetic_id');
            $table->timestamp('acquired_at')->nullable();
            $table->enum('source', ['purchase', 'grant'])->default('purchase');
            $table->boolean('equipped')->default(false);

            $table->unique(['member_id', 'cosmetic_id']);
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('cosmetic_id')->references('id')->on('chat_cosmetics')->restrictOnDelete();
        });

        Schema::create('chat_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->integer('delta');
            $table->unsignedInteger('balance_after');
            $table->enum('reason', ['admin_grant', 'purchase']);
            $table->unsignedBigInteger('cosmetic_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['member_id', 'created_at']);
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });

        Schema::create('chat_moderation_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_member_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('target_member_id');
            $table->enum('action', [
                'gag', 'ungag', 'kick', 'ban', 'unban',
                'delete_message', 'rank_change', 'grant_coins', 'grant_cosmetic',
            ]);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['target_member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_log');
        Schema::dropIfExists('chat_transactions');
        Schema::dropIfExists('chat_cosmetic_member');
        Schema::dropIfExists('chat_cosmetics');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_members');
    }
};
```

- [ ] **Step 2: Dry-run it**

Run, in the ahd-admin repo:
`php artisan migrate --pretend --path=database/migrations/2026_11_07_000001_create_chat_tables.php`
Expected: six `create table` statements printed, no error, nothing executed.

- [ ] **Step 3: Run it locally**

Run: `php artisan migrate --path=database/migrations/2026_11_07_000001_create_chat_tables.php`
Expected: `DONE`.

- [ ] **Step 4: Commit (in ahd-admin)**

```bash
git add database/migrations/2026_11_07_000001_create_chat_tables.php
git commit -m "feat(chat): add the tables for the ahd-v2 chat lobby"
```

- [ ] **Step 5: Hand the production run to the operator**

Do **not** run this against production yourself. Write in the hand-off notes: the operator runs, on the box, in `/www/wwwroot/admin.animehdzero.com`:
`php84 artisan migrate --force --path=database/migrations/2026_11_07_000001_create_chat_tables.php`
**This must happen before ahd-v2 deploys**, or `/chat` 500s on first load.

---

## Task 1b: Models and the sqlite test schema

**Files:**
- Modify: `database/migrations/2024_01_01_000005_create_tag_shim_tables.php` (append; it already ends with a `down()`)
- Create: `app/Enums/ChatRank.php`, `app/Enums/CosmeticType.php`
- Create: `app/Models/ChatMember.php`, `ChatMessage.php`, `ChatCosmetic.php`, `ChatCosmeticMember.php`, `ChatTransaction.php`, `ChatModerationLog.php`
- Test: `tests/Feature/Chat/ChatSchemaTest.php`

**Interfaces:**
- Consumes: the table names from Task 1a.
- Produces: `ChatRank` (cases `Member`, `Mod`, `Owner`, `MainOwner`; `level(): int`; `outranks(ChatRank $other): bool`), `CosmeticType` (cases for the six types; `slot(): ?string`), and the six models. `ChatMember::$casts` makes `rank` a `ChatRank` and the four timestamps `datetime`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatRank;
use App\Models\ChatMember;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chat_member_row_defaults_to_the_member_rank_and_no_coins(): void
    {
        $member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);

        $chatMember = ChatMember::create(['member_id' => $member->id]);

        $this->assertSame(ChatRank::Member, $chatMember->fresh()->rank);
        $this->assertSame(0, $chatMember->fresh()->balance);
    }

    public function test_the_rank_ladder_only_outranks_downwards(): void
    {
        $this->assertTrue(ChatRank::Owner->outranks(ChatRank::Mod));
        $this->assertFalse(ChatRank::Mod->outranks(ChatRank::Mod));
        $this->assertFalse(ChatRank::Mod->outranks(ChatRank::Owner));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter=ChatSchemaTest`
Expected: FAIL — `Class "App\Enums\ChatRank" not found`.

- [ ] **Step 3: Write the enums**

`app/Enums/ChatRank.php`:

```php
<?php

namespace App\Enums;

/**
 * The chat rank ladder. The whole moderation model is one rule — an actor may
 * only act on a strictly lower rank — so comparison lives here rather than
 * being re-derived at each call site.
 */
enum ChatRank: string
{
    case Member = 'member';
    case Mod = 'mod';
    case Owner = 'owner';
    case MainOwner = 'main_owner';

    public function level(): int
    {
        return match ($this) {
            self::Member => 1,
            self::Mod => 2,
            self::Owner => 3,
            self::MainOwner => 4,
        };
    }

    public function outranks(self $other): bool
    {
        return $this->level() > $other->level();
    }
}
```

`app/Enums/CosmeticType.php`:

```php
<?php

namespace App\Enums;

/**
 * Cosmetic types and the equip slot each one occupies.
 *
 * Exclusivity belongs to the SLOT, not the type: name_color and name_gradient
 * both occupy `name`, so equipping one clears the other and two name styles can
 * never fight. A smiley has no slot — owning it makes it usable.
 */
enum CosmeticType: string
{
    case NameColor = 'name_color';
    case NameGradient = 'name_gradient';
    case Glow = 'glow';
    case Pawn = 'pawn';
    case Smiley = 'smiley';
    case Background = 'background';

    public function slot(): ?string
    {
        return match ($this) {
            self::NameColor, self::NameGradient => 'name',
            self::Glow => 'effect',
            self::Pawn => 'pawn',
            self::Background => 'background',
            self::Smiley => null,
        };
    }
}
```

- [ ] **Step 4: Append the sqlite shim**

Append inside the existing `up()` of `database/migrations/2024_01_01_000005_create_tag_shim_tables.php`, after the `taggables` block, and add the matching `dropIfExists` lines to `down()`:

```php
        // Chat lobby tables. The real ones are created in ahd-admin
        // (2026_11_07_000001_create_chat_tables.php); these exist only so the
        // in-memory sqlite test DB has them.
        //
        // NO FOREIGN KEYS HERE: this file runs before
        // 2026_03_22_000001_create_members_table, so `members` does not exist
        // yet and a constrained() column would fail the whole test suite.
        if (! Schema::hasTable('chat_members')) {
            Schema::create('chat_members', function (Blueprint $table): void {
                $table->unsignedBigInteger('member_id')->primary();
                $table->string('rank')->default('member');
                $table->unsignedInteger('balance')->default(0);
                $table->timestamp('gagged_until')->nullable();
                $table->timestamp('kicked_until')->nullable();
                $table->timestamp('banned_at')->nullable();
                $table->timestamp('banned_until')->nullable();
                $table->string('ban_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->text('body');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->unsignedBigInteger('deleted_by_admin_id')->nullable();
                $table->index('created_at');
                $table->index('member_id');
            });
        }

        if (! Schema::hasTable('chat_cosmetics')) {
            Schema::create('chat_cosmetics', function (Blueprint $table): void {
                $table->id();
                $table->string('type');
                $table->string('name');
                $table->string('slug')->unique();
                $table->json('payload');
                $table->unsignedInteger('price')->default(0);
                $table->boolean('active')->default(true);
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_cosmetic_member')) {
            Schema::create('chat_cosmetic_member', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->unsignedBigInteger('cosmetic_id');
                $table->timestamp('acquired_at')->nullable();
                $table->string('source')->default('purchase');
                $table->boolean('equipped')->default(false);
                $table->unique(['member_id', 'cosmetic_id']);
            });
        }

        if (! Schema::hasTable('chat_transactions')) {
            Schema::create('chat_transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->integer('delta');
                $table->unsignedInteger('balance_after');
                $table->string('reason');
                $table->unsignedBigInteger('cosmetic_id')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('note')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['member_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('chat_moderation_log')) {
            Schema::create('chat_moderation_log', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('actor_member_id')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->unsignedBigInteger('target_member_id');
                $table->string('action');
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['target_member_id', 'created_at']);
            });
        }
```

- [ ] **Step 5: Write the models**

`app/Models/ChatMember.php`:

```php
<?php

namespace App\Models;

use App\Enums\ChatRank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One participant's chat state: rank, sanctions and coin balance.
 *
 * Kept off `members` because this app does not own that table, and because a
 * site ban and a chat ban are different things — see isBanned().
 *
 * @property int $member_id
 * @property ChatRank $rank
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $gagged_until
 * @property \Illuminate\Support\Carbon|null $kicked_until
 * @property \Illuminate\Support\Carbon|null $banned_at
 * @property \Illuminate\Support\Carbon|null $banned_until
 * @property string|null $ban_reason
 */
class ChatMember extends Model
{
    protected $table = 'chat_members';

    protected $primaryKey = 'member_id';

    public $incrementing = false;

    protected $fillable = [
        'member_id', 'rank', 'balance',
        'gagged_until', 'kicked_until', 'banned_at', 'banned_until', 'ban_reason',
    ];

    protected $casts = [
        'rank' => ChatRank::class,
        'balance' => 'integer',
        'gagged_until' => 'datetime',
        'kicked_until' => 'datetime',
        'banned_at' => 'datetime',
        'banned_until' => 'datetime',
    ];

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    /**
     * The one definition of an active ban. A timed ban that has run out is not
     * an active ban, and nothing sweeps the table to make that true — the
     * predicate is evaluated per request, so expiry needs no cron.
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null
            && ($this->banned_until === null || $this->banned_until->isFuture());
    }

    public function isGagged(): bool
    {
        return $this->gagged_until !== null && $this->gagged_until->isFuture();
    }

    public function isKicked(): bool
    {
        return $this->kicked_until !== null && $this->kicked_until->isFuture();
    }

    /** May this member speak right now? */
    public function canSpeak(): bool
    {
        return ! $this->isBanned() && ! $this->isGagged() && ! $this->isKicked();
    }
}
```

`app/Models/ChatMessage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line of chat. Soft-deleted: a moderator removing a message must not
 * destroy the evidence of what was removed.
 *
 * @property int $id
 * @property int $member_id
 * @property string $body
 */
class ChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'chat_messages';

    public const UPDATED_AT = null;

    protected $fillable = ['member_id', 'body'];

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }
}
```

`app/Models/ChatCosmetic.php`:

```php
<?php

namespace App\Models;

use App\Enums\CosmeticType;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-authored cosmetic. Members never create these — see
 * App\Support\CosmeticStyle for why that is a security boundary.
 *
 * @property int $id
 * @property CosmeticType $type
 * @property string $name
 * @property string $slug
 * @property array<string, mixed> $payload
 * @property int $price
 * @property bool $active
 */
class ChatCosmetic extends Model
{
    protected $table = 'chat_cosmetics';

    protected $fillable = ['type', 'name', 'slug', 'payload', 'price', 'active', 'sort'];

    protected $casts = [
        'type' => CosmeticType::class,
        'payload' => 'array',
        'price' => 'integer',
        'active' => 'boolean',
        'sort' => 'integer',
    ];
}
```

`app/Models/ChatCosmeticMember.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inventory row: this member owns this cosmetic.
 *
 * The unique index on (member_id, cosmetic_id) is what makes a double-clicked
 * purchase a no-op instead of a double charge.
 *
 * @property int $id
 * @property int $member_id
 * @property int $cosmetic_id
 * @property bool $equipped
 * @property string $source
 */
class ChatCosmeticMember extends Model
{
    protected $table = 'chat_cosmetic_member';

    public $timestamps = false;

    protected $fillable = ['member_id', 'cosmetic_id', 'acquired_at', 'source', 'equipped'];

    protected $casts = [
        'acquired_at' => 'datetime',
        'equipped' => 'boolean',
    ];

    /**
     * @return BelongsTo<ChatCosmetic, $this>
     */
    public function cosmetic(): BelongsTo
    {
        return $this->belongsTo(ChatCosmetic::class, 'cosmetic_id');
    }
}
```

`app/Models/ChatTransaction.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only coin ledger. Every balance change writes one of these in the
 * same DB transaction as the balance itself, so the ledger always
 * reconstructs the balance.
 *
 * @property int $id
 * @property int $member_id
 * @property int $delta
 * @property int $balance_after
 * @property string $reason
 */
class ChatTransaction extends Model
{
    protected $table = 'chat_transactions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'member_id', 'delta', 'balance_after', 'reason', 'cosmetic_id', 'admin_id', 'note',
    ];

    protected $casts = [
        'delta' => 'integer',
        'balance_after' => 'integer',
    ];
}
```

`app/Models/ChatModerationLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit row for every moderator and admin action. Exactly one of
 * actor_member_id (in-room moderator) / admin_id (dashboard) is set.
 *
 * @property int $id
 * @property string $action
 * @property array<string, mixed>|null $meta
 */
class ChatModerationLog extends Model
{
    protected $table = 'chat_moderation_log';

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_member_id', 'admin_id', 'target_member_id', 'action', 'meta',
    ];

    protected $casts = ['meta' => 'array'];
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter=ChatSchemaTest`
Expected: PASS, 2 tests.

- [ ] **Step 7: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: every existing test still green. If the shim broke migration order you will see failures far from chat — that is the FK trap in the Global Constraints.

- [ ] **Step 8: Commit**

```bash
./vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
git add app/Enums app/Models database/migrations/2024_01_01_000005_create_tag_shim_tables.php tests/Feature/Chat/ChatSchemaTest.php
git commit -m "feat(chat): add chat models and the sqlite test schema"
```

---

## Task 2: The rank rule

**Files:**
- Create: `app/Policies/ChatPolicy.php`
- Test: `tests/Unit/Chat/ChatPolicyTest.php`, `tests/Feature/Chat/ChatMemberSanctionTest.php`

**Interfaces:**
- Consumes: `ChatRank` from Task 1b.
- Produces: `ChatPolicy::canActOn(ChatRank $actor, ChatRank $target): bool`,
  `ChatPolicy::canGag(ChatRank): bool`, `canKick(ChatRank): bool`, `canBan(ChatRank): bool`,
  `canSetRank(ChatRank $actor, ChatRank $newRank): bool`,
  `canDeleteMessage(ChatRank $actor, ChatRank $author, bool $isOwnMessage): bool`.
  Tasks 5, 6 and 10 all call these and nothing else.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Chat;

use App\Enums\ChatRank;
use App\Policies\ChatPolicy;
use Tests\TestCase;

class ChatPolicyTest extends TestCase
{
    private ChatPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ChatPolicy;
    }

    public function test_equal_rank_may_not_act(): void
    {
        $this->assertFalse($this->policy->canActOn(ChatRank::Mod, ChatRank::Mod));
        $this->assertFalse($this->policy->canActOn(ChatRank::MainOwner, ChatRank::MainOwner));
    }

    public function test_higher_rank_acts_on_lower(): void
    {
        $this->assertTrue($this->policy->canActOn(ChatRank::Mod, ChatRank::Member));
        $this->assertTrue($this->policy->canActOn(ChatRank::Owner, ChatRank::Mod));
        $this->assertFalse($this->policy->canActOn(ChatRank::Member, ChatRank::Mod));
    }

    public function test_only_owners_and_above_may_ban(): void
    {
        $this->assertFalse($this->policy->canBan(ChatRank::Mod));
        $this->assertTrue($this->policy->canBan(ChatRank::Owner));
        $this->assertTrue($this->policy->canBan(ChatRank::MainOwner));
    }

    public function test_mods_may_gag_and_kick_but_members_may_not(): void
    {
        $this->assertTrue($this->policy->canGag(ChatRank::Mod));
        $this->assertTrue($this->policy->canKick(ChatRank::Mod));
        $this->assertFalse($this->policy->canGag(ChatRank::Member));
        $this->assertFalse($this->policy->canKick(ChatRank::Member));
    }

    public function test_an_owner_may_make_mods_but_only_a_main_owner_may_make_owners(): void
    {
        $this->assertTrue($this->policy->canSetRank(ChatRank::Owner, ChatRank::Mod));
        $this->assertFalse($this->policy->canSetRank(ChatRank::Owner, ChatRank::Owner));
        $this->assertTrue($this->policy->canSetRank(ChatRank::MainOwner, ChatRank::Owner));
        $this->assertFalse($this->policy->canSetRank(ChatRank::MainOwner, ChatRank::MainOwner));
    }

    public function test_anyone_may_delete_their_own_message_and_a_mod_may_not_delete_a_mods(): void
    {
        $this->assertTrue($this->policy->canDeleteMessage(ChatRank::Member, ChatRank::Member, true));
        $this->assertTrue($this->policy->canDeleteMessage(ChatRank::Mod, ChatRank::Member, false));
        $this->assertFalse($this->policy->canDeleteMessage(ChatRank::Mod, ChatRank::Mod, false));
        $this->assertFalse($this->policy->canDeleteMessage(ChatRank::Member, ChatRank::Member, false));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter=ChatPolicyTest`
Expected: FAIL — `Class "App\Policies\ChatPolicy" not found`.

- [ ] **Step 3: Write the policy**

```php
<?php

namespace App\Policies;

use App\Enums\ChatRank;

/**
 * Every moderation decision in the chat, in one class.
 *
 * The rule is: an actor may only act on a STRICTLY LOWER rank. Equal rank is
 * refused, which is what stops a mod gagging a mod. Both entry points — the
 * in-room POST /chat/moderate and the dashboard — call these methods rather
 * than re-deriving the comparison, so there is one place to get it wrong.
 */
class ChatPolicy
{
    public function canActOn(ChatRank $actor, ChatRank $target): bool
    {
        return $actor->outranks($target);
    }

    public function canGag(ChatRank $actor): bool
    {
        return $actor->level() >= ChatRank::Mod->level();
    }

    public function canKick(ChatRank $actor): bool
    {
        return $actor->level() >= ChatRank::Mod->level();
    }

    public function canBan(ChatRank $actor): bool
    {
        return $actor->level() >= ChatRank::Owner->level();
    }

    /**
     * Promoting is capped one rung below the actor: an owner makes mods, a
     * main owner makes owners. Nobody mints their own rank.
     */
    public function canSetRank(ChatRank $actor, ChatRank $newRank): bool
    {
        return $actor->outranks($newRank);
    }

    /**
     * Deleting is judged on the MESSAGE AUTHOR's rank, with one addition: you
     * may always delete your own message.
     */
    public function canDeleteMessage(ChatRank $actor, ChatRank $author, bool $isOwnMessage): bool
    {
        return $isOwnMessage || $this->canActOn($actor, $author);
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit --filter=ChatPolicyTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Write the sanction-expiry test** (Review Focus #4)

```php
<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMember;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMemberSanctionTest extends TestCase
{
    use RefreshDatabase;

    private function chatMember(array $attributes = []): ChatMember
    {
        $member = Member::create([
            'name' => 'Rin',
            'email' => 'rin'.uniqid().'@example.test',
            'password' => 'password123!',
        ]);

        return ChatMember::create(['member_id' => $member->id] + $attributes);
    }

    public function test_a_permanent_ban_is_active(): void
    {
        $this->assertTrue($this->chatMember(['banned_at' => now()])->isBanned());
    }

    public function test_a_timed_ban_is_active_until_it_expires(): void
    {
        $this->assertTrue($this->chatMember([
            'banned_at' => now(),
            'banned_until' => now()->addMinutes(5),
        ])->isBanned());
    }

    public function test_an_expired_ban_is_not_active_and_needs_no_sweep(): void
    {
        $this->assertFalse($this->chatMember([
            'banned_at' => now()->subDay(),
            'banned_until' => now()->subMinute(),
        ])->isBanned());
    }

    public function test_an_expired_gag_lets_the_member_speak_again(): void
    {
        $chatMember = $this->chatMember(['gagged_until' => now()->subSecond()]);

        $this->assertFalse($chatMember->isGagged());
        $this->assertTrue($chatMember->canSpeak());
    }

    public function test_a_live_kick_blocks_speaking(): void
    {
        $chatMember = $this->chatMember(['kicked_until' => now()->addSeconds(60)]);

        $this->assertTrue($chatMember->isKicked());
        $this->assertFalse($chatMember->canSpeak());
    }
}
```

- [ ] **Step 6: Run it**

Run: `vendor/bin/phpunit --filter=ChatMemberSanctionTest`
Expected: PASS, 5 tests. (The predicates were written in Task 1b; this is the test that pins them.)

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
git add app/Policies/ChatPolicy.php tests/Unit/Chat/ChatPolicyTest.php tests/Feature/Chat/ChatMemberSanctionTest.php
git commit -m "feat(chat): add the rank policy and pin sanction expiry"
```

---

## Task 3: Cosmetic payloads, validated on the way out

**Files:**
- Create: `app/Support/CosmeticStyle.php`, `app/Rules/CosmeticPayload.php`
- Create: `public/chat/pawns/default.svg`, `public/chat/pawns/gold.svg`, `public/chat/backgrounds/night.webp`
- Test: `tests/Unit/Chat/CosmeticStyleTest.php`

**Interfaces:**
- Consumes: `CosmeticType` from Task 1b.
- Produces: `CosmeticStyle::for(ChatCosmetic $cosmetic): array<string, string>` — a map of CSS custom property names to values, empty when the payload is invalid — and `CosmeticStyle::assetUrl(CosmeticType $type, string $slug): ?string`. Task 9 renders with these; Task 10 validates admin input with `CosmeticPayload`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Chat;

use App\Enums\CosmeticType;
use App\Models\ChatCosmetic;
use App\Support\CosmeticStyle;
use Tests\TestCase;

class CosmeticStyleTest extends TestCase
{
    private function cosmetic(CosmeticType $type, array $payload): ChatCosmetic
    {
        $cosmetic = new ChatCosmetic;
        $cosmetic->type = $type;
        $cosmetic->payload = $payload;

        return $cosmetic;
    }

    public function test_a_valid_colour_becomes_a_custom_property(): void
    {
        $style = CosmeticStyle::for($this->cosmetic(CosmeticType::NameColor, ['color' => '#ff8800']));

        $this->assertSame(['--chat-name-color' => '#ff8800'], $style);
    }

    public function test_a_gradient_needs_both_stops_and_an_angle(): void
    {
        $style = CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
            'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 90,
        ]));

        $this->assertSame('linear-gradient(90deg, #ff0000, #0000ff)', $style['--chat-name-gradient']);
    }

    /**
     * Review Focus #3. The row can be wrong — hand-edited, or written before
     * validation tightened — and style="..." is a CSS context where HTML
     * escaping does nothing. The renderer itself has to refuse.
     */
    public function test_a_payload_that_is_invalid_in_the_database_is_refused_at_render(): void
    {
        $injection = $this->cosmetic(CosmeticType::NameColor, [
            'color' => 'red; background: url(javascript:alert(1))',
        ]);

        $this->assertSame([], CosmeticStyle::for($injection));
    }

    public function test_an_out_of_range_gradient_angle_is_refused(): void
    {
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
            'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 999,
        ])));
    }

    public function test_an_asset_slug_cannot_traverse_out_of_its_directory(): void
    {
        $this->assertNull(CosmeticStyle::assetUrl(CosmeticType::Pawn, '../../../etc/passwd'));
        $this->assertNull(CosmeticStyle::assetUrl(CosmeticType::Pawn, 'does-not-exist'));
        $this->assertSame('/chat/pawns/default.svg', CosmeticStyle::assetUrl(CosmeticType::Pawn, 'default'));
    }

    public function test_a_smiley_code_must_look_like_a_code(): void
    {
        $this->assertTrue(CosmeticStyle::isValidSmileyCode('(akuma)'));
        $this->assertFalse(CosmeticStyle::isValidSmileyCode('<img src=x>'));
        $this->assertFalse(CosmeticStyle::isValidSmileyCode('(a)'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter=CosmeticStyleTest`
Expected: FAIL — `Class "App\Support\CosmeticStyle" not found`.

- [ ] **Step 3: Write the helper**

```php
<?php

namespace App\Support;

use App\Enums\CosmeticType;
use App\Models\ChatCosmetic;

use function is_int;
use function is_string;

/**
 * Turns a cosmetic's stored payload into inline CSS custom properties.
 *
 * It re-validates on every render, deliberately. Validating only at admin save
 * time assumes the row cannot change afterwards, and a value that reaches
 * style="..." sits in a CSS context — HTML escaping does nothing there, so
 * `red; background: url(...)` would sail through. The rule is that this class
 * refuses anything it cannot prove safe and returns an empty map, which renders
 * as the default style.
 *
 * Asset slugs are matched against a strict pattern AND checked for existence in
 * a fixed directory, so a payload cannot name a file outside it.
 */
class CosmeticStyle
{
    private const HEX = '/^#[0-9a-f]{6}$/i';

    private const SLUG = '/^[a-z0-9-]{1,32}$/';

    private const SMILEY_CODE = '/^\(\w{2,12}\)$/';

    /**
     * @return array<string, string>
     */
    public static function for(ChatCosmetic $cosmetic): array
    {
        $payload = $cosmetic->payload;

        return match ($cosmetic->type) {
            CosmeticType::NameColor => self::hex($payload['color'] ?? null) !== null
                ? ['--chat-name-color' => (string) $payload['color']]
                : [],

            CosmeticType::NameGradient => self::gradient($payload),

            CosmeticType::Glow => self::glow($payload),

            CosmeticType::Pawn, CosmeticType::Background => self::asset($cosmetic),

            CosmeticType::Smiley => [],
        };
    }

    public static function assetUrl(CosmeticType $type, string $slug): ?string
    {
        $directory = match ($type) {
            CosmeticType::Pawn => 'pawns',
            CosmeticType::Background => 'backgrounds',
            default => null,
        };

        if ($directory === null || preg_match(self::SLUG, $slug) !== 1) {
            return null;
        }

        $extension = $type === CosmeticType::Pawn ? 'svg' : 'webp';
        $relative = "/chat/{$directory}/{$slug}.{$extension}";

        return is_file(public_path($relative)) ? $relative : null;
    }

    public static function isValidSmileyCode(string $code): bool
    {
        return preg_match(self::SMILEY_CODE, $code) === 1;
    }

    private static function hex(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::HEX, $value) === 1 ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function gradient(array $payload): array
    {
        $from = self::hex($payload['from'] ?? null);
        $to = self::hex($payload['to'] ?? null);
        $angle = $payload['angle'] ?? null;

        if ($from === null || $to === null || ! is_int($angle) || $angle < 0 || $angle > 360) {
            return [];
        }

        return ['--chat-name-gradient' => "linear-gradient({$angle}deg, {$from}, {$to})"];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function glow(array $payload): array
    {
        $color = self::hex($payload['color'] ?? null);
        $strength = $payload['strength'] ?? null;

        if ($color === null || ! is_int($strength) || $strength < 1 || $strength > 3) {
            return [];
        }

        $radius = $strength * 4;

        return ['--chat-glow' => "0 0 {$radius}px {$color}"];
    }

    /**
     * @return array<string, string>
     */
    private static function asset(ChatCosmetic $cosmetic): array
    {
        $slug = $cosmetic->payload['asset'] ?? null;

        if (! is_string($slug)) {
            return [];
        }

        $url = self::assetUrl($cosmetic->type, $slug);

        if ($url === null) {
            return [];
        }

        $property = $cosmetic->type === CosmeticType::Pawn ? '--chat-pawn' : '--chat-background';

        return [$property => "url('{$url}')"];
    }
}
```

- [ ] **Step 4: Add the shipped assets**

Create `public/chat/pawns/default.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16"><circle cx="8" cy="5" r="3" fill="currentColor"/><path d="M3 14a5 5 0 0 1 10 0z" fill="currentColor"/></svg>
```

Create `public/chat/pawns/gold.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16"><circle cx="8" cy="5" r="3" fill="#f5c518"/><path d="M3 14a5 5 0 0 1 10 0z" fill="#f5c518"/></svg>
```

For `public/chat/backgrounds/night.webp`, copy any existing small webp in `public/` to that path as a placeholder — the test only asserts that `assetUrl` finds a file that exists:

```bash
mkdir -p public/chat/backgrounds public/chat/pawns
cp public/cwv-poster.jpg public/chat/backgrounds/night.webp
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter=CosmeticStyleTest`
Expected: PASS, 6 tests.

- [ ] **Step 6: Write the admin-side validation rule**

```php
<?php

namespace App\Rules;

use App\Enums\CosmeticType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use function is_array;

/**
 * Validates a cosmetic payload at admin save time.
 *
 * This is the FIRST of two gates, not the only one: App\Support\CosmeticStyle
 * re-checks at render. This rule exists so the admin gets a useful error
 * message, not so the renderer can trust the row.
 */
class CosmeticPayload implements ValidationRule
{
    public function __construct(private readonly CosmeticType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The payload must be an object.');

            return;
        }

        $cosmetic = new \App\Models\ChatCosmetic;
        $cosmetic->type = $this->type;
        $cosmetic->payload = $value;

        if ($this->type === CosmeticType::Smiley) {
            $code = $value['code'] ?? '';
            $asset = $value['asset'] ?? '';

            if (! is_string($code) || ! \App\Support\CosmeticStyle::isValidSmileyCode($code)) {
                $fail('The smiley code must look like (name), 2–12 word characters.');
            }

            if (! is_string($asset) || \App\Support\CosmeticStyle::assetUrl(CosmeticType::Pawn, $asset) === null) {
                $fail('The smiley asset must be a file shipped in public/chat/pawns.');
            }

            return;
        }

        if (\App\Support\CosmeticStyle::for($cosmetic) === []) {
            $fail('The payload is not valid for this cosmetic type.');
        }
    }
}
```

- [ ] **Step 7: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
./vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
git add app/Support/CosmeticStyle.php app/Rules/CosmeticPayload.php public/chat tests/Unit/Chat/CosmeticStyleTest.php
git commit -m "feat(chat): validate cosmetic payloads on the way out, not just in"
```

---
