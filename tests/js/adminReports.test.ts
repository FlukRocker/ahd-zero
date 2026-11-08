// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, type App } from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({
        render() {
            return h('a', this.$slots.default?.());
        },
    }),
    router: { reload: vi.fn(), get: vi.fn() },
}));
vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        render() {
            return h('div', this.$slots.default?.());
        },
    }),
}));

import Reports from '@/pages/admin/Reports.vue';

type Group = {
    type: 'chat_message' | 'comment';
    id: string;
    excerpt: string;
    author: { name: string | null; memberId: number | null };
    count: number;
    reasons: string[];
    notes: string[];
    status: string;
    available: boolean;
};

const group = (over: Partial<Group>): Group => ({
    type: 'chat_message',
    id: '5',
    excerpt: 'rude words',
    author: { name: 'Rin', memberId: 3 },
    count: 2,
    reasons: ['spam', 'abuse'],
    notes: ['ads'],
    status: 'open',
    available: true,
    ...over,
});

describe('admin Reports', () => {
    let app: App;
    let root: HTMLElement;

    const mount = (groups: Group[]) => {
        root = document.createElement('div');
        document.body.appendChild(root);
        app = createApp({
            render: () => h(Reports, { groups, filters: { status: 'open' } }),
        });
        app.mount(root);
    };

    afterEach(() => {
        app.unmount();
        root.remove();
    });

    const buttons = () =>
        [...root.querySelectorAll('li button')].map((b) =>
            b.textContent?.trim(),
        );

    it('offers both bans and delete for a chat item with a known author', () => {
        mount([group({})]);

        expect(root.textContent).toContain('rude words');
        expect(buttons()).toEqual(
            expect.arrayContaining([
                'Delete',
                'Ban from chat',
                'Ban from site',
                'Dismiss',
            ]),
        );
        expect(buttons()).not.toContain('Delete and ban');
    });

    it('offers delete and ban for a comment item', () => {
        mount([group({ type: 'comment', id: 'abc' })]);

        expect(buttons()).toEqual(
            expect.arrayContaining(['Delete', 'Delete and ban', 'Dismiss']),
        );
        expect(buttons()).not.toContain('Ban from chat');
    });

    it('hides bans when the author is unknown', () => {
        mount([group({ author: { name: null, memberId: null } })]);

        expect(buttons()).toEqual(
            expect.arrayContaining(['Delete', 'Dismiss']),
        );
        expect(buttons().some((b) => b?.includes('Ban'))).toBe(false);
    });

    it('shows deleted and only Dismiss for an unavailable target', () => {
        mount([group({ available: false })]);

        expect(root.textContent?.toLowerCase()).toContain('deleted');
        expect(buttons()).toEqual(['Dismiss']);
    });

    it('shows an empty state without groups', () => {
        mount([]);

        expect(root.textContent).toContain('No reports');
    });
});
