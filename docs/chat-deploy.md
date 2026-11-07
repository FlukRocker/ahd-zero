# Deploying the chat lobby

Run in this order. Step 1 is in a different repo (`ahd-admin`) and must come
first. Every production command uses `php84` / `composer84`, never `php` or
`composer`, because that is what the shared host provides. `ahd-admin` is
Laravel 8 and every path below is from notes, not a checked inventory: **verify
every path and the `php84` binary on the box** before running anything.

## 1. ahd-admin — create the tables

The chat tables are owned by `ahd-admin` (commit `e5879b0`, migration
`database/migrations/2026_11_07_000001_create_chat_tables.php`). ahd-v2 does
not create them.

### 1a. Confirm which database the migration will hit

`ahd-admin`'s local `.env` points at a **remote** MySQL host. Before running
anything, check that the connection is the production database:

```bash
cd /www/wwwroot/admin.animehdzero.com   # verify on the box
grep -E '^DB_(CONNECTION|HOST|PORT|DATABASE)=' .env
```

`ahd-admin` is Laravel 8.83 and has no `db:show`. Check the host and database
name in the output. If they are not the production database, stop. To confirm
what the connection really reaches, open `php84 artisan db` (verify on the box)
and run `SELECT @@hostname, DATABASE();`.

### 1b. Check the `members` primary key type

The new tables add foreign keys to `members.id`. Those keys only work if
`members.id` is `BIGINT UNSIGNED`. ahd-v2's own migration says it is, but
ahd-v2's `migrations` table is out of sync with the real database, so verify
the real table:

```sql
SHOW CREATE TABLE members;
```

The `id` column must read `bigint(20) unsigned NOT NULL AUTO_INCREMENT`
(MariaDB) or `bigint unsigned NOT NULL AUTO_INCREMENT` (MySQL 8); both satisfy
the check. If it is anything else (for example signed `bigint` or `int`), do
not run the migration. Fix the schema first.

The table must also say `ENGINE=InnoDB`. The coin store's `lockForUpdate`, its
transactions and the no-double-charge argument all depend on InnoDB row
locking. If `members` is MyISAM, stop.

### 1c. Run the migration by explicit path

```bash
php84 artisan migrate --force --path=database/migrations/2026_11_07_000001_create_chat_tables.php
```

Do not run a bare `php84 artisan migrate` against this remote database: be
explicit about exactly what runs there. (In `ahd-v2` a bare `migrate` also dies
on `0001_01_01_000000_create_users_table` because that app's `migrations` table
is out of sync; that failure mode belongs to `ahd-v2`, not `ahd-admin`.)

**Untested on MySQL:** this migration has only been run against in-memory
sqlite in the test suite. Its `enum` columns and foreign-key creation have
not been run against MySQL/MariaDB. Watch the output of the command above
and check the tables afterwards (`SHOW TABLES LIKE 'chat_%';`), including
that every one of them is InnoDB:

```sql
SELECT table_name, engine FROM information_schema.tables WHERE table_name LIKE 'chat\_%';
```

**Recovery after a failed run.** MySQL DDL is not transactional, so a run that
fails part-way can leave some `chat_*` tables behind, and a re-run then dies
with "table already exists". Inspect first:

```sql
SHOW TABLES LIKE 'chat\_%';
SELECT table_name, table_rows FROM information_schema.tables WHERE table_name LIKE 'chat\_%';
```

DESTRUCTIVE, operator decision: dropping the partial `chat_*` tables (and only
those, children before parents because of the foreign keys) lets the migration
run again. Confirm with the owner before running any `DROP TABLE`, and only do
it if the tables are empty. Never drop anything that is not named `chat_*`.

If ahd-v2 deploys before this runs, `/chat` 500s on first load.

## 2. ahd-v2 — env

Set these in the production `.env` on the server:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<generate>
REVERB_APP_KEY=<generate>
REVERB_APP_SECRET=<generate>
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_APP_ALLOWED_ORIGINS=animehdzero.net

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=animehdzero.net
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

The two halves are **not** interchangeable. `REVERB_*` is how PHP reaches the
daemon: loopback, plain http, port 8080. `VITE_REVERB_*` is baked into the
JS bundle and is how a browser reaches it: the public hostname over wss on
443, because Cloudflare terminates TLS and never exposes 8080.

`REVERB_APP_ALLOWED_ORIGINS` is a comma-separated list of browser Origin
**host names** (no scheme, no port) that Reverb accepts for websocket
connections. It defaults to `animehdzero.net`; it must never be `*`. Client
events (whispers) are hard-switched off in `config/reverb.php` and per-app rate
limiting is on, so there is nothing to set for those.

Generate the three `<generate>` values once and keep them. Do not regenerate
`REVERB_APP_KEY` later without rebuilding the assets (see step 3), because the
bundle carries the old key.

## 3. ahd-v2 — deploy

### 3a. The build machine needs the `VITE_REVERB_*` values

`VITE_*` variables are read by Vite **at build time** and inlined into the
JavaScript. They must be present in the environment of `pnpm build` on the
machine that builds the assets. Without them the bundle has no socket config,
and the chat's `connect()` returns early without opening a connection. The page
still loads and still posts over HTTP, but nothing arrives live, and nothing
reports an error.

Make sure `VITE_REVERB_APP_KEY` and `VITE_REVERB_HOST` (plus `VITE_REVERB_PORT`
and `VITE_REVERB_SCHEME`) are set, either in the `.env` in the project root
(as in step 2) or in the shell that runs the build. If you change any
`VITE_REVERB_*` value, run `pnpm build` again. Changing it in `.env` alone
does nothing to the assets already built.

### 3b. Build and install

```bash
cd /www/wwwroot/ahd-v2
pnpm install --prod=false
pnpm build
composer84 install --no-dev --optimize-autoloader
php84 artisan optimize
```

Once new code is live, restart the daemon (step 5) so it runs the new code.
A running Reverb process keeps the code and config it started with.

## 4. nginx

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 3600s;
}
```

Use `/app/` with the trailing slash: a bare `/app` prefix would also catch
`/apple-touch-icon.png` and `/apps/*`. (Verify the nginx file location on the
box.)

`proxy_read_timeout` must be long: the default 60s kills idle sockets every
minute and the room reconnects in a loop.

## 5. Start the daemon

Check that port 8080 is free first. Reverb refuses to start (or worse, a stale
process answers) if something already holds it:

```bash
ss -ltnp | grep ':8080' || echo "8080 is free"
```

```bash
REVERB_WORKER=1 PHP_BIN=php84 pm2 start ecosystem.config.cjs --env production
pm2 save
```

`pm2 save` matters: without it the daemon does not come back after a reboot.

On later deploys, restart it instead of starting it again:

```bash
pm2 restart ahd-reverb
pm2 save
```

PM2, because Reverb is a long-running process.

`pm2 logs ahd-reverb` only shows connections if Reverb was started with
`--debug`; without it the log is nearly silent even when the room works.

**Capacity.** Without the `ext-uv` PHP extension and a raised `ulimit -n`,
Reverb tops out around 1,000 connections, not the ~2,000 the spec assumes.
Install `ext-uv` and raise the file-descriptor limit for the PM2 process before
launching to a large audience.

## 6. Cloudflare

Websockets must be ON for the zone (Network → WebSockets). `/chat` is excluded
from the shared-cache headers in `SecurityHeaders`. Do **not** purge the whole
zone: on this shared host that empties the SEO cache for the entire site.
`/chat` never existed before this release, so at most purge that single URL.

## Smoke check

1. Open `https://animehdzero.net/chat` in two browsers, one logged in, one not.
2. Post from the logged-in one. The message appears in the other browser
   within a second, without a refresh.
   If it only appears after a refresh, the build did not have the
   `VITE_REVERB_*` values (see 3a). Rebuild and check again.
3. `pm2 logs ahd-reverb` shows the connection, with no repeated reconnects
   (only if Reverb runs with `--debug`).
4. Guest response headers for `/chat` contain no `s-maxage`.

## Known limitations

These are known and accepted for this release. None is blocking, but do not
assume the opposite.

- **Row locks and deadlocks in the coin store are reasoned about, not run.**
  The coin store relies on `lockForUpdate` for real InnoDB row locking. The
  test suite runs on sqlite, which ignores `lockForUpdate`, so lock behaviour
  and deadlock handling under MySQL/MariaDB were checked by reading the code
  only. Watch the logs for deadlock errors after launch.
- **Channel authorisation is tested only against the null broadcaster.**
  Tests run with `BROADCAST_CONNECTION=null`, which never calls channel
  callbacks. The `chat.roster` presence-channel check (`RosterChannel`) is
  tested directly as a class, but the real Reverb subscribe path has not been
  exercised by an automated test.
- **The presence roster does not yet show equipped cosmetics.** The roster
  payload carries only uuid, name and rank. The spec says it should also carry
  the equipped cosmetics.
- **Cached chat history can show a stale cosmetic style for up to 5 minutes.**
  The recent-history list is cached in Redis for 300 seconds. A message served
  from that cache can show the sender's style from before a cosmetic change
  until the cache entry expires or is rebuilt.
- **What the UI covers now.** The site header links to `/chat` (everyone) and
  `/chat/store` (logged-in members only). The store page buys and equips
  cosmetics. Lobby messages carry delete, gag, kick and ban controls. The admin
  page has a create-cosmetic form, grants, a message list with removal, and
  paginated members.
- **Lobby moderation is deliberately narrow.** A lobby ban is a fixed 24 hour
  timed ban after a confirm; permanent bans are site-admin only. Deleting
  someone else's message asks for confirmation. Gag is 10 minutes and kick is
  60 seconds (`ModerationService::kick`). There is no ungag control anywhere;
  gags expire after 10 minutes.
- **"Admin-page only" means the site-admin dashboard.** That is the web-guard
  dashboard at `/dashboard/chat`. A chat owner or main owner is a member and
  cannot reach it, so owners have no in-room UI for unban, permanent ban or
  rank changes. Only a site admin can do those.
- **No unequip in the store.** The store only equips; equipping another item
  in the same slot replaces it.
- **The controls shown can be stale.** `myRank` is a fresh `ChatMember` query
  at page load, but each history line's author `rank` comes from the Redis
  history (up to 300 seconds old) and live lines never refresh it. The UI may
  offer or hide a control that the server then accepts or refuses. The server
  is the authority, and its message is shown inline.
- **Pawn and background types are hidden in the UI only.** They are hidden from
  the store and from the admin create-cosmetic form, but `buy` does not filter
  by type at the API level, so keep such cosmetics inactive.
- **The lobby message menu opens downward** and may be clipped on the bottom
  line. This is unverified in a browser.
- **Pawn and background cosmetics have no visible effect yet.** The server emits
  `--chat-pawn` and `--chat-background`, but the client and CSS do not use them,
  and no `public/chat/backgrounds/` assets exist, so backgrounds cannot be
  saved. Keep pawn and background out of the v1 catalogue until they are built.
- **Admin dashboard powers are not constrained by `ChatPolicy`, by design.**
- **No reconnect UI and no refetch after a socket gap.** A client that loses
  the socket misses messages sent meanwhile until it reloads.
- **A delete racing a history rebuild can resurrect a deleted line** in the
  cached history for up to 300 seconds.
- **Unverified spec note:** the spec mentions a concern about root's crontab.
  That is unverified and not part of this deploy; do not edit it as a step here.
