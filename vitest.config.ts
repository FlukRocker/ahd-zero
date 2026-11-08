import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    // The Vue plugin is only here so a page component can be mounted in a
    // test; the node-environment tests never touch it.
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['tests/js/**/*.{test,spec}.ts'],
    },
});
