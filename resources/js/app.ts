import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { createHead } from '@unhead/vue/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';

import { initializeTheme } from './composables/useAppearance';

// Inertia 2 rejects pending visit promises with a "cancelled" sentinel when a
// new visit is triggered before the previous one resolves. These bubble as
// unhandled rejections and clutter the console without indicating an error.
if (typeof window !== 'undefined') {
    window.addEventListener('unhandledrejection', (event) => {
        const reason = event.reason as
            | { cancelled?: boolean; message?: string }
            | undefined;
        if (
            reason &&
            (reason.cancelled === true || reason.message === 'cancelled')
        ) {
            event.preventDefault();
        }
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Anime HD Zero';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const head = createHead();

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(head)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: 'hsl(350 80% 68%)',
    },
});

initializeTheme();

// GA4 SPA tracking — gtag in blade only fires on initial load with
// send_page_view:false, so each Inertia visit needs an explicit page_view.
// Title is read after Inertia swaps the page so Head updates land first.
if (typeof window !== 'undefined') {
    router.on('navigate', () => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const gtag = (window as any).gtag;
        if (typeof gtag !== 'function') return;
        queueMicrotask(() => {
            gtag('event', 'page_view', {
                page_location: location.href,
                page_path: location.pathname + location.search,
                page_title: document.title,
            });
        });
    });
}
