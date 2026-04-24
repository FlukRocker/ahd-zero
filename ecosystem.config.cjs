/**
 * PM2 ecosystem for production runtime processes.
 *
 * Usage:
 *   pnpm install --prod=false   # build deps
 *   PHP_BIN=php84 pnpm build:ssr
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
 * Shared host conventions:
 *   - PHP/Composer binaries are `php84` / `composer84` (set PHP_BIN=php84).
 *   - The Inertia SSR daemon listens on INERTIA_SSR_PORT, default 13715 here
 *     so it doesn't collide with kurokami's SSR (which uses 13714).
 *     Make sure config/inertia.php / .env's INERTIA_SSR_URL matches.
 */
const PHP_BIN = process.env.PHP_BIN || 'php';
const INERTIA_SSR_PORT = process.env.INERTIA_SSR_PORT || '13715';

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
                INERTIA_SSR_PORT,
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
