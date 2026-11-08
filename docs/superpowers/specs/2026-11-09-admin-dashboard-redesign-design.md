# Admin dashboard redesign + reports (design)

Date: 2026-11-09. Status: draft for review.

## Intent

The admin is the stock Laravel Vue starter kit: a five-item sidebar that still
links to "Github Repo" and "Documentation" for the starter kit, and a home page
that is a loose set of stat cards. It works but does not read as a proper
dashboard, and moderators have no way to learn what members think is wrong:
they have to read every comment and chat message themselves.

This redesign gives the admin a real shell and a home page that answers "how is
the site doing and what needs me", and adds member reporting so moderation
becomes a queue rather than a trawl.

## Goals

- A grouped-sidebar admin shell ("layout A", chosen from mockups): sidebar
  sections Overview (Dashboard), Community (Members, Comments, Chat, Reports)
  and System (Site settings); top bar with search and the signed-in admin.
- A dashboard home with four blocks:
  1. KPI cards (views today / week / month, members, anime, episodes) and the
     30-day views chart.
  2. Latest comments with inline delete and ban.
  3. Recent chat messages and recent chat moderation actions.
  4. Trending anime and top referrers.
  Plus a "Needs attention" box showing the open report count, linking to
  Reports.
- Member reporting: verified, signed-in members can report a comment or a chat
  message. Admins work a Reports queue.
- Remove the starter-kit footer links.

## Non-goals

- Ads and banner management. It stays out of the admin entirely; ads keep being
  edited as config files on the server.
- Catalogue (anime / episode) management. That lives in the separate
  `ahd-admin` app.
- An "online in chat" number (the data is in Reverb presence, not readable
  server-side cheaply).
- Separate Analytics or Cache pages. Analytics is the home page; cache clearing
  stays in Site settings.
- Reporting anything other than comments and chat messages (no member, anime
  or episode reports).
- Anti-goal: a redesign that breaks existing moderation. Every current admin
  action (ban/unban/delete member, delete comment, delete all by user,
  delete-and-ban, chat rank/ban/grant/cosmetics/rooms) must keep working with
  the same routes and behaviour.

## Constraints (from the codebase)

- Admin pages are Inertia + Vue 3 inside `layouts/AppLayout.vue`. Keep that
  stack; the public site stays Blade.
- Stats come from existing sources: anime/episode/member counts from MySQL, page
  views, trending and referrers from `AnalyticsService` (MongoDB). The home page
  must degrade gracefully (cards show a dash, page still loads) if Mongo is
  slow or down.
- Comments live in MongoDB (string `_id`); chat messages and members live in
  MySQL (integer ids, `members` uses a uuid for public identity). Reports must
  point at either kind.
- Do not create migration files in this repo. The new table goes in
  `ahd-admin` as a migration, applied to prod by hand by explicit path.
  Production is MariaDB 10.1: no json column type, no JSON functions; DDL is not
  transactional, so guard with `hasTable`/`hasColumn`; adding a column rebuilds
  the table. Add a guarded shim for the test schema to an existing
  `2024_01_01_*` shim file, as the chat tables did.
- Realtime and admin sessions: admins now also sign in as a linked member
  (`LinkAdminToMember`), so an admin can report too. Reporting and moderation
  must not assume the reporter is a non-admin.
- Strings: the public site UI is Thai; the admin UI is English today and stays
  English.

## Reports

Data (one table, `reports`):
- `id`, `member_id` (reporter; `unsignedBigInteger` referencing `members.id`,
  the same type and foreign key the chat tables already use on prod),
  `target_type` (`varchar(16)`: `comment` | `chat_message`), `target_id`
  (`varchar(32)`: a Mongo ObjectId is 24 hex chars, chat ids are integers),
  `reason` (`varchar(16)`: `spam` | `abuse` | `other`), `note` (optional,
  `varchar(500)`), `status` (`varchar(16)`: `open` | `actioned` | `dismissed`),
  `resolved_by` (admin user id, nullable), `resolved_at`, `created_at`,
  `updated_at`.
- Column lengths are explicit on purpose: the default `varchar(255)` on a
  utf8mb4 unique key exceeds MariaDB 10.1's 767-byte index limit and the
  migration would fail half-built (DDL is not transactional there).
- Unique on (`member_id`, `target_type`, `target_id`): one report per member
  per item. Reporting again is a no-op that returns success.
- Index on (`status`, `created_at`) for the queue.

Rules:
- Only a verified, non-banned member can report; the endpoint is throttled per
  member. Guests and unverified members do not see the Report action.
- You cannot report your own comment or message.
- A reported item can be deleted by its author, a chat moderator or an admin at
  any time. Deleting an item through the existing delete paths auto-resolves its
  open reports as `actioned`. The queue must still handle a target that is gone
  (show "deleted", offer Dismiss).
- Comment targets: the comment's `site` must equal this site's `app.site_key`
  (the Mongo collection is shared); reject others. A comment's author is its
  `user_id` (member uuid), which can be null or point at a deleted member: then
  Ban author is hidden and only Delete / Dismiss are offered.
- Reporter identity is visible to admins only, never to the reported member.
- Resolving: an admin chooses Delete item (marks the report `actioned`), a ban
  (below, marks `actioned`), or Dismiss (marks `dismissed`). Resolving one
  report resolves all open reports on the same target.
- Delete item and Ban author must reuse the existing behaviour, not raw row
  deletes: deleting a chat message must also clear the per-room Redis history
  and broadcast `MessageDeleted` (as `ChatManagementController::destroyMessage`
  does); deleting a comment must follow the existing admin comment delete /
  delete-and-ban semantics. The shared logic is extracted into services that
  both the current controllers and the Reports page call. Existing routes and
  responses stay unchanged.
- Banning (owner's decision): a chat report offers both "Ban from chat" (the
  existing chat-only ban, `ChatManagementController::ban`) and "Ban from site"
  (the existing member ban, `MemberManagementController::ban`). A comment report
  offers the existing delete-and-ban comment path. Either ban marks the report
  `actioned`.
- The Reports queue is admin-only (the `auth` area). Chat moderators keep acting
  from the lobby menu.

Member-facing surface:
- Comments: a "Report" action in the comment item menu (Vue component on the
  anime/episode pages).
- Chat: a "Report" entry in the message action menu in the lobby for any
  message that is not the member's own. Chat moderators keep their existing
  moderation actions.

Admin surface:
- Reports page: queue of open reports, newest first, grouped by target, showing
  the item text, author, report count, reasons and notes; status filter (open,
  actioned, dismissed).
- Sidebar badge with the open count.
- Dashboard "Needs attention" box shows the same count and links to Reports.

## Dashboard and shell details

- The existing Dashboard page, `AppLayout.vue` and the settings pages (profile,
  password, 2FA, appearance) all move into the new shell: `AppLayout.vue` is
  rewritten in place, not duplicated.
- The sidebar "open reports" badge comes from an Inertia shared prop on admin
  requests: one indexed `count` on `reports(status, created_at)`, no caching,
  so it is never stale after resolving.
- The top-bar search filters the Members list by name or email (adding that
  filter if Members does not already have it). No global search backend.
- The "latest comments" feed reads Mongo and renders an empty state with a short
  notice if Mongo is slow or down; the rest of the page still loads. If it needs
  a new Mongo index, ship it as an idempotent artisan command run by hand, like
  `analytics:index-page-views`, and list it as a deploy step.
- Report UI (public): a small modal with a reason picker and an optional note,
  opened from the Report menu item. Thai strings for the member-facing text are
  written by the builder and listed in the PR for the owner to review.

## Delivery

- Order: shell, then home, then reports. Reports ships last because it needs the
  table.
- The `reports` migration is added to `ahd-admin` (guarded with `hasTable`,
  `hasColumn`-style checks) and applied to prod by hand by explicit path after
  the owner has backed up the database, as with the chat tables. A guarded test
  schema shim goes into an existing `2024_01_01_*` shim file here.

## Decisions I made (not from the owner)

- Reason list is fixed at spam / abuse / other.
- One report per member per item.
- Report statuses open / actioned / dismissed.
- Sidebar grouping and the removal of Analytics/Cache pages.
- Admin UI stays English.
- Queue is admin-only; deleting an item auto-resolves its reports.
- Badge uncached; top-bar search filters Members only; Thai strings by the
  builder, reviewed by the owner.

## Left to the builder

- Visual design details inside layout A (spacing, icons, chart styling,
  responsive behaviour of the sidebar), within the existing design tokens.
- Query shape and caching for the dashboard stats and for "latest comments"
  and "recent chat" feeds.
- Component breakdown, test strategy, how to stage the work (shell first,
  then home, then reports).
- Exact throttle numbers and note length limit.
