/**
 * PM2 ecosystem for production runtime processes.
 *
 * Usage:
 *   pnpm install --prod=false   # build deps
 *   pnpm build:ssr              # emits bootstrap/ssr/ssr.js
 *   composer84 install --no-dev --optimize-autoloader
 *   PHP_BIN=php84 pm2 start ecosystem.config.cjs --env production
 *   pm2 save
 *   pm2 startup       # one-time, to register systemd unit
 *
 * Reload after deploy:
 *   PHP_BIN=php84 pm2 reload ecosystem.config.cjs --update-env
 *
 * Logs:
 *   pm2 logs ahd-ssr
 *   pm2 logs ahd-queue
 *
 * The shared host uses non-default PHP/Composer binaries. Override the PHP
 * interpreter for the queue worker via the PHP_BIN env var (defaults to `php`
 * for local dev). Composer is only used at deploy time, not by PM2.
 */
const PHP_BIN = process.env.PHP_BIN || 'php';

module.exports = {
    apps: [
        {
            // Inertia SSR — serves the pre-rendered HTML on port 13714 (Inertia
            // default). Laravel's `Inertia::render()` calls back to this node
            // process when ssr.url config points here.
            name: 'ahd-ssr',
            script: 'bootstrap/ssr/ssr.js',
            interpreter: 'node',
            exec_mode: 'cluster',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '512M',
            env: {
                NODE_ENV: 'production',
                APP_ENV: 'production',
            },
            error_file: 'storage/logs/ssr.error.log',
            out_file: 'storage/logs/ssr.out.log',
            merge_logs: true,
            time: true,
        },
        {
            // Queue worker. Runs `artisan queue:work` with sane defaults.
            // Restart every 1000 jobs or 60min to recycle memory leaks.
            name: 'ahd-queue',
            script: 'artisan',
            interpreter: PHP_BIN,
            args: 'queue:work --tries=3 --max-time=3600 --max-jobs=1000 --sleep=3',
            exec_mode: 'fork',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '256M',
            env: {
                APP_ENV: 'production',
            },
            error_file: 'storage/logs/queue.error.log',
            out_file: 'storage/logs/queue.out.log',
            merge_logs: true,
            time: true,
        },
    ],
};
