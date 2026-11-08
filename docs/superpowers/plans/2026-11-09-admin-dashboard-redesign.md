# Admin dashboard redesign + reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the admin a grouped-sidebar shell and a real dashboard home, and add member reporting (comments + chat messages) with an admin Reports queue.

**Architecture:** Rewrite the existing Inertia/Vue admin chrome in place (`AppSidebar.vue`, `AppSidebarHeader.vue`, `Dashboard.vue`); add a `reports` table (migration lives in `../ahd-admin`, guarded shim here for tests); extract the existing delete/ban logic into services so both the current controllers and the new Reports queue use one code path; a `ReportTargetResolver` contract hides the Mongo-backed comments so tests need no Mongo.

**Tech Stack:** Laravel 12 / PHP 8.4, Inertia + Vue 3 (admin), Blade + Alpine (public chat), MongoDB (comments, analytics), MariaDB 10.1 (prod) / sqlite (tests), PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-09-admin-dashboard-redesign-design.md`

## Global Constraints

- Do not create migration files in this repo. The `reports` migration goes in `../ahd-admin/database/migrations/`; here only add a `Schema::hasTable()`-guarded shim to `database/migrations/2024_01_01_000005_create_tag_shim_tables.php`.
- Prod is MariaDB 10.1: no `json` type, no JSON functions; guard DDL with `hasTable`; `reports` columns use explicit lengths (`target_type` varchar(16), `target_id` varchar(32), `reason` varchar(16), `status` varchar(16), `note` varchar(500)); `member_id` is `unsignedBigInteger` FK to `members.id`.
- Admin UI strings stay English. Member-facing strings (Report button, modal, toasts) are Thai.
- Existing admin routes, URLs and JSON responses must not change (anti-goal: do not break moderation). `ChatManagementController::destroyMessage` must keep clearing per-room history and broadcasting `MessageDeleted`.
- Comments are Mongo documents (string `_id`, `user_id` = member uuid, nullable, `site` must equal `config('app.site_key')`). There is no Mongo in the PHPUnit run: anything touching `Comment` in a test goes through a fake.
- Never use `x-html` / `innerHTML` in chat markup. Run `./vendor/bin/pint` and `vendor/bin/phpstan analyse` before each commit; do not add new phpstan errors (6 pre-existing).
- Commit style: Conventional Commits, trailer `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

Failure modes the spec implies but the obvious tests would miss. Each has a test in the owning task.

1. Reporting a target that was deleted / does not exist / is on another site must fail cleanly, not 500 (Task 4).
2. Reporting your own item, or reporting twice, must not create rows (Task 4).
3. Deleting an item through any existing path must auto-resolve its open reports and still clear chat history + broadcast (Task 3).
4. A reported comment with null/unknown `user_id`: Ban actions hidden/rejected, Delete and Dismiss still work (Task 5).
5. Mongo down: dashboard still renders; the comments block shows its empty state (Task 2).

---

### Task 1: Admin shell (sidebar groups, top bar, report badge)

**Files:**
- Modify: `resources/js/components/AppSidebar.vue` (nav groups, drop footer starter links), `resources/js/components/NavMain.vue` (render titled groups, optional badge), `resources/js/components/AppSidebarHeader.vue` (search box), `app/Http/Middleware/HandleInertiaRequests.php` (share `openReports`), `resources/js/types/index.d.ts` (`NavItem.badge?: number`, `NavGroup`)
- Create: `app/Support/OpenReports.php`
- Test: `tests/Feature/AdminShellTest.php`, `tests/js/adminSidebar.test.ts`

**Interfaces:**
- Produces: `App\Support\OpenReports::count(): int` (0 when the `reports` table does not exist, otherwise count of rows with `status = 'open'`); Inertia shared prop `openReports: int` on every admin request; `NavGroup = { title: string; items: NavItem[] }`.

- [ ] **Step 1: Failing tests.** PHP `AdminShellTest`: `test_open_reports_count_is_zero_when_the_table_is_missing`; `test_authenticated_admin_pages_share_open_reports` (log in an admin without 2FA like `AdminMemberLinkTest::admin()`, GET `/dashboard`, assert Inertia prop `openReports` is `0`). Vitest `adminSidebar.test.ts` (mount `AppSidebar.vue` with Inertia mocked as `adminChatRooms.test.ts` does): asserts group titles `Overview`, `Community`, `System`; item titles Dashboard, Members, Comments, Chat, Reports, Site settings; no text `Github Repo` or `Documentation`; a badge with the number when `openReports > 0`.
- [ ] **Step 2: Run, confirm FAIL.** `vendor/bin/phpunit --filter=AdminShellTest` and `pnpm test -- adminSidebar`.
- [ ] **Step 3: Implement.** `OpenReports::count()` uses `Schema::hasTable('reports')` then `DB::table('reports')->where('status','open')->count()`. Share `openReports` in `HandleInertiaRequests::share()` only when an admin (`web` guard) is authenticated. Regroup `AppSidebar.vue` as spec: Overview [Dashboard]; Community [Members, Comments, Chat, Reports `/dashboard/reports` with badge]; System [Site settings]. Remove the footer starter-kit links and `NavFooter` use. Top bar: a search input that submits GET to `/dashboard/members?search=<q>` (verify `MemberManagementController::index` honours `search`; add the name/email filter if missing, with a feature test).
- [ ] **Step 4: Run tests, confirm PASS**, then full `vendor/bin/phpunit` and `pnpm test`.
- [ ] **Step 5: Commit** `feat(admin): grouped sidebar shell with report badge`. (Settings pages already render inside `AppLayout`, so they pick up the new shell with no extra change; confirm by loading `/settings/profile` in the browser smoke at the end.)

### Task 2: Dashboard home

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`, `resources/js/pages/Dashboard.vue`
- Create: `app/Services/AdminFeeds.php`
- Test: `tests/Feature/DashboardTest.php` (extend), `tests/Unit/AdminFeedsTest.php`

**Interfaces:**
- Produces: `App\Services\AdminFeeds` with `latestComments(int $limit = 8): ?array` (rows `{id, user_name, body, created_at, deleted}` or `null` when Mongo throws), `recentChat(int $limit = 8): array` (rows `{id, room, member_name, body, created_at}` from non-deleted `ChatMessage`), `modLog(int $limit = 8): array` (rows `{action, target_member_name, created_at}` from `ChatModerationLog`). Inertia props added to `Dashboard`: `latestComments` (nullable), `recentChat`, `modLog`, `openReports`.

- [ ] **Step 1: Failing tests.** `AdminFeedsTest`: `test_recent_chat_returns_newest_first_and_skips_deleted`; `test_mod_log_returns_newest_first`; `test_latest_comments_is_null_when_mongo_fails` (inject a closure/seam so the Mongo call throws; do not need a real Mongo). `DashboardTest`: props include `recentChat`, `modLog`, `openReports`, and a `latestComments` key that is `null` in the test environment (no Mongo) while the response is still 200 (Review Focus 5).
- [ ] **Step 2: Run, confirm FAIL.**
- [ ] **Step 3: Implement** `AdminFeeds` (wrap the comments query in try/catch, return null; truncate bodies to 140 chars server-side) and pass through `DashboardController::index`. Rewrite `Dashboard.vue` to the layout-A home: KPI cards row, 30-day views chart (reuse the existing sparkline data), "Needs attention" box (`openReports`, link to `/dashboard/reports`), latest comments with inline Delete / Ban buttons calling the existing `DELETE /dashboard/comments/{id}` and `POST /dashboard/comments/{id}/delete-and-ban`, recent chat + mod log, trending + top referrers. Empty state text "Comments unavailable right now" when `latestComments === null`.
- [ ] **Step 4: Run tests, confirm PASS** (+ full suites).
- [ ] **Step 5: Commit** `feat(admin): dashboard home with feeds and needs-attention box`.

### Task 3: reports table, model, shared moderation services

**Files:**
- Create: `../ahd-admin/database/migrations/2026_11_09_000001_create_reports.php`, `app/Models/Report.php`, `app/Services/Moderation/ChatMessageModerator.php`, `app/Services/Moderation/CommentModerator.php`, `app/Services/Moderation/ReportResolver.php`
- Modify: `database/migrations/2024_01_01_000005_create_tag_shim_tables.php` (guarded `reports`), `app/Http/Controllers/Admin/ChatManagementController.php` (`destroyMessage` delegates), `app/Http/Controllers/Admin/CommentManagementController.php` (`destroy`, `destroyAndBan` delegate)
- Test: `tests/Unit/Moderation/ReportResolverTest.php`, `tests/Feature/Chat/AdminChatTest.php` (extend), `tests/Feature/Moderation/ReportSchemaTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Report` (fillable `member_id,target_type,target_id,reason,note,status,resolved_by,resolved_at`; consts `TYPE_COMMENT='comment'`, `TYPE_CHAT='chat_message'`; `REASONS = ['spam','abuse','other']`; `STATUS_OPEN/ACTIONED/DISMISSED`).
  - `ReportResolver::resolveTarget(string $type, string $id, string $status, ?int $adminId): int` (updates every open report on that target; returns rows changed).
  - `ChatMessageModerator::delete(ChatMessage $message, int $adminId): void` (exactly the body of `destroyMessage` today: set `deleted_by_admin_id`, soft delete, `ChatModerationLog` `delete_message`, `ChatService::forgetHistory`, `MessageDeleted::dispatch`, then `ReportResolver::resolveTarget('chat_message', (string) $id, 'actioned', $adminId)`).
  - `CommentModerator::delete(Comment $c, ?int $adminId): void`, `CommentModerator::deleteAndBan(Comment $c, ?string $reason, bool $deleteAll, ?int $adminId): void` (bodies moved from the controller; both call `resolveTarget('comment', $c->getKey(), 'actioned', $adminId)`).
- Migration shape: `reports` per Global Constraints, `unique(['member_id','target_type','target_id'])`, `index(['status','created_at'])`, FK `member_id` → `members.id` cascadeOnDelete; whole create wrapped in `if (! Schema::hasTable('reports'))`.

- [ ] **Step 1: Failing tests.** `ReportSchemaTest::test_reports_table_has_the_unique_member_target_key` (insert a duplicate, expect `QueryException`). `ReportResolverTest::test_resolve_target_closes_every_open_report_on_that_target_only` and `::test_already_resolved_reports_are_untouched`. `AdminChatTest::test_admin_delete_still_clears_history_and_broadcasts_and_closes_open_reports` (create an open report for the message first; assert `Event::assertDispatched(MessageDeleted::class)`, history cache cleared as the existing delete test asserts, report status `actioned`).
- [ ] **Step 2: Run, confirm FAIL.**
- [ ] **Step 3: Implement** the shim, model, resolver and the two moderators; make the three controller methods thin callers with unchanged signatures, routes and JSON. Write the `ahd-admin` migration (guarded, explicit lengths); do not run it on prod here.
- [ ] **Step 4: Run tests, confirm PASS**; full `vendor/bin/phpunit` must stay green (existing admin chat tests prove nothing regressed).
- [ ] **Step 5: Commit** in both repos: `feat(reports): reports table and shared moderation services` (ahd-v2) and `feat: create reports table` (ahd-admin).

### Task 4: Member report endpoint

**Files:**
- Create: `app/Contracts/ReportTargetResolver.php`, `app/Support/EloquentReportTargets.php`, `app/Http/Controllers/ReportController.php`, `app/Http/Requests/StoreReportRequest.php`
- Modify: `routes/web.php` (inside the verified-member group that already holds `chat.send`), `app/Providers/AppServiceProvider.php` (bind contract; add `reports` rate limiter keyed by member)
- Test: `tests/Feature/ReportTest.php`

**Interfaces:**
- Produces: `ReportTargetResolver::find(string $type, string $id): ?ReportTarget` where `ReportTarget` is a readonly value object `{type, id, authorMemberId: ?int, excerpt: string, available: bool}` (`available` false for deleted items and items from another site; `authorMemberId` resolved from comment `user_id` uuid → `members.id`, or chat `member_id`). Route `POST /reports` name `reports.store`, body `{target_type: comment|chat_message, target_id: string, reason: spam|abuse|other, note?: string max 500}`. Responses: `201 {ok:true}` created, `200 {ok:true}` when already reported (idempotent), `404` unknown/unavailable target, `422` validation or own item.

- [ ] **Step 1: Failing tests** (bind a fake `ReportTargetResolver` for comment targets; chat targets use real `ChatMessage` rows): `test_a_verified_member_can_report_a_chat_message`; `test_reporting_twice_creates_one_row_and_still_succeeds`; `test_you_cannot_report_your_own_message`; `test_unknown_or_deleted_or_other_site_target_is_404_not_500`; `test_guest_and_unverified_member_are_rejected`; `test_invalid_reason_is_422`; `test_note_over_500_chars_is_422`; `test_reports_are_rate_limited_per_member`.
- [ ] **Step 2: Run, confirm FAIL.**
- [ ] **Step 3: Implement** controller + request + resolver (comment lookup is the only Mongo touch, behind the contract; inside it, reject `site !== config('app.site_key')` and `deleted_by !== null`). Rate limiter `reports`: 10/min per member. Use `firstOrCreate` on the unique key so the duplicate race is safe.
- [ ] **Step 4: Run tests, confirm PASS** (+ full suite).
- [ ] **Step 5: Commit** `feat(reports): member report endpoint`.

### Task 5: Admin Reports queue

**Files:**
- Create: `app/Http/Controllers/Admin/ReportManagementController.php`, `resources/js/pages/admin/Reports.vue`
- Modify: `routes/web.php` (admin group), `tests/Feature/AdminShellTest.php` (badge shows live count)
- Test: `tests/Feature/Admin/ReportManagementTest.php`, `tests/js/adminReports.test.ts`

**Interfaces:**
- Consumes: Task 3 services/`Report`, Task 4 `ReportTargetResolver`.
- Produces routes (all in the `auth, verified` admin group): `GET dashboard/reports` (`admin.reports`, query `status=open|actioned|dismissed`, default open), `POST dashboard/reports/{target}/delete` (`admin.reports.delete`), `POST dashboard/reports/{target}/ban-chat`, `POST .../ban-site`, `POST .../ban-comment`, `POST .../dismiss`; `{target}` is `"{type}:{id}"` so one action resolves every report on the item. Page props: `groups: [{type, id, excerpt, author: {name, memberId|null}, count, reasons: string[], notes: string[], status, available}]`, `filters.status`.
- Behaviour: delete → `ChatMessageModerator::delete` / `CommentModerator::delete`; `ban-chat` → `ModerationService::ban($chatMember, null, reason, null, $adminId)` then `ReportResolver::resolveTarget(..., 'actioned')`; `ban-site` → `Member::update(['banned_at'=>now(),...])` then resolve; `ban-comment` → `CommentModerator::deleteAndBan`; dismiss → `resolveTarget(..., 'dismissed')`. Ban endpoints return 422 when the author is unknown (null member, Review Focus 4). An unavailable target only accepts dismiss.

- [ ] **Step 1: Failing tests.** PHP: `test_queue_lists_open_reports_grouped_by_target_newest_first`; `test_delete_action_deletes_the_chat_message_and_closes_all_its_reports`; `test_ban_chat_and_ban_site_resolve_the_reports_and_apply_the_right_ban`; `test_ban_with_unknown_author_is_422_but_delete_and_dismiss_work`; `test_dismiss_marks_dismissed`; `test_unavailable_target_only_allows_dismiss`; `test_non_admin_cannot_reach_reports`; sidebar badge equals open count. Vitest `adminReports.test.ts`: mounts `Reports.vue`, shows Ban buttons only when `author.memberId` is set, shows both "Ban from chat" and "Ban from site" for chat items and "Delete and ban" for comment items, shows "deleted" + only Dismiss when `available` is false.
- [ ] **Step 2: Run, confirm FAIL.**
- [ ] **Step 3: Implement** controller (comment items go through the fake-able `ReportTargetResolver` for excerpt/author) and `Reports.vue` in the layout-A style with a status filter; use `router.post` + `router.reload` like `admin/Chat.vue`.
- [ ] **Step 4: Run tests, confirm PASS** (+ full suites).
- [ ] **Step 5: Commit** `feat(admin): reports queue`.

### Task 6: Public Report UI (comments + chat)

**Files:**
- Modify: `resources/js/components/comments/CommentItem.vue` (+ `CommentSection.vue` if the emit chain needs it), `resources/js/chat/permissions.js`, `resources/js/chat/lobby.js`, `resources/views/components/chat/message.blade.php`, `resources/views/chat.blade.php` (modal markup)
- Create: `resources/js/components/comments/ReportModal.vue`, `resources/js/lib/reports.ts` (`submitReport(type, id, reason, note): Promise<'created'|'duplicate'>`)
- Test: `tests/js/chatPermissions.test.ts` (extend), `tests/js/reports.test.ts`, `tests/Feature/Chat/ChatPageTest.php` (extend)

**Interfaces:**
- Consumes: `POST /reports` from Task 4.
- Produces: `canReport(myUuid, canPost, line): boolean` exported from `permissions.js` (true only for a verified poster who is not the author); chat menu action key `report`; Thai labels: button `รายงาน`, reasons `สแปม` / `ละเมิด` / `อื่นๆ`, note placeholder `รายละเอียดเพิ่มเติม (ไม่บังคับ)`, success `ขอบคุณ เราจะตรวจสอบ`, duplicate `คุณรายงานรายการนี้แล้ว`.

- [ ] **Step 1: Failing tests.** `chatPermissions`: `test_can_report_only_for_a_verified_member_on_someone_elses_line`; `reports.test.ts`: `submitReport` posts the right JSON and maps 200 to `duplicate`, 201 to `created`, other statuses to a thrown error; `ChatPageTest::test_message_menu_has_a_report_entry_for_posters` (markup contains the report action and no `x-html`).
- [ ] **Step 2: Run, confirm FAIL.**
- [ ] **Step 3: Implement.** Chat: add `report` to the menu list via `canReport`, open one shared modal (reason radios + optional note + submit) rendered once in `chat.blade.php`, submit through `submitReport`, inline Thai feedback; the menu must keep the `z-20` open-row fix. Comments: add a "Report" item to the `CommentItem.vue` menu for signed-in members who do not own the comment (menu currently renders only for owner/admin: widen the condition), open `ReportModal.vue`.
- [ ] **Step 4: Run tests, confirm PASS** (+ full suites).
- [ ] **Step 5: Commit** `feat(reports): report action on comments and chat`.

### Task 7: Deploy runbook and final verification

**Files:**
- Create: `docs/reports-deploy.md`
- Test: manual smoke list inside the doc

- [ ] **Step 1:** Write the runbook: back up prod DB; push `ahd-admin`; apply `2026_11_09_000001_create_reports.php` by explicit `--path` on prod (guarded, MariaDB 10.1 safe); then deploy ahd-v2 (`git pull --ff-only`, `pnpm build` using `/root/.local/share/pnpm` first in PATH, `artisan optimize`, `pm2 restart ahd-reverb`); rollback = previous SHA (the table is additive and can stay). Deploy order matters only for the Reports page; the shell and home work before the table exists because `OpenReports::count()` guards it.
- [ ] **Step 2:** Run `vendor/bin/phpunit`, `pnpm test`, `pint`, `phpstan`; browser-smoke locally on the scratch sqlite env (never the prod `.env`): shell, home, `/settings/profile`, a report round trip on chat.
- [ ] **Step 3: Commit** `docs: reports deploy runbook`. Pushing, applying the migration and deploying each wait for the owner's explicit go.
