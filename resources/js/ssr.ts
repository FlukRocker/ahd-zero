import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { createHead, transformHtmlTemplate } from '@unhead/vue/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const appName = import.meta.env.VITE_APP_NAME || 'Anime HD Zero';

// Port chosen via INERTIA_SSR_PORT env so the server can avoid colliding with
// kurokami's SSR (default 13714). Falls back to Inertia's default for dev.
const ssrPort = Number(process.env.INERTIA_SSR_PORT) || 13714;

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: async (app) => {
                const body = await renderToString(app);
                // @ts-expect-error — head is attached in setup below
                const head = app.config.globalProperties.$head;
                if (head) {
                    return await transformHtmlTemplate(head, body);
                }
                return body;
            },
            title: (title) => (title ? `${title} — ${appName}` : appName),
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup: ({ App, props, plugin }) => {
                const app = createSSRApp({ render: () => h(App, props) }).use(
                    plugin,
                );
                const head = createHead();
                app.use(head);
                app.config.globalProperties.$head = head;
                return app;
            },
        }),
    // Single-process SSR. PM2 supervises the master (fork mode); avoid
    // Node cluster on top, which leaks memory under sustained traffic and
    // was triggering the 5-9min restart loop. Scale by adding more PM2
    // instances on different ports if the single worker saturates a CPU.
    { cluster: false, port: ssrPort },
);
