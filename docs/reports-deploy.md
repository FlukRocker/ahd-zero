# Reports + admin dashboard redesign: deploy runbook

Branch `feat/admin-dashboard` (ahd-v2) and one migration in `ahd-admin`.
Nothing here runs until the owner says go: pushing, applying the migration and
deploying are each a separate decision.

## What ships

- Admin shell: grouped sidebar (Overview / Community / System), top-bar member
  search, open-reports badge, starter-kit footer links removed.
- Dashboard home: KPI cards, 30-day views, Needs attention, latest comments
  (inline delete / ban), chat activity + moderation log, trending, referrers.
- Member reports of comments and chat messages, and the admin Reports queue
  (`/dashboard/reports`).
- New `reports` table (migration lives in `../ahd-admin`).

## Order

Any order is safe. `OpenReports::count()` and `ReportResolver::resolveTarget()`
return 0 / do nothing while the `reports` table is missing, so admin delete,
comment delete and the dashboard keep working before the migration. Only the
Reports page and the member Report action need the table; until it exists the
Report action answers 503 "Reporting is not available yet." (not a 500) and the
Reports page shows an empty queue.

Recommended: table first, then code.

1. **Back up the database** (owner).
2. **ahd-admin:** push the repo (`feat: create reports table`).
3. **Apply the migration on prod, by explicit path** (guarded, MariaDB 10.1 safe,
   a re-run is a no-op; never a bare `migrate`):

   ```bash
   cd <ahd-admin dir> && php84 artisan migrate --force \
     --path=database/migrations/2026_11_09_000001_create_reports.php
   ```

   Check: `DESCRIBE reports;` shows `target_type varchar(16)`, `target_id varchar(32)`,
   unique `reports_member_target_unique`, index `reports_status_created_at_index`,
   FK to `members`.
4. **ahd-v2:** push `feat/admin-dashboard`, merge to `main`, then on the server
   (`/www/wwwroot/ahd-v2`):

   ```bash
   git rev-parse HEAD > /root/ahd-v2-rollback-sha.txt
   git pull --ff-only
   PATH=/root/.local/share/pnpm:/root/.nvm/versions/node/v22.22.0/bin:$PATH pnpm build
   php84 artisan optimize
   chown -R www:www public/build
   PATH=/root/.nvm/versions/node/v18.16.0/bin:$PATH pm2 restart ahd-reverb
   ```

   No new env vars, no new pm2 processes, no new artisan command to run.
5. If the mini chat bubble is deployed with this, its panel must include
   `<x-chat.report-modal />`, or the chat "Report" entry has no dialog.

## Rollback

`git reset --hard $(cat /root/ahd-v2-rollback-sha.txt)` then `pnpm build` and
`php84 artisan optimize`. The `reports` table is additive; leave it in place.

## Smoke list (after deploy)

- `/dashboard`: KPI cards, Needs attention shows `0 open reports`, latest
  comments render (or show "Comments unavailable right now" if Mongo is slow).
- Sidebar shows the three groups, no "Github Repo" / "Documentation".
- Top-bar search for a member name lands on `/dashboard/members?search=...`.
- `/settings/profile` renders inside the new shell.
- As a verified member: report a chat message of someone else and a comment; the
  Reports page lists them grouped by item and the sidebar badge matches.
- Reports page: Dismiss, Delete, Ban from chat / site (chat items), Delete and
  ban (comment items). A deleted item shows "deleted" with Dismiss only.
- Reporting your own message is refused; reporting the same item twice is a
  no-op that still says thanks.
- Deleting a reported chat message as an admin or moderator closes its report.
