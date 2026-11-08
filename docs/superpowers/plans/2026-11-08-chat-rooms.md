# Chat Rooms, Store Tabs and Message Backgrounds Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin-made topic rooms with a layout-C chat page (rooms + online list sidebar, one message per row), Chat | Store tabs, and an admin-catalogue message background members can buy and equip.

**Architecture:** Rooms are rows in `chat_rooms` (created in `ahd-admin`); `chat_messages.room_id` scopes every read and broadcast. History cache, public channel and presence channel all become per room (`chat:room:{id}:history`, `chat.room.{id}`, `chat.roster.{id}`). Sanctions stay global and are announced on every active room's channel. Backgrounds reuse `CosmeticStyle` + client `safeStyle` with a new `--chat-background` key.

**Tech Stack:** Laravel 12, PHP 8.4, Blade + Alpine 3 (public), Inertia + Vue 3 (admin), Vitest, PHPUnit on in-memory sqlite, Reverb/Echo.

**Spec:** `docs/superpowers/specs/2026-11-08-chat-rooms-design.md` (read it first; its "my calls" are decided).

## Global Constraints

- **No migration files in this repo.** Schema lives in `../ahd-admin` (Task 1). This repo only edits the sqlite shim `database/migrations/2024_01_01_000005_create_tag_shim_tables.php`, behind `Schema::hasTable` / `hasColumn`.
- **Prod is MariaDB 10.1.47:** no `json` columns (use `longText` + array cast), no JSON SQL functions, CTEs, window functions, `SKIP LOCKED`/`NOWAIT`. Tests run on sqlite and will NOT catch violations.
- Blade + Alpine for public pages, Inertia + Vue only for admin. `x-text` only, never `x-html`/`innerHTML`/`v-html`.
- Styles reach the page only through server `CosmeticStyle` and the client `safeStyle` allow-list.
- Members are identified by uuid on the client; `members.id` is never public. Room ids and slugs are public.
- The server is the only authority; client rank logic only decides which buttons to show.
- Redis is shared with other apps: key writes go through the facade (app prefix), and deletes are by exact key, never by pattern.
- Alpine `:disabled` bindings must evaluate to a real boolean (`!!expr`): an `undefined` leaves buttons disabled inside `x-for`.
- Do not stage `app/Console/Commands/IndexPageViews.php` or `public/cwv-*.jpg`.
- Never run a migration on prod without the user saying so. Never run a bare `migrate` in `ahd-admin` (an older unrelated migration is pending); use `--path`.

## Execution Notes

- **Run Tasks 1 and 9 in the main session, not a subagent.** They need context a fresh agent lacks: the cross-repo diff (take Task 1's review diff with `git -C ../ahd-admin`, since `review-package` here would show nothing), the scratch-server router recipe, the curl-blocking hook (use the browser or `ctx_execute` to fetch), synthetic clicks around the site's ad popups, and smiley seeds needing an `asset` key. If Task 9 is ever delegated, inline the router recipe: a `router.php` that returns `false` for files under `public/` and otherwise `chdir`s into `public/` and requires `public/index.php`, run with `php -S 127.0.0.1:PORT -t public router.php` (`artisan serve` returned a bare 404 here).
- The local `.env` points at the production database and a shared Redis; every local run must use the sqlite/env overrides above.

## Review Focus

Spec-implied failure modes no task's tests would otherwise exercise, most likely first:

1. **Delete lands on the wrong room.** Deleting a message in room B must clear room B's history cache only and broadcast on `chat.room.B`, never `chat.room.1`. (Task 3 test)
2. **Dead or unknown room accepts traffic.** Posting to a deactivated or nonexistent `room_id` is refused; `/chat/room/{inactive}` is 404; the `lobby` room cannot be deactivated. (Tasks 3 and 4 tests)
3. **A background injects CSS.** A payload like `red; background:url(x)` or an oversized angle yields no `--chat-background`; hex and `linear-gradient` yield exactly one. The client drops anything outside `SAFE_VALUE`. (Task 5 tests)
4. **Sanction misses a room.** A gag/kick/ban notice is broadcast on every active room channel and none for inactive rooms. (Task 3 test)
5. **Buttons render disabled.** Unit tests never render Alpine. A real-browser check of the menu buttons, tabs and room switch is a required step in Task 9.

---

### Task 1: ahd-admin migration for rooms (separate repo, `../ahd-admin`)

**Files:**
- Create: `../ahd-admin/database/migrations/2026_11_08_000001_create_chat_rooms.php`

**Interfaces:**
- Produces: table `chat_rooms` (`id`, `slug` varchar(40) unique, `name` varchar(60), `topic` varchar(160) null, `sort` int default 0, `active` tinyint default 1, `timestamps`); a seeded row `slug='lobby'`; `chat_messages.room_id` bigint unsigned NOT NULL DEFAULT `<lobby id>` with index `(room_id, created_at)` and a foreign key to `chat_rooms`.

- [ ] **Step 1: Write the migration** with `up()`: create `chat_rooms` if absent; insert the `lobby` row if absent and capture its id; add `room_id` to `chat_messages` if the column is absent, as `NOT NULL` with that id as a **literal** default (MariaDB requires a literal, so compute the id first, then `DB::statement('ALTER TABLE ...')`); add the index and FK. Backfill is implicit (existing rows take the default) but run an explicit `UPDATE ... WHERE room_id = 0` guard. `down()` drops the FK, index, column, then the table. Every step is guarded with `hasTable`/`hasColumn` because DDL here is not transactional and a failed run leaves partial state.
- [ ] **Step 2: Test on a real MariaDB 10.1, not `--pretend`.** `--pretend` never sends SQL to a server (it already let a `json` column through once this session) and fakes the inserted lobby id. `podman` is installed and running. Start `docker.io/library/mariadb:10.1` on a spare port, point `../ahd-admin` at it with env overrides (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`; never edit `.env`), create a minimal `members` table, run the chat-tables migration and then the rooms migration by `--path`, assert `chat_rooms` has the `lobby` row and `chat_messages.room_id` is `NOT NULL` with that id as default and existing rows backfilled, then `migrate:rollback --path=...` and run it again to prove the guards. **Before any `migrate` or `migrate:rollback`, prove you are on the scratch DB, because `../ahd-admin/.env` points at prod (also MariaDB 10.1.47, so a version check cannot tell them apart) and env overrides only win when config is not cached:** `bootstrap/cache/config.php` must not exist in `../ahd-admin`; use a database name that does not exist on prod (`chat_rooms_scratch`); and abort unless a tinker one-liner prints `127.0.0.1:<spare port>/chat_rooms_scratch`, built from `config('database.connections.mysql.host')`, the port, and `DB::selectOne('select database() d')->d`. If pulling `mariadb:10.1` fails with "no matching manifest", retry with `--platform linux/amd64`. Remove the container afterwards. If podman cannot start, say so in the handoff and call the migration untested against 10.1.
- [ ] **Step 3: Note in the commit body** that `ALTER TABLE ADD COLUMN` on MariaDB 10.1 rebuilds `chat_messages` and blocks writes while it runs; the table is small, so run it soon after deploy. Commit in `ahd-admin` only. **Do not run it on prod; ask the user.**

---

### Task 2: Test shim, `ChatRoom` model, and room scoping on messages

**Files:**
- Modify: `database/migrations/2024_01_01_000005_create_tag_shim_tables.php`
- Create: `app/Models/ChatRoom.php`
- Modify: `app/Models/ChatMessage.php`
- Test: `tests/Feature/Chat/ChatRoomTest.php`

**Interfaces:**
- Produces: `App\Models\ChatRoom` with fillable `slug,name,topic,sort,active`, cast `active` bool, `scopeActive`, `static lobby(): ChatRoom`, `static activeOrdered(): Collection`; `ChatMessage` fillable gains `room_id` and a `room()` BelongsTo.
- Shim: creates `chat_rooms` and inserts the `lobby` row with id 1 if absent; adds `chat_messages.room_id` unsigned bigint default 1 (indexed) if the column is absent. No foreign keys (same reason as the existing shim).

- [ ] **Step 1: Write failing tests** in `ChatRoomTest`: `test_lobby_row_exists_in_test_schema` (`ChatRoom::lobby()->slug === 'lobby'`); `test_new_message_defaults_to_lobby` (create a `ChatMessage` without `room_id`; `room_id === ChatRoom::lobby()->id`); `test_active_ordered_excludes_inactive_and_sorts` (create three rooms, one inactive; expect only active, ordered by `sort` then `id`).
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter=ChatRoomTest`. Expected: FAIL (class/table missing).
- [ ] **Step 3: Implement** the shim edit, the model and the `ChatMessage` change as specified above.
- [ ] **Step 4: Run** `vendor/bin/phpunit --filter=ChatRoomTest` then `vendor/bin/phpunit --filter=Chat`. Expected: all PASS.
- [ ] **Step 5: Commit** `feat(chat): ChatRoom model, room_id on messages and test shim`.

---

### Task 3: Per-room history, channels, presence, posting and delete

**Files:**
- Modify: `app/Contracts/ChatHistory.php`, `app/Support/RedisChatHistory.php`, `app/Support/ArrayChatHistory.php`, `app/Services/Chat/ChatService.php`, `app/Services/Chat/ModerationService.php`
- Modify: `app/Events/Chat/MessageSent.php`, `MessageDeleted.php`, `MemberSanctioned.php`, `app/Broadcasting/RosterChannel.php`, `routes/channels.php`
- Modify: `app/Http/Controllers/ChatController.php`, `routes/web.php`
- Test: update existing `tests/Feature/Chat/*` and `tests/Unit/Chat/*` for the new signatures; add `tests/Feature/Chat/RoomsTest.php`

**Interfaces:**
- `ChatHistory`: every method gains a leading `int $roomId` — `push(int, array)`, `recent(int, int): ?array`, `replace(int, array)`, `forget(int)`. Redis key `chat:room:{roomId}:history` (same LPUSHX/ltrim/expire behaviour as today). The old `chat:lobby:history` key is no longer read or written.
- `ChatService`: `history(int $roomId, int $limit = 50): array` (query filters `room_id`), `remember(int $roomId, array $record)`, `forgetHistory(int $roomId)`. `record()` unchanged.
- Events: `MessageSent(int $roomId, array $message)` and `MessageDeleted(int $roomId, int $messageId)` broadcast on `new Channel("chat.room.{$roomId}")`. `MemberSanctioned(array $roomIds, string $uuid, string $action)` is dispatched **once** by `ModerationService::apply` with `ChatRoom::active()->pluck('id')->all()`; its `broadcastOn(): array` returns one `Channel("chat.room.{$id}")` per id (one HTTP call carries up to 100 channels, so no N synchronous broadcasts inside the request or the DB transaction).
- `RosterChannel::join(Member $member, ChatRoom $room): array|false` (implicit binding; a missing room becomes a 403, not a TypeError) — false when the room is inactive or the member is banned/kicked. `routes/channels.php`: `Broadcast::channel('chat.roster.{room}', RosterChannel::class, ['guards' => ['member']])` (class string, per the existing comment there).
- Callers of the history cache are only `ChatController` (`show`, `store`, `destroy`) and the admin `destroyMessage`. Equip, grant and rank changes have never invalidated it and keep relying on the 5-minute expiry; do not add invalidation.
- Routes: `GET /chat` → `ChatController@show` (lobby); `GET /chat/room/{slug}` → `ChatController@show` named `chat.room`; 404 for unknown or inactive slugs. `POST /chat/messages` requires `room_id` (`exists:chat_rooms,id,active,1`).
- `show()` view data: `history`, `myRank`, `room` (`{id, slug, name, topic}`), `rooms` (list of `{id, slug, name, topic}` from `ChatRoom::activeOrdered()`).
- `destroy()` uses the message's own `room_id` for `forgetHistory`, `MessageDeleted` and the moderation log meta.

- [ ] **Step 1: Write failing tests** in `RoomsTest`: `test_show_lobby_and_other_room`, `test_inactive_room_page_is_404`, `test_post_to_inactive_or_unknown_room_is_refused` (assert nothing persisted), `test_message_is_broadcast_on_its_room_channel` (`Event::fake`; `MessageSent` carries the room id), `test_history_is_per_room` (message in room B absent from room A's history), `test_delete_only_clears_own_room_history_and_broadcasts_on_it` (Review Focus 1), `test_sanction_is_broadcast_on_every_active_room_only` (Review Focus 4), `test_roster_channel_rejects_inactive_room_and_banned_member` (call `RosterChannel::join` directly), and `test_presence_auth_endpoint_per_room`: the null broadcaster never invokes channel callbacks, so in this test set `broadcasting.default` to `reverb` with a fake key/secret/app id, POST `/broadcasting/auth` as `actingAs($member, 'member')` with `channel_name=presence-chat.roster.{id}` and `socket_id=1.1`, and expect 200 for an active room and 403 for an inactive room or a banned member (auth is signed locally; no server needed). `Broadcast::channel()` registers on whichever driver is default at boot (the null broadcaster in tests), so after switching the config run `require base_path('routes/channels.php');` to register the channel on the new driver. A 403 on the ACTIVE room means the channel was not re-registered, not that the code is right; it would otherwise pass every negative case and hide the bug. Event tests assert the exact channel names (`'chat.room.'.$id`), not just "carries the room id", so a mismatch with the JS subscription fails a test.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter=RoomsTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the contract, both history implementations, service, events, channel, routes and controller changes. Update every existing test that called the old signatures; the existing `ArrayChatHistory` throwing mode must still be exercised.
- [ ] **Step 4: Run** `vendor/bin/phpunit` (full). Expected: PASS.
- [ ] **Step 5: Commit** `feat(chat): per-room history, channels, presence, posting and delete`.

---

### Task 4: Admin room management

**Files:**
- Modify: `app/Http/Controllers/Admin/ChatManagementController.php`, `routes/web.php`
- Modify: `resources/js/pages/admin/Chat.vue`
- Test: `tests/Feature/Chat/AdminChatTest.php` (add cases)

**Interfaces:**
- Routes (admin group, same middleware as the existing chat admin routes): `POST dashboard/chat/rooms` (`admin.chat.rooms.store`), `PATCH dashboard/chat/rooms/{room}` (`admin.chat.rooms.update`, `whereNumber`).
- `storeRoom`: validates `name` (required, max 60), `slug` (required, `^[a-z0-9-]{2,40}$`, unique), `topic` (nullable, max 160), `sort` (integer). `updateRoom`: name/topic/sort/active only — **slug is never changed**; `lobby` cannot be set inactive (422).
- `index()` gains `rooms` (all rooms with `id, slug, name, topic, sort, active`). `destroyMessage` uses the message's `room_id` for `forgetHistory` and `MessageDeleted`.

- [ ] **Step 1: Write failing tests**: `test_admin_creates_room`, `test_duplicate_or_bad_slug_is_rejected`, `test_update_never_changes_slug`, `test_lobby_cannot_be_deactivated` (Review Focus 2), `test_non_admin_cannot_manage_rooms`, `test_admin_delete_message_uses_its_room`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter=AdminChatTest`. Expected: FAIL.
- [ ] **Step 3: Implement** controller, routes and a Rooms section in `Chat.vue` (list, create form, rename/topic/sort edit, activate toggle), following the page's existing busy-guard and error patterns.
- [ ] **Step 4: Run** `vendor/bin/phpunit --filter=Chat` and `pnpm lint` (eslint on touched files only). Expected: PASS.
- [ ] **Step 5: Commit** `feat(chat): admin room management`.

---

### Task 5: Background style rendering (server + client allow-list)

**Files:**
- Modify: `app/Support/CosmeticStyle.php`, `resources/js/chat/lobby.js`
- Test: `tests/Unit/Chat/CosmeticStyleTest.php` (or the existing style test file), `tests/js/chatLobby.test.ts`

**Interfaces:**
- `CosmeticStyle::for()`: `Background` is no longer routed to `asset()`; it is colour/gradient only: `{color}` → `['--chat-background' => hex]`, `{from,to,angle}` → `['--chat-background' => 'linear-gradient(...)']`, anything else (including an `asset`-shaped payload) → `[]`. The old asset branch for `Background` is removed: `chat_cosmetics` was created empty on prod on 2026-11-08, so no old-shape rows exist, and the client would strip `url(...)` anyway. This answers the spec's open question about existing background rows. `gradient(array $payload, string $property = '--chat-name-gradient')` gains the property parameter. Pawn keeps `asset()` only.
- `lobby.js`: `STYLE_KEYS` gains `'--chat-background'`. `SAFE_VALUE` is unchanged.

- [ ] **Step 1: Write failing tests**: PHP — `test_background_hex_payload`, `test_background_gradient_payload`, `test_background_rejects_css_injection` (`red; background:url(x)` and angle 999 → `[]`, Review Focus 3), `test_background_with_asset_payload_yields_nothing`, `test_name_gradient_unchanged`. JS — `safeStyle` keeps `--chat-background` for a hex and a gradient, drops `url(...)`, drops unknown keys.
- [ ] **Step 2: Run** the PHP filter and `pnpm test`. Expected: FAIL.
- [ ] **Step 3: Implement** the two changes above.
- [ ] **Step 4: Run** both suites. Expected: PASS.
- [ ] **Step 5: Commit** `feat(chat): colour and gradient message backgrounds in the style pipeline`.

---

### Task 6: Backgrounds in the store and admin catalogue

**Files:**
- Modify: `app/Http/Controllers/ChatStoreController.php`, `resources/js/chat/store.js`, `resources/views/chat/store.blade.php`, `resources/js/lib/cosmeticPayload.ts`, `resources/js/pages/admin/Chat.vue`
- Test: `tests/Feature/Chat/StoreTest.php`, `tests/js/chatStore.test.ts`, `tests/js/cosmeticPayload.test.ts`, `tests/Feature/Chat/AdminChatTest.php`

**Interfaces:**
- `ChatStoreController::index` stops filtering `Background` (Pawn stays hidden); items of type `background` carry `style` and `slot: 'background'`.
- `store.js` label map gains `background: 'Message background'`; the preview renders the background tint behind sample text via `safeStyle(item.style)`.
- `cosmeticPayload.ts`: `fieldsFor('background')` offers colour or from/to/angle; `buildPayload` produces `{color}` or `{from,to,angle}` accordingly. The admin type dropdown lists `background`. Admin `storeCosmetic` validation accepts the type with the same payload rules as name colour/gradient.

- [ ] **Step 1: Write failing tests**: `test_store_lists_background_items_but_not_pawns`, `test_buy_and_equip_background_uses_its_own_slot_and_keeps_name_style` (equipping a background must not unequip a name style), `test_admin_can_create_background_cosmetic`, JS `fieldsFor`/`buildPayload` for background, `chatStore` label and preview style.
- [ ] **Step 2: Run** the filters and `pnpm test`. Expected: FAIL.
- [ ] **Step 3: Implement** the four changes. These four places currently hide the feature silently; check each one.
- [ ] **Step 4: Run** `vendor/bin/phpunit --filter=Chat` and `pnpm test`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(chat): buy, equip and build message backgrounds`.

---

### Task 7: Chat layout C, tabs and one-message-per-row

**Files:**
- Create: `resources/views/components/chat/tabs.blade.php`, `resources/views/components/chat/sidebar.blade.php`
- Modify: `resources/views/chat.blade.php`, `resources/views/chat/store.blade.php`, `resources/views/components/chat/message.blade.php`, `resources/views/components/chat/name-styles.blade.php`, `resources/views/components/chat/roster.blade.php`, `resources/views/components/site-header.blade.php`, `resources/js/chat/lobby.js`
- Test: `tests/Feature/Chat/ChatPageTest.php`, `tests/js/chatLobby.test.ts`

**Interfaces:**
- `<x-chat.tabs active="chat|store" />` renders `Chat` (→ `route('chat.show')`) and `Store` (→ `route('chat.store')`, members only) with the active one marked `aria-current="page"`. Both pages use it. The header's Store link (in the `$nav` array of `site-header.blade.php`) is removed; the header Chat link stays.
- `<x-chat.sidebar :rooms :room />`: room links (`/chat` for lobby, `route('chat.room', slug)` otherwise, active marked) above the online list (`x-chat.roster`). The store page shows the tab bar only, no sidebar.
- Bootstrap JSON gains `room` (`{id, slug, name}`); `lobby.js` listens on `chat.room.${room.id}` and joins `chat.roster.${room.id}`; `POST /chat/messages` sends `room_id`. The message-row markup is one block per message (avatar initial, name + rank, body), with `--chat-background` painted as a translucent tint through a pseudo-element at fixed opacity using the `background` shorthand (works for hex and gradient) and text colour untouched; the `···` menu is anchored to its button and stays inside the viewport on the last row.
- `onSanctioned` currently hardcodes `echo.leave('chat.roster')`, which silently does nothing once the channel is renamed; it must call `echo.leave('chat.roster.' + room.id)`, and a JS test must assert that exact name.
- The composer placeholder reads `Message #{room name}` via `x-text`.

- [ ] **Step 1: Write failing tests**: `test_chat_page_has_tabs_and_room_sidebar`, `test_store_page_has_tabs_and_no_sidebar`, `test_header_has_no_store_link`, `test_bootstrap_contains_room_and_no_member_id`, JS — `connect()` subscribes to the room channel names and `send()` includes `room_id` (stub Echo as the existing tests do).
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter=ChatPageTest` and `pnpm test`. Expected: FAIL.
- [ ] **Step 3: Implement** the components, page rewrite, header edit and `lobby.js` changes. Every `:disabled` binding must use `!!`. The sidebar must collapse under the page on phone widths (stack above the messages).
- [ ] **Step 4: Run** `vendor/bin/phpunit`, `pnpm test`, `pnpm build`. Expected: PASS and build OK.
- [ ] **Step 5: Commit** `feat(chat): room sidebar layout, tabs and one-message-per-row`.

---

### Task 8: Deploy notes and known limits

**Files:**
- Modify: `docs/chat-deploy.md`

- [ ] **Step 1: Update the doc** with: the deploy order (`ahd-admin` migration first, then this app); the hard cutover (pages left open must reload); the orphaned `chat:lobby:history` key (leave it or `DEL` that exact key, never a pattern); per-room channel names; MariaDB 10.1 limits; the table-rebuild note from Task 1; the sanction-is-global decision; the stale-closed-room behaviour.
- [ ] **Step 2: Commit** `docs(chat): deploy notes for rooms`.

---

### Task 9: Gates and real-browser check

**Files:** none (verification only; fixes go back to the owning task's files)

- [ ] **Step 1: Run the gates** — `vendor/bin/phpunit`, `pnpm test`, `vendor/bin/phpstan analyse` (expect only the 6 known baseline errors: `IndexPageViews`, `AdImage` ×3, `Schema`, `NumericRouteConstraintTest`), `pnpm build`, `./vendor/bin/pint --test` and eslint on touched files.
- [ ] **Step 2: Grep for 10.1-incompatible SQL** in the chat paths: `whereJson`, `JSON_`, `json_extract`, `->>`, `withExpression`, `OVER (`, `skipLocked`, `noWait`, `upsert(`. Expected: no hits.
- [ ] **Step 3: Real-browser check on a throwaway sqlite database** (never the remote DB — `.env` points at prod): copy the scratch approach — sqlite file in the scratchpad, `php -S` with a router, env overrides `DB_CONNECTION=sqlite SESSION_DRIVER=file CACHE_STORE=file BROADCAST_CONNECTION=log REDIS_PORT=6399`, seeded members/rooms/cosmetics. Check with synthetic clicks (the site's ad script opens popups on real clicks): both tabs navigate; room links switch rooms and each room shows only its own messages; menu buttons are **enabled** (`button.disabled === false`) and Gag/Kick/Delete/Ban work; buying and equipping a background tints that member's rows; the admin page creates a room and a background; the sidebar stacks at phone width.
- [ ] **Step 4: Report** the results, the not-fixed items, and the deploy order. Do not merge, push, or touch prod; ask the user.
