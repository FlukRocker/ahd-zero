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
