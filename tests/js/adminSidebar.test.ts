// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, reactive, type App } from 'vue';

const page = reactive({
    url: '/dashboard',
    props: { openReports: 0 } as Record<string, unknown>,
});

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        render() {
            return h('a', this.$slots.default?.());
        },
    }),
    usePage: () => page,
}));
vi.mock('@/components/NavUser.vue', () => ({
    default: defineComponent({ render: () => h('div') }),
}));
vi.mock('@/components/AppLogo.vue', () => ({
    default: defineComponent({ render: () => h('span') }),
}));
vi.mock('@/routes', () => ({ dashboard: () => '/dashboard' }));

import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';

describe('AppSidebar', () => {
    let app: App;
    let root: HTMLElement;

    const mount = () => {
        root = document.createElement('div');
        document.body.appendChild(root);
        app = createApp({
            render: () => h(SidebarProvider, null, () => h(AppSidebar)),
        });
        app.mount(root);
    };

    beforeEach(() => {
        vi.stubGlobal(
            'matchMedia',
            () => ({ matches: false, addEventListener() {}, removeEventListener() {} }),
        );
        page.props = { openReports: 0 };
    });

    afterEach(() => {
        app.unmount();
        root.remove();
        vi.unstubAllGlobals();
    });

    it('groups the nav and drops the starter-kit links', () => {
        mount();
        const text = root.textContent ?? '';

        for (const label of ['Overview', 'Community', 'System', 'Dashboard', 'Members', 'Comments', 'Chat', 'Reports', 'Site settings']) {
            expect(text).toContain(label);
        }
        expect(text).not.toContain('Github Repo');
        expect(text).not.toContain('Documentation');
    });

    it('shows the open report count as a badge', () => {
        page.props = { openReports: 4 };
        mount();

        expect(root.querySelector('[data-nav-badge]')?.textContent).toBe('4');
    });

    it('shows no badge when nothing is open', () => {
        mount();

        expect(root.querySelector('[data-nav-badge]')).toBeNull();
    });
});
