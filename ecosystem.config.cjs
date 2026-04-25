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
 *
 * Queue worker:
 *   The current app dispatches no background jobs (no ShouldQueue / dispatch()
 *   anywhere). `ahd-queue` is OFF by default to save a PHP process. Flip it on
 *   only after you start dispatching:
 *     QUEUE_WORKER=1 pm2 reload ecosystem.config.cjs --update-env
 *   Redis is just the broker — it stores queued jobs but does not run them.
 *   You always need a `queue:work` process to consume the queue.
 */
const PHP_BIN = process.env.PHP_BIN || 'php';
const INERTIA_SSR_PORT = process.env.INERTIA_SSR_PORT || '13715';
const QUEUE_WORKER_ENABLED = process.env.QUEUE_WORKER === '1';
const SSR_MEMORY_LIMIT = process.env.SSR_MEMORY_LIMIT || '1024M';

const apps = [
    {
        // Inertia SSR — serves pre-rendered HTML on INERTIA_SSR_PORT.
        // Laravel's Inertia::render() forwards to this node process when
        // config('inertia.ssr.url') points here.
        //
        // Run in `fork` mode (NOT `cluster`). The Inertia createServer call
        // already spawns its own Node `cluster` worker pool when given
        // { cluster: true } (see resources/js/ssr.ts). Wrapping that in PM2
        // cluster mode produces double-forking, port-bind races, and the
        // memory churn that was triggering a restart every 5-9 minutes.
        //
        // Memory limit raised to 1G — Vue SSR on a 1M-row anime DB spikes
        // hard per render and the previous 512M cap was being hit cleanly.
        // Override via SSR_MEMORY_LIMIT env if needed.
        name: 'ahd-ssr',
        script: 'bootstrap/ssr/ssr.js',
        interpreter: 'node',
        exec_mode: 'fork',
        instances: 1,
        autorestart: true,
        watch: false,
        max_memory_restart: SSR_MEMORY_LIMIT,
        kill_timeout: 5000,
        listen_timeout: 10000,
        env: {
            NODE_ENV: 'production',
            APP_ENV: 'production',
            INERTIA_SSR_PORT,
            // Cap V8 old-space at 80% of the PM2 cap so V8 GCs hard before
            // PM2 SIGTERMs the process. Cleaner shutdown, no in-flight
            // render losses.
            NODE_OPTIONS: '--max-old-space-size=820',
        },
        error_file: 'storage/logs/ssr.error.log',
        out_file: 'storage/logs/ssr.out.log',
        merge_logs: true,
        time: true,
    },
];

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
