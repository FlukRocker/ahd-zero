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
4. **Redis is shared.** CLAUDE.md names kurokami; keys for lnw-anime and neko_miku were also
   observed on the production instance. Every key below goes through Laravel's Redis facade so it
   carries the app prefix, and nothing in this design flushes or scans outside it.
5. **Cron on the production box is broken** (root crontab line 3 has four time fields, so Debian
   discards the whole file). Reverb is therefore supervised by PM2, which is already installed and
   already the documented place for long-running processes. The crontab still needs fixing for the
   scheduler, but this feature does not depend on it.
6. **CSP does not contain XSS here.** `script-src` already allows `'unsafe-inline' 'unsafe-eval'
   https:` for the ad tags, so a successful injection would execute. Escaping is the only control;
   every rendering rule below is load-bearing, not defence in depth.

## Architecture

```
browser ──POST /chat/messages──► Laravel ─┬─ validate · rank/gag/kick/ban check · persist
                                          └─ broadcast(MessageSent)
                                                   │
browser ◄────── wss ──────────────  Reverb daemon (PM2, nginx proxies /app)
```

**Clients never write to the socket.** Sending is an ordinary HTTP POST so it passes through auth,
validation, the rate limiter and the sanction checks. The socket is push-only plus presence.
Client events (whispers) are **disabled** — they would bypass the rate limiter and the rank checks,
and their payload is client-controlled. That removes the typing indicator from v1; it returns only
if it can be driven by a throttled server round trip.

Two channels:

- `chat.lobby` — **public**. Carries every message, deletion and sanction notice. Guests and banned
  members may subscribe; both are read-only, which is the behaviour we want and costs no extra code.
- `presence-chat.lobby` — **presence, members only**. The online roster. The authorisation callback
  rejects members with an active ban and returns **only** `uuid`, display name, rank and equipped
  cosmetics — never `members.id`, never the email.

### What a ban can and cannot do

Authorisation runs at subscribe time, so a ban cannot evict a socket that is already connected.
Being honest about that:

- A banned member **cannot send** (checked on every POST) and **cannot rejoin** the roster.
- A banned member *can keep reading* until they reconnect. `/chat` is world-readable, so this
  grants nothing a logged-out browser does not already have.
- A ban broadcasts `MemberSanctioned` on the public channel; the client drops its presence
  subscription on receipt. That is client-cooperative and therefore **not** a security control —
  it is a UX nicety. The server-side truth is the POST check and the subscribe check.
- Kick is the same shape: `kicked_until = now + 60s`, enforced on POST and on subscribe.

### History

The last 200 messages live in a Redis list, pushed and trimmed in one pipelined `LPUSH` + `LTRIM`.
Key: `chat:lobby:history` (prefixed by the Redis facade). The page renders the newest 50 from it.

`chat_messages` is the durable record behind it. If the key is missing the page rebuilds it from
the table, **filtering `deleted_at`** — a cold cache is slower, never wrong.

**Deleting a message `DEL`s the history key** rather than trying to surgically `LREM` a JSON blob.
The next page load rebuilds 200 rows from MySQL, which is cheap and leaves no way for a deleted
message to reappear.

## Data model

Six tables, created in **ahd-admin**. `members.id` is a **bigint** (`$table->id()`); `members.uuid`
is a separate unique column and `Member::uniqueIds()` returns `['uuid']`. Every FK below therefore
targets `members.id` (bigint), and the uuid is used only in URLs and broadcast payloads.

### `chat_members`

One row per participant. Keeps rank, sanctions and wallet off the `members` table, which this app
does not own.

| column | notes |
|---|---|
| `member_id` | PK, unsigned bigint, FK → `members.id`, cascade on delete |
| `rank` | enum: `member`, `mod`, `owner`, `main_owner`; default `member` (guest is the absence of an account, never a row) |
| `balance` | unsigned int, coins, default 0 |
| `gagged_until` | nullable timestamp |
| `kicked_until` | nullable timestamp |
| `banned_at` | nullable timestamp — set for any chat ban |
| `banned_until` | nullable timestamp — null while `banned_at` is set means permanent |
| `ban_reason` | nullable string |
| `created_at` / `updated_at` | |

**Active-ban predicate, used everywhere, defined once in `ChatMember::isBanned()`:**
`banned_at IS NOT NULL AND (banned_until IS NULL OR banned_until > now())`.
An expired timed ban is simply not active; unban nulls all three columns and logs the action.

The row is created on demand with `insertOrIgnore` before any locking read — see "Currency".

**Relation to the existing site ban:** `members.banned_at` already exists and
`MemberAuthController` refuses login when it is set, so a site-banned member cannot reach `/chat`
at all. `chat_members.banned_at` is chat-only and independent. Neither one writes the other.

### `chat_messages`

`id`, `member_id` (bigint FK), `body` (text, ≤ 500 chars), `created_at`, `deleted_at`,
`deleted_by` (nullable bigint FK → `members.id`), `deleted_by_admin_id` (nullable bigint FK →
`users.id`). Index on `created_at`, index on `member_id` for "delete everything this user posted".
Deletion is soft — a moderator removing a message must not destroy the evidence.

### `chat_cosmetics`

The admin-authored catalogue. `id`, `type`, `name`, `slug` (unique), `payload` json, `price`
unsigned int, `active` bool, `sort` int.

Types: `name_color`, `name_gradient`, `glow`, `pawn`, `smiley`, `background`.

**There is no hard delete.** The admin "delete" action sets `active = false`, which hides the item
from the store and leaves every inventory row and ledger row intact and meaningful.

### `chat_cosmetic_member`

Inventory. `id`, `member_id` (bigint FK), `cosmetic_id` (FK, restrict on delete), `acquired_at`,
`source` (`purchase` | `grant`), `equipped` bool. **`unique(member_id, cosmetic_id)`** — this index
is what makes a double-click a no-op instead of a double charge.

### `chat_transactions`

Append-only ledger. `id`, `member_id` (bigint FK), `delta` (signed int), `balance_after`, `reason`
(`admin_grant` | `purchase`), `cosmetic_id` nullable, `admin_id` nullable (bigint FK → `users.id`),
`note`, `created_at`. Every balance change writes a row in the same DB transaction as the balance
update; the ledger must always reconstruct the balance. `delta` is signed only so the arithmetic
reads naturally — v1 has no negative producer (see "Currency").

### `chat_moderation_log`

`id`, `actor_member_id` (nullable bigint FK → `members.id`), `admin_id` (nullable bigint FK →
`users.id`), `target_member_id` (bigint FK), `action` (`gag` | `ungag` | `kick` | `ban` | `unban` |
`delete_message` | `rank_change` | `grant_coins` | `grant_cosmetic`), `meta` json, `created_at`.
Exactly one of `actor_member_id` / `admin_id` is set.

## Ranks and moderation

```
guest(0) < member(1) < mod(2) < owner(3) < main_owner(4)
```

**One rule, in one place:** an actor may only act on a target of *strictly lower* rank. A mod cannot
gag a mod. This lives in `App\Policies\ChatPolicy` and both entry points call it.

| rank | may |
|---|---|
| guest | read |
| member | read, send, delete own message |
| mod | + gag (timed mute), kick, delete a lower-ranked member's message |
| owner | + ban (timed or permanent), unban, promote/demote to mod |
| main_owner | + promote/demote owner |

Deleting a message is judged on **the message author's rank**, with one addition to the rule: a
member may always delete their own message. Equal rank is refused, so a mod cannot delete another
mod's message.

Granting coins or cosmetics is **not** a chat rank power — it belongs to site admins in the
dashboard only.

**Who may use in-room moderation:** `auth:member` only. Site admins (the `web` guard) moderate
through the dashboard routes, not through `/chat`. An admin who wants in-room powers registers a
member account and is given `main_owner`. This keeps every `/chat` route on one guard instead of
threading `auth.memberOrAdmin` and two identity types through the policy. `chat_members` holds no
admin rows.

**Who may send:** an authenticated member (`auth:member`) who is not gagged, kicked or banned.
**Email verification is required** — note this is *stricter* than the existing comment route, which
is `auth.memberOrAdmin` with no verification check. A live room is a louder surface than a comment
thread, and the verified-email gate is the cheapest spam brake available.

Enforcement happens at two points on purpose, with the limits stated in "What a ban can and cannot
do": the POST, and the presence subscribe.

## Cosmetics

**Cosmetics are admin-authored, never user-authored. This is a security boundary, not a product
limitation.**

`payload` is a fixed json shape per type, not free-form CSS:

| type | payload | validation |
|---|---|---|
| `name_color` | `{ "color": "#rrggbb" }` | `/^#[0-9a-f]{6}$/i` |
| `name_gradient` | `{ "from": "#rrggbb", "to": "#rrggbb", "angle": 0-360 }` | hex + int range |
| `glow` | `{ "color": "#rrggbb", "strength": 1-3 }` | hex + int enum |
| `pawn` | `{ "asset": "<slug>" }` | slug `/^[a-z0-9-]{1,32}$/` **and** the file exists in `public/chat/pawns/` |
| `smiley` | `{ "code": "(name)", "asset": "<slug>" }` | code `/^\(\w{2,12}\)$/`, asset as above |
| `background` | `{ "asset": "<slug>" }` | slug + exists in `public/chat/backgrounds/` |

Colours are arbitrary; **images are not** — there are no uploads in v1, so the admin picks from
assets committed to the repo. The slug regex excludes `.` and `/`, so no payload can traverse out
of its directory. Upload support would mean SVG sanitising and is a separate concern.

**`App\Support\CosmeticStyle` re-validates the payload on every render, not only on admin save.**
A value that reaches `style="..."` is in a CSS context, where HTML escaping means nothing; the
only safe rule is that the renderer itself refuses anything that does not match the regex above and
emits a known-good default instead. Rendering goes through this one helper, which returns a small
map of inline custom properties.

Message bodies are escaped. Smiley codes expand to `<img>` **only if the sender owns that smiley**;
otherwise the code stays literal text.

**The client never uses `innerHTML`.** The server broadcasts structured data (`body` plus the
sender's owned smiley map) and the Alpine component builds text nodes and image elements. Initial
history is handed over in a `<script type="application/json" id="chat-history">` block encoded with
`json_encode($v, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)` — **not** interpolated
into an `x-data` attribute, where a `"` in a message body breaks out and becomes XSS.

### Equip slots

Exclusivity belongs to the slot, not the type, so two name styles can never fight:

| slot | types |
|---|---|
| `name` | `name_color`, `name_gradient` |
| `effect` | `glow` |
| `pawn` | `pawn` |
| `background` | `background` |

Smilies have no slot — owning one makes it usable. Equipping takes the member's inventory rows
`lockForUpdate`, clears the equipped row in that slot and sets the new one, in one transaction.

## Currency and store

Unit: **coins**, unsigned int, never negative.

Every balance path starts the same way: `insertOrIgnore` the `chat_members` row, then
`lockForUpdate`. Without the insert, a first-time member has no row, `FOR UPDATE` locks nothing,
and two concurrent writes both insert and collide on the primary key.

**Purchase**, one DB transaction:

1. `insertOrIgnore` + `lockForUpdate` the member row.
2. Refuse if the cosmetic is `active = false`.
3. Refuse if already owned (explicit `exists` check after the lock).
4. Refuse if `balance < price`.
5. Insert inventory, insert ledger row, decrement balance.

The duplicate-key error (SQLSTATE 23000) is still caught around step 5 and turned into the same
"you already own this" response — the explicit check narrows the race, the catch closes it. Every
refusal is a 422 with an inline message, never a 500.

**Grant** (dashboard only): a **positive** integer amount plus a note. Writes the balance and the
ledger row in one transaction and a `chat_moderation_log` row. There is no revoke and no refund in
v1 — a signed `delta` with no defined producer is an unspecified path, so it stays unused.

**Cosmetic grant** (dashboard only): gives an item outright with `source = grant`, no coins moved,
logged as `grant_cosmetic`. This is what makes `source` meaningful.

Nothing else creates coins. No earning loop, no trading, no payment in v1, which is what keeps the
abuse surface at zero.

## Routes

```
GET    /chat                            public — guests read
POST   /chat/messages                   auth:member + verified, throttle:20,1
DELETE /chat/messages/{id}              auth:member — own message, or mod+ over a lower rank
POST   /chat/moderate                   auth:member, mod+ — body: action, target_uuid, duration, reason
GET    /chat/store                      auth:member
POST   /chat/store/{cosmetic}/buy       auth:member, throttle:30,1
POST   /chat/cosmetics/{cosmetic}/equip auth:member, throttle:60,1 — 403 unless owned
```

`{cosmetic}` is a `chat_cosmetics` id, route-model-bound; equip resolves the inventory row from
`(member, cosmetic)` and 403s when it does not exist. Targets are addressed by **uuid** in public
routes so `members.id` never appears in a URL.

Admin, inside the existing `['auth','verified']` group in `routes/web.php` (literal `dashboard/...`
paths — there is no prefix group), with `{id}` being `members.id` as the existing ban route already
does:

```
GET    dashboard/chat                            console
POST   dashboard/chat/cosmetics                  create
PATCH  dashboard/chat/cosmetics/{id}             update / toggle active
DELETE dashboard/chat/cosmetics/{id}             deactivate (active = false; no hard delete)
POST   dashboard/chat/members/{id}/grant         coins + note
POST   dashboard/chat/members/{id}/cosmetics     grant an item
POST   dashboard/chat/members/{id}/rank          set rank
POST   dashboard/chat/members/{id}/ban           ban / unban
DELETE dashboard/chat/messages/{id}              delete
```

Status codes: 403 gagged/kicked/banned or rank refused, 422 validation and store refusals,
429 throttled (Laravel's `throttle` default — the client renders all three inline).

## Components

**Server**

- `App\Models\{ChatMember, ChatMessage, ChatCosmetic, ChatCosmeticMember, ChatTransaction, ChatModerationLog}`
- `App\Services\Chat\ChatService` — send, history (Redis + rebuild), delete
- `App\Services\Chat\ModerationService` — gag, kick, ban, rank, logging
- `App\Services\Chat\StoreService` — buy, equip, grant coins, grant cosmetic
- `App\Policies\ChatPolicy` — the rank rule, called by both the `/chat` and dashboard entry points
- `App\Support\CosmeticStyle` — validate-on-render → inline custom properties
- `App\Events\Chat\{MessageSent, MessageDeleted, MemberSanctioned, RankChanged}`
- `App\Http\Controllers\{ChatController, ChatStoreController, ChatModerationController}`
- `App\Http\Controllers\Admin\ChatManagementController`

Each service has one job and is testable without the socket: events are dispatched, not awaited.

**Client**

- `resources/views/chat.blade.php` + `resources/views/components/chat/*.blade.php`
- `resources/js/chat/lobby.js` — the Alpine component, registered from `resources/js/blade.js`.
  It is its own module because `blade.js` is already long and chat is the largest interactive
  surface on the public site.
- `laravel-echo` and `pusher-js` are **dynamically imported inside `lobby.js`**, not added to the
  Blade entry — otherwise every SEO page ships a socket client it never uses.
- `resources/js/pages/admin/Chat.vue` — Inertia, following `Members.vue` and `Comments.vue`.

## Message validation

`required|string|min:1|max:500`, plus the existing `App\Rules\NoExternalLinks` that the comment
path already uses, plus a strip of C0/C1 control characters and Unicode bidi overrides (U+202A–
U+202E, U+2066–U+2069) which are otherwise invisible and let a sender reorder what others read.

## Error handling

- Socket down → the page still renders history and the composer posts over HTTP; the client shows a
  "reconnecting" state and retries with backoff. Chat degrades to slow, not broken.
- Redis history key missing → rebuild from `chat_messages` where `deleted_at is null`.
- A rejected send returns 403/422/429 as above; the composer shows it inline and does not
  optimistically keep the message on screen.
- Broadcast failure is logged, never fatal: the message is already persisted, and a reconnect
  re-reads history.

## Testing

TDD — failing test first, and every fix verified non-vacuous by reverting it.

1. Guest may read `/chat`, may not post.
2. Unverified member may not post.
3. Gagged, kicked, banned and throttled senders are each rejected (403/403/403/429).
4. The full policy matrix: every rank against every action, including equal-rank refusal.
5. Delete: own message allowed; a mod may delete a lower rank's; a mod may not delete a mod's.
6. Ban sets the active-ban predicate; an expired `banned_until` is not an active ban; unban clears
   all three columns and logs.
7. Presence authorisation refuses a banned member and returns uuid — never `members.id`.
8. Purchase: success debits and grants; insufficient funds refuses; an inactive cosmetic refuses;
   a repeat buy is a no-op and does not double charge.
9. A member with no `chat_members` row can buy and be granted (the `insertOrIgnore` path).
10. Equip clears the previous item **in the same slot**; equipping an unowned cosmetic 403s.
11. Admin grant writes balance plus a matching ledger row; the ledger sum equals the balance.
12. Deleting a message clears the Redis key, and the rebuild excludes soft-deleted rows.
13. `Event::fake` — sending dispatches `MessageSent` on the public channel.
14. XSS: a body containing markup and a `"` survives the JSON handoff escaped; a cosmetic payload
    with a bad colour or an unknown asset slug is rejected at save **and** at render.
15. A guest GET to `/chat` does **not** carry `s-maxage` (the `SecurityHeaders` skip, below).

Unit: `ChatPolicy`, `CosmeticStyle` payload validation, the control-character strip.

**Known test-harness gap:** the suite runs on in-memory sqlite, which ignores `FOR UPDATE`, so the
purchase race cannot be proven by a feature test. The unique index is what actually holds, and test
8 proves the no-double-charge outcome; the locking is reasoned, not covered.

## Deployment

```bash
composer84 require laravel/reverb
# config/broadcasting.php and routes/channels.php are added by hand —
# `artisan install:broadcasting` is interactive and edits the JS entry.
pnpm build
php84 artisan optimize
REVERB_WORKER=1 PHP_BIN=php84 pm2 start ecosystem.config.cjs --env production && pm2 save
```

- `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_*`, `REVERB_HOST`, `REVERB_PORT`,
  `REVERB_SCHEME`, plus the matching `VITE_REVERB_*`.
- `ecosystem.config.cjs` gains an `ahd-reverb` app behind `REVERB_WORKER=1`, with
  `args: 'reverb:start --host=127.0.0.1 --port=8080'` and **no `max_memory_restart`** — the
  `ahd-queue` block's `256M` would restart the daemon and drop every socket. The file's header
  comment ("PM2 supervises ONLY the optional queue worker") needs updating in the same change.
- nginx proxies `/app` to the Reverb port with `Upgrade` / `Connection` headers.
- CSP needs no change: `connect-src` already allows `wss:` and `ws:`.
- **Code change required:** add `str_starts_with($path, 'chat')` to the skip list in
  `app/Http/Middleware/SecurityHeaders.php`. Without it a guest GET to `/chat` is served
  `public, max-age=60, s-maxage=300`, so Cloudflare would hand a cached guest render — including
  somebody's roster state — to the next visitor.

## Out of scope for v1

Multi-room (`/chat/{room}`), private chat, member-to-member trading, real-money top-up, animated or
timed powers, typing indicators, audio and tickles, cosmetic uploads, mobile push. Each is its own
spec.

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
5. **A ban does not evict a live socket.** Documented above as a known limit, acceptable only
   because the room is world-readable.
