import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

// The shared production host uses non-default PHP/Composer binaries
// (`php84`, `composer84`). Set `PHP_BIN=php84` in the deploy environment so
// build-time artisan invocations (Wayfinder type generation) work.
const PHP_BIN = process.env.PHP_BIN || 'php';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            command: `${PHP_BIN} artisan wayfinder:generate`,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
