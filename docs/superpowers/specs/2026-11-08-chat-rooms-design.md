# Chat rooms, tabs and message backgrounds — design

Follows `2026-11-07-chat-lobby-design.md`. The lobby, store and moderation UI are built and on `main`.

## Intent

The chat is one room today. Admins want topic rooms, a more modern layout, the store reachable from inside the chat, and members able to give their own messages a background they bought.

## Goals

1. **Rooms.** Admins create, rename, reorder and deactivate topic rooms. Every member and guest can read every active room; members can post in any active room. Rooms differ only by name and topic.
2. **Layout C.** The chat page is two columns. The left sidebar holds the room list with the online list beneath it. The right side is the message area with the composer at the bottom. One message per row: avatar, name and rank, then the body. The per-message moderation menu is anchored to its `···` button and is not clipped on the last line (a rough edge from the smoke test).
3. **Tabs.** A `Chat | Store` tab bar sits at the top of the chat area. It replaces the `Store` link in the site header. The store keeps its URL `/chat/store`, shows the tab bar only (no room sidebar), and its `Chat` tab goes to `/chat`. This also removes the double highlight on `/chat/store`.
4. **Message backgrounds.** Members buy and equip a background from the store. It paints behind that member's message row for everyone. The admin builds backgrounds in the same catalogue form as name styles.

## Non-goals

- Private or rank-gated rooms, per-room permissions, DMs.
- Unread counts and per-member read state (the mockup badge was illustrative).
- Member-supplied colours or images. Backgrounds come only from the admin catalogue.
- Pawn cosmetics stay hidden.

## Anti-goals

- Nothing a member can influence may inject CSS or HTML into other members' pages. Styles are built only by the server's `CosmeticStyle` and filtered again by the client `safeStyle`; `x-text` only, never `x-html`.
- Moving messages into rooms must not lose or re-order existing history.

## Decisions (user)

- Admins create rooms; topic-based; open to every member.
- Layout C, and the Chat | Store tabs.
- Backgrounds come from the admin catalogue, like name styles.

## Decisions (my calls — change any of these)

**Rooms and routing**
- Existing messages move into a default room named `lobby`. It cannot be deactivated. `/chat` opens it.
- Other rooms live at `/chat/room/{slug}`. This cannot collide with `/chat/store`, `/chat/messages`, `/chat/moderate` or `/chat/cosmetics/...`.
- A slug is fixed at creation; renaming changes the display name only, so links keep working.
- A deactivated room 404s on its page and rejects posts; its messages stay in the database.
- Room switching is a full page navigation. Each page joins only its own room's channel and presence channel, so Echo never has to leave and rejoin.
- Messages carry their room. Delete-by-id and the admin message list keep working by message id.

**Sanctions and presence**
- Sanctions and ranks are global (user has not confirmed; stated default). Rate limits, gag checks and posting authorisation stay global.
- A sanction notice is broadcast on every active room's channel, so a kicked or banned member's open tabs all react.
- The online list is per room: one presence channel per room replaces `chat.roster`. A member with two tabs in two rooms shows in both. No site-wide roster is built.

**Backgrounds (security-relevant)**
- `Background` moves off the asset path in `CosmeticStyle::for()` to colour/gradient, reusing the existing `hex()` builder and the `gradient()` builder (which hardcodes `--chat-name-gradient` today, so it takes the property name as a parameter) and emitting `--chat-background`. Pawn keeps the asset path. The old asset branch for `Background` is removed: `chat_cosmetics` was created empty on prod on 2026-11-08, so no old-shape rows exist (this closes the open question about existing background rows).
- Today `--chat-background` is not in the client `STYLE_KEYS`, so a background cosmetic renders nowhere. It is added to `STYLE_KEYS`. The existing `SAFE_VALUE` filter already allows hex and `linear-gradient(...)` and blocks `url(...)`, so no new character class is needed.
- Store and admin catalogue previews must use the same safe path.
- Contrast: the background is painted as a translucent tint at a fixed opacity and text colour is left unchanged, so one catalogue entry works in both the light and dark themes.
- One background can be equipped at a time (its own slot). It stacks with a name style. It paints the whole message row.
- `ImageVariantService` matched a `Background` search only because of the word "background" in an unrelated method; it is not involved.

**Deploy and cutover**
- Order: the `ahd-admin` migration first — rooms table, a seeded `lobby` row, `room_id` on `chat_messages` backfilled to it — then this app. This app does not seed the lobby and does not tolerate the schema being absent.
- `room_id` is `NOT NULL DEFAULT <seeded lobby id>`, so the old app's posts during the gap between the migration and this app's deploy land in `lobby` instead of failing. *My call.*
- Hard cutover. A page left open across the deploy keeps listening on the old channel names until it is reloaded. This is acceptable for a public read-mostly chat.
- The old `chat:lobby:history` Redis key is left orphaned or deleted by its exact key. Never delete by pattern: that Redis is shared with other apps. Each room's history key is rebuilt from MySQL on first miss, as today.

## Constraints

- **No migration files in this repo.** The rooms table and `room_id` are an `ahd-admin` migration. This repo only edits the sqlite shim `2024_01_01_000005_create_tag_shim_tables.php`, behind `Schema::hasTable` / `hasColumn`.
- **Prod is MariaDB 10.1.47.** No `json` columns (use `longText` with the array cast), no JSON SQL functions, CTEs or window functions, no `SKIP LOCKED`/`NOWAIT`. The rooms migration and its backfill must follow this; tests run on sqlite and will not catch a violation.
- Redis history stays one key per room under the app prefix and degrades to MySQL when Redis is down.
- Authorisation runs at subscribe time only, so a ban does not evict a connected socket. Rooms are public, which is why that is acceptable. Do not change this.
- The server is the only authority. Client rank logic only decides which buttons to show.
- Admin: rooms get create, rename, reorder and deactivate on the existing admin chat page. The cosmetic form gains the `background` type.
- Public pages are Blade + Alpine; the admin page is Inertia + Vue. Keep that split.
- **The `:disabled="!!busy[...]"` fix in `resources/views/components/chat/message.blade.php` ships to `main` on its own before the rooms branch exists.** The redesign rewrites that file, and pushed `main` currently has unclickable moderation buttons.

## Left to the builder

Table and column names, slug rules and the reserved-word handling, how the room list reaches the page, presence channel naming, the visual design inside layout C (spacing, colours, the mobile collapse of the sidebar), the tint opacity, and test structure.

## Risks to carry into the plan

- A member who owns a background whose cosmetic is later deactivated: existing behaviour is that owned items keep rendering. Same here.
- Phone widths: layout C was only mocked at desktop width.
- Deactivating a room with members online: they stay connected to a dead channel until they reload; new posts are rejected.
