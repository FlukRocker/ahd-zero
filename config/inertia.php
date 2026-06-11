<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configure if and how Inertia uses Server Side Rendering
    | to pre-render every initial visit made to your application's pages
    | automatically. A separate rendering service should be available.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', true),
        // Default 13715 (NOT 13714 — that's kurokami's SSR on the shared host).
        // Must match ecosystem.config.cjs's INERTIA_SSR_PORT default, or Laravel
        // calls a dead/foreign port, Inertia silently falls back to a client-only
        // empty #app div, and slow webviews (Facebook in-app) show a blank page.
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:'.env('INERTIA_SSR_PORT', 13715)),
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [
        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],

];
