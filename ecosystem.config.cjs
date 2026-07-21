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
 * PM2 here supervises ONLY the optional queue worker (off by default — the app
 * dispatches no background jobs yet). Flip it on when you start dispatching:
 *   QUEUE_WORKER=1 PHP_BIN=php84 pm2 start ecosystem.config.cjs --env production
 *   pm2 save
 *
 * Shared host conventions:
 *   - PHP/Composer binaries are `php84` / `composer84` (set PHP_BIN=php84).
 */
const PHP_BIN = process.env.PHP_BIN || 'php';
const QUEUE_WORKER_ENABLED = process.env.QUEUE_WORKER === '1';

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

module.exports = { apps };
