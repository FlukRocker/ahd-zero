# Live Chat Lobby — Design

**Date:** 2026-11-07
**Status:** Draft — awaiting review

## Problem

The site has members (`member` guard: registration, email verification, settings, comments,
bookmarks) but nowhere for them to talk to each other in real time. Comments are per-anime and
asynchronous; there is no shared room.

The ask is a xat-style chat: one room, a visible rank hierarchy with in-room moderation, cosmetic
items that members own and equip, and an admin console behind the existing dashboard.

xat itself is five subsystems — rooms, ranks/moderation, cosmetics, an economy, and an admin
console. This spec covers **v1 only**: a single global lobby at `/chat` with ranks, moderation,
cosmetics, and an admin-granted currency. Multi-room, private chat, trading and real-money top-up
are named in "Out of scope" and each becomes its own later spec.

## Decisions taken before this spec

| Question | Answer |
|---|---|
| Where | Inside ahd-v2, `member` guard, shared Redis |
| Transport | Laravel Reverb (self-hosted, PM2-supervised) |
| v1 scope | Single lobby + ranks/moderation + cosmetics/inventory + currency/store |
| Currency source | Admin grant only — no earning loop, no payment, no trading |

## Constraints this design works around

1. **No new migration files in ahd-v2.** Schema is owned by ahd-admin and this repo's `migrations`
   table is out of sync with the real database. The six new tables are created in **ahd-admin**;
   ahd-v2 gets Eloquent models plus a `Schema::hasTable()`-guarded shim appended to an existing
   `2024_01_01_*` file so the in-memory sqlite test schema has them.
2. **No Node runtime in production.** The public site is Blade + Alpine served by php-fpm/nginx.
   The chat page is Blade, not Vue; only the gated admin page is Inertia, matching the existing
   split. Reverb is PHP, so it does not break this rule.
3. **php-fpm cannot hold open connections.** A worker per connected user exhausts the pool, which
   rules out SSE and long-polling. Hence a separate socket daemon.
4. **Redis is shared** with kurokami, lnw-anime and neko_miku. Every key below carries the app
   prefix; nothing in this design flushes or scans outside it.
5. **Cron on the production box is broken** (root crontab line 3 has four time fields, so Debian
   discards the whole file). Reverb is therefore supervised by PM2, which is already installed and
   already the documented place for long-running processes. The crontab still needs fixing for the
   scheduler, but this feature does not depend on it.

## Architecture

```
browser ──POST /chat/messages──► Laravel ─┬─ validate · rank/gag/ban check · persist
                                          └─ broadcast(MessageSent)
                                                   │
browser ◄────── wss ──────────────  Reverb daemon (PM2, nginx proxies /app)
```

**Clients never write to the socket.** Sending is an ordinary HTTP POST so it passes through auth,
validation, the rate limiter and the gag/ban check. The socket is push-only, plus presence for the
online roster and whispers for the typing indicator.

Two channels:

- `chat.lobby` — **public**. Carries every message, deletion and sanction notice. Guests and banned
  members may subscribe; both are read-only, which is the behaviour we want and costs no extra code.
- `presence-chat.lobby` — **presence, members only**. The online roster with each member's rank and
  equipped cosmetics. The channel authorisation callback rejects banned members, so a ban removes
  someone from the roster without a second mechanism.

Nothing sensitive is broadcast on either channel: no email, no member id other than the public uuid.

### History

The last 200 messages live in a Redis list (`LPUSH` + `LTRIM`), and the page renders the newest 50
from it. `chat_messages` is the durable record behind it, used for moderation, audit and rebuild.
If the Redis key is missing the page falls back to the table — a cold cache is slower, never broken.

## Data model

Six tables, created in **ahd-admin**.

### `chat_members`

One row per participant. Keeps rank, sanctions and wallet off the `members` table, which this app
does not own.

| column | notes |
|---|---|
| `member_id` | PK, uuid, FK → `members.id` |
| `rank` | enum: `member`, `mod`, `owner`, `main_owner`; default `member` (guest is the absence of an account, never a row) |
| `balance` | unsigned int, coins, default 0 |
| `gagged_until` | nullable timestamp |
| `kicked_until` | nullable timestamp — short rejoin block after a kick |
| `banned_at` | nullable timestamp — set for any ban, timed or not |
| `banned_until` | nullable timestamp — null while `banned_at` is set means permanent |
| `ban_reason` | nullable string |
| `created_at` / `updated_at` | |

A member with no row is treated as rank `member` with a zero balance; the row is created on first
send. Guests are not members and never get a row.

### `chat_messages`

`id`, `member_id`, `body` (text, ≤ 500 chars), `created_at`, `deleted_at`, `deleted_by`.
Index on `created_at`, index on `member_id` for "delete everything this user posted".
Deletion is soft — a moderator removing a message must not destroy the evidence.

### `chat_cosmetics`

The admin-authored catalogue. `id`, `type`, `name`, `slug` (unique), `payload` json, `price`
unsigned int, `active` bool, `sort` int.

Types: `name_color`, `name_gradient`, `glow`, `pawn`, `smiley`, `background`.

### `chat_cosmetic_member`

Inventory. `id`, `member_id`, `cosmetic_id`, `acquired_at`, `source` (`purchase` | `grant`),
`equipped` bool. **`unique(member_id, cosmetic_id)`** — this index is what makes a double-click a
no-op instead of a double charge.

### `chat_transactions`

Append-only ledger. `id`, `member_id`, `delta` (signed), `balance_after`, `reason`
(`admin_grant` | `purchase` | `refund`), `cosmetic_id` nullable, `admin_id` nullable, `note`,
`created_at`. Every balance change writes a row in the same DB transaction as the balance update;
the ledger must always reconstruct the balance.

### `chat_moderation_log`

`id`, `actor_member_id` (nullable — a site admin acts as `admin_id`), `admin_id` nullable,
`target_member_id`, `action` (`gag` | `ungag` | `kick` | `ban` | `unban` | `delete_message` |
`rank_change` | `grant`), `meta` json, `created_at`.

## Ranks and moderation

```
guest(0) < member(1) < mod(2) < owner(3) < main_owner(4)
```

Site admins (the `web` guard, i.e. the existing dashboard users) are `main_owner` implicitly and
are not stored in `chat_members`.

**One rule, in one place:** an actor may only act on a target of *strictly lower* rank. A mod cannot
gag a mod. This lives in `App\Policies\ChatPolicy` and nowhere else.

| rank | may |
|---|---|
| guest | read |
| member | read, send |
| mod | + gag (timed mute), kick, delete message |
| owner | + ban (timed or permanent), unban, promote/demote to mod |
| main_owner | + promote/demote owner |

Granting coins is **not** a chat rank power — it belongs to site admins in the dashboard only.

Sending requires an authenticated member with a verified email, matching the existing comment rule.

Enforcement happens twice on purpose: `POST /chat/messages` rejects gagged and banned senders, and
the presence channel authorisation rejects banned members. Neither alone is sufficient — the first
without the second leaves a banned user visible in the roster, the second without the first trusts
the socket.

A kick disconnects the client and sets `kicked_until = now + 60s`; the client's auto-reconnect is
refused until it passes.

## Cosmetics

**Cosmetics are admin-authored, never user-authored. This is a security boundary, not a product
limitation.**

`payload` is a fixed json shape per type, not free-form CSS:

| type | payload | validation |
|---|---|---|
| `name_color` | `{ "color": "#rrggbb" }` | strict hex |
| `name_gradient` | `{ "from": "#rrggbb", "to": "#rrggbb", "angle": 0-360 }` | strict hex + int range |
| `glow` | `{ "color": "#rrggbb", "strength": 1-3 }` | strict hex + enum |
| `pawn` | `{ "asset": "<slug>" }` | must match a file shipped in `public/chat/pawns/` |
| `smiley` | `{ "code": "(name)", "asset": "<slug>" }` | code `^\(\w{2,12}\)$`, asset shipped |
| `background` | `{ "asset": "<slug>" }` | asset shipped |

Colours are arbitrary; **images are not** — there are no uploads in v1, so the admin picks from
assets committed to the repo. Upload support would mean SVG sanitising and is a separate concern.
Rendering goes through one `App\Support\CosmeticStyle` helper that turns a validated payload into a
small set of inline custom properties. No branch of the renderer ever emits a string that came from
a database column unescaped.

Message bodies are escaped. Smiley codes expand to `<img>` **only if the sender owns that smiley**;
otherwise the code stays literal text. The client never uses `innerHTML`: the server broadcasts
structured data (`body` plus the sender's owned smiley map) and the Alpine component builds text
nodes and image elements.

One item equipped per type. Equipping clears the previously equipped item of the same type in the
same transaction.

## Currency and store

Unit: **coins**, integer, never negative.

**Purchase** is a single DB transaction: re-read the balance `FOR UPDATE`, check
`balance >= price`, insert inventory, insert ledger row, decrement balance. Insufficient funds and
an already-owned item both return the store page with an error, not a 500.

**Grant** (dashboard only): amount + note, writes the ledger row and the balance in one
transaction and a `chat_moderation_log` entry with `action = grant`.

Nothing else creates coins. There is no earning loop, no trading and no payment in v1, which is
what keeps the abuse surface at zero.

## Routes

```
GET    /chat                         public — guests read
POST   /chat/messages                auth:member + verified, throttle:20,1
DELETE /chat/messages/{id}           mod+
POST   /chat/moderate                gag | ungag | kick | ban | unban | rank
GET    /chat/store                   auth:member
POST   /chat/store/{cosmetic}/buy    auth:member, throttle:30,1
POST   /chat/cosmetics/{id}/equip    auth:member, throttle:60,1
```

Admin, inside the existing `dashboard` group (web guard):

```
GET    dashboard/chat                            console
POST   dashboard/chat/cosmetics                  create
PATCH  dashboard/chat/cosmetics/{id}             update / toggle active
DELETE dashboard/chat/cosmetics/{id}             delete (keeps inventory rows)
POST   dashboard/chat/members/{id}/grant         coins + note
POST   dashboard/chat/members/{id}/rank          set rank
POST   dashboard/chat/members/{id}/ban           ban / unban
DELETE dashboard/chat/messages/{id}              delete
```

## Components

**Server**

- `App\Models\{ChatMember, ChatMessage, ChatCosmetic, ChatCosmeticMember, ChatTransaction, ChatModerationLog}`
- `App\Services\Chat\ChatService` — send, history, delete
- `App\Services\Chat\ModerationService` — gag, kick, ban, rank, logging
- `App\Services\Chat\StoreService` — buy, equip, grant
- `App\Policies\ChatPolicy` — the rank rule, used by every caller
- `App\Support\CosmeticStyle` — validated payload → inline custom properties
- `App\Events\Chat\{MessageSent, MessageDeleted, MemberSanctioned, RankChanged}`
- `App\Http\Controllers\ChatController`, `ChatStoreController`, `ChatModerationController`
- `App\Http\Controllers\Admin\ChatManagementController`

Each service has one job and is testable without the socket: events are dispatched, not awaited.

**Client**

- `resources/views/chat.blade.php` + `resources/views/components/chat/*.blade.php`
- `resources/js/chat/lobby.js` — the Alpine component, imported by `resources/js/blade.js`.
  It goes in its own module because `blade.js` is already long and chat is the largest interactive
  surface on the public site.
- `laravel-echo` + `pusher-js` added to the Blade entry.
- `resources/js/pages/admin/Chat.vue` — Inertia, following `Members.vue` and `Comments.vue`.

## Error handling

- Socket down → the page still renders history and the composer posts over HTTP; the client shows a
  "reconnecting" state and retries with backoff. Chat degrades to slow, not broken.
- Redis history key missing → fall back to `chat_messages`.
- A rejected send (gagged, banned, throttled, too long) returns a 422 with a message the composer
  shows inline; the client does not optimistically keep the message on screen.
- Broadcast failure is logged, never fatal: the message is already persisted, and a reconnect
  re-reads history.

## Testing

TDD — failing test first, and every fix verified non-vacuous by reverting it.

Feature tests:

1. Guest may read `/chat`, may not post.
2. Unverified member may not post.
3. Gagged, banned and throttled senders are each rejected.
4. The full policy matrix: every rank against every action, including equal-rank refusal.
5. A kick sets `kicked_until` and blocks presence auth until it passes.
6. Purchase: success debits and grants; insufficient funds does not; a repeat buy is a no-op and
   does not double charge.
7. Equip clears the previous item of the same type.
8. Admin grant writes a balance and a matching ledger row; ledger sum equals balance.
9. `Event::fake` — sending dispatches `MessageSent` on the public channel.
10. XSS: a message body containing markup renders escaped; a cosmetic payload with a bad colour or
    an unknown asset slug is rejected at validation.

Unit tests: `ChatPolicy`, `CosmeticStyle` payload validation.

## Deployment

```bash
composer84 require laravel/reverb
php84 artisan install:broadcasting      # writes config/broadcasting.php, routes/channels.php
pnpm build
php84 artisan optimize
REVERB_WORKER=1 PHP_BIN=php84 pm2 start ecosystem.config.cjs --env production && pm2 save
```

- `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_*`, `REVERB_HOST`, `REVERB_PORT`,
  `REVERB_SCHEME`, plus the matching `VITE_REVERB_*`.
- `ecosystem.config.cjs` gains an `ahd-reverb` app behind a `REVERB_WORKER=1` flag, mirroring the
  existing `ahd-queue` block.
- nginx proxies `/app` to the Reverb port with `Upgrade` / `Connection` headers.
- CSP needs no change: `connect-src` already allows `wss:` and `ws:`.
- Cloudflare must not cache `/chat` — it is excluded from the guest `s-maxage` block in
  `SecurityHeaders` along with the other authenticated paths.

## Out of scope for v1

Multi-room (`/chat/{room}`), private chat, member-to-member trading, real-money top-up, animated or
timed powers, audio and tickles, cosmetic uploads, mobile push. Each is its own spec.

## Risks

1. **Moderation is a people problem.** A public live chat on a Thai anime site is a real abuse and
   legal surface. v1 logs every message and every moderator action, but logs are not moderators —
   the room needs staffed owners before it opens to everyone.
2. **A second always-on process.** Reverb must be supervised (PM2, `pm2 save`). The broken root
   crontab should be fixed regardless, but nothing here depends on it.
3. **Capacity.** Reverb beside php-fpm on this box realistically tops out around 2k concurrent
   sockets. Past that the daemon moves to its own host — the design does not assume co-location,
   only the deploy steps do.
4. **Schema lives in another repo.** The tables must exist in ahd-admin before ahd-v2 deploys, or
   `/chat` 500s on first load. Deploy order is ahd-admin migration first, then ahd-v2.
