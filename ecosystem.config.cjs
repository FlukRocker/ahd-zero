/**
 * PM2 ecosystem for production runtime processes.
 *
 * The web tier no longer needs a Node process: the public/SEO site is
 * server-rendered Blade (real SSR) served by php-fpm/nginx, and the remaining
 * Inertia admin/auth pages (gated + noindex) client-render without an SSR
 * daemon. Node runs at BUILD TIME ONLY. The former `ahd-ssr` Inertia SSR
 * daemon has been removed.
 *
 * Usage:
 *   pnpm install --prod=false
 *   pnpm build                                  # assets only — no build:ssr
 *   composer84 install --no-dev --optimize-autoloader
 *   php84 artisan optimize
 *
 * PM2 here supervises two optional long-running processes, both off by default:
 *   - ahd-queue:  the queue worker (the app dispatches no background jobs yet).
 *       QUEUE_WORKER=1 PHP_BIN=php84 pm2 start ecosystem.config.cjs --env production
 *   - ahd-reverb: the Reverb WebSocket daemon behind the live chat lobby.
 *       REVERB_WORKER=1 PHP_BIN=php84 pm2 start ecosystem.config.cjs --env production
 *   Run `pm2 save` after starting either, or it will not come back after a reboot.
 *   See docs/chat-deploy.md for the full chat deployment order.
 *
 * Shared host conventions:
 *   - PHP/Composer binaries are `php84` / `composer84` (set PHP_BIN=php84).
 */
const PHP_BIN = process.env.PHP_BIN || 'php';
const QUEUE_WORKER_ENABLED = process.env.QUEUE_WORKER === '1';
const REVERB_ENABLED = process.env.REVERB_WORKER === '1';

const apps = [];

if (QUEUE_WORKER_ENABLED) {
    apps.push({
        name: 'ahd-queue',
        script: 'artisan',
        interpreter: PHP_BIN,
        args: 'queue:work --tries=3 --max-time=3600 --max-jobs=1000 --sleep=3',
        exec_mode: 'fork',
        instances: 1,
        autorestart: true,
        watch: false,
        max_memory_restart: '256M',
        env: { APP_ENV: 'production' },
        error_file: 'storage/logs/queue.error.log',
        out_file: 'storage/logs/queue.out.log',
        merge_logs: true,
        time: true,
    });
}

if (REVERB_ENABLED) {
    apps.push({
        name: 'ahd-reverb',
        script: 'artisan',
        interpreter: PHP_BIN,
        args: 'reverb:start --host=127.0.0.1 --port=8080',
        exec_mode: 'fork',
        instances: 1,
        autorestart: true,
        watch: false,
        // No max_memory_restart. The queue block uses 256M, which is fine for
        // a worker that exits between jobs — but restarting Reverb drops every
        // open socket in the room. Watch RSS instead of capping it.
        env: { APP_ENV: 'production' },
        error_file: 'storage/logs/reverb.error.log',
        out_file: 'storage/logs/reverb.out.log',
        merge_logs: true,
        time: true,
    });
}

module.exports = { apps };
