import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createHead, transformHtmlTemplate } from '@unhead/vue/server';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const appName = import.meta.env.VITE_APP_NAME || 'Anime HD Zero';

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
                const app = createSSRApp({ render: () => h(App, props) }).use(plugin);
                const head = createHead();
                app.use(head);
                app.config.globalProperties.$head = head;
                return app;
            },
        }),
    { cluster: true },
);
