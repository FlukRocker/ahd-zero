# Deploying the chat lobby

Run in this order. Step 1 is in a different repo (`ahd-admin`) and must come
first. Every production command uses `php84` / `composer84`, never `php` or
`composer`, because that is what the shared host provides.

## 1. ahd-admin — create the tables

The chat tables are owned by `ahd-admin` (commit `e5879b0`, migration
`database/migrations/2026_11_07_000001_create_chat_tables.php`). ahd-v2 does
not create them.

### 1a. Confirm which database the migration will hit

`ahd-admin`'s local `.env` points at a **remote** MySQL host. Before running
anything, check that the connection is the production database:

```bash
cd /www/wwwroot/admin.animehdzero.com
php84 artisan db:show
```

Check the host and database name in the output. If they are not the
production database, stop.

### 1b. Check the `members` primary key type

The new tables add foreign keys to `members.id`. Those keys only work if
`members.id` is `BIGINT UNSIGNED`. ahd-v2's own migration says it is, but
ahd-v2's `migrations` table is out of sync with the real database, so verify
the real table:

```sql
SHOW CREATE TABLE members;
```

The `id` column must read `bigint(20) unsigned NOT NULL AUTO_INCREMENT`. If it
is anything else (for example signed `bigint` or `int`), do not run the
migration. Fix the schema first.

### 1c. Run the migration by explicit path

```bash
php84 artisan migrate --force --path=database/migrations/2026_11_07_000001_create_chat_tables.php
```

Never run a bare `php84 artisan migrate` on this box. It fails on
`0001_01_01_000000_create_users_table` with "table already exists", because the
`migrations` table is out of sync.

**Untested on MySQL:** this migration has only been run against in-memory
sqlite in the test suite. Its `enum` columns and foreign-key creation have
not been run against MySQL/MariaDB. Watch the output of the command above
and check the tables afterwards (`SHOW TABLES LIKE 'chat_%';`).

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

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=animehdzero.net
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

The two halves are **not** interchangeable. `REVERB_*` is how PHP reaches the
daemon: loopback, plain http, port 8080. `VITE_REVERB_*` is baked into the
JS bundle and is how a browser reaches it: the public hostname over wss on
443, because Cloudflare terminates TLS and never exposes 8080.

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
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 3600s;
}
```

`proxy_read_timeout` must be long: the default 60s kills idle sockets every
minute and the room reconnects in a loop.

## 5. Start the daemon

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

This is PM2 and not cron on purpose: root's crontab currently has a
syntax error on line 3, so Debian discards the whole file and nothing
scheduled runs on this box. That still needs fixing, but the chat does not
wait on it.

## 6. Cloudflare

Websockets must be ON for the zone (Network → WebSockets). `/chat` is excluded
from the shared-cache headers in `SecurityHeaders`, but purge the zone once
after deploy so no stale guest render survives.

## Smoke check

1. Open `https://animehdzero.net/chat` in two browsers, one logged in, one not.
2. Post from the logged-in one. The message appears in the other browser
   within a second, without a refresh.
   If it only appears after a refresh, the build did not have the
   `VITE_REVERB_*` values (see 3a). Rebuild and check again.
3. `pm2 logs ahd-reverb` shows the connection, with no repeated reconnects.
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
