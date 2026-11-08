// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, reactive, type App } from 'vue';

// A lightweight stand-in for the Inertia reload: the new props arrive a moment
// AFTER the request that triggered it, like a real partial reload.
const state = reactive({
    cosmetics: [],
    rooms: [
        {
            id: 7,
            slug: 'anime',
            name: 'Anime',
            topic: null,
            sort: 0,
            active: true,
        },
    ],
    participants: { data: [] },
    messages: [],
    log: [],
});

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({
        render() {
            return h('a', this.$slots.default?.());
        },
    }),
    router: {
        reload: (options?: { onFinish?: () => void }) => {
            setTimeout(() => {
                state.rooms = [
                    {
                        id: 7,
                        slug: 'anime',
                        name: 'Anime talk',
                        topic: null,
                        sort: 0,
                        active: true,
                    },
                ];
                options?.onFinish?.();
            }, 20);
        },
    },
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        render() {
            return h('div', this.$slots.default?.());
        },
    }),
}));

import Chat from '@/pages/admin/Chat.vue';

const settle = (ms = 80) => new Promise((resolve) => setTimeout(resolve, ms));

describe('admin Chat rooms', () => {
    let app: App;
    let root: HTMLElement;

    beforeEach(() => {
        root = document.createElement('div');
        document.body.appendChild(root);
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response('{}', { status: 200 })),
        );
        app = createApp({ render: () => h(Chat, { ...state }) });
        app.mount(root);
    });

    afterEach(() => {
        app.unmount();
        root.remove();
        vi.unstubAllGlobals();
    });

    it('shows the saved values, not the old ones, after a successful save', async () => {
        const name = root.querySelector(
            'input[aria-label="Room name"]',
        ) as HTMLInputElement;
        name.value = 'Anime talk';
        name.dispatchEvent(new Event('input'));
        await settle(5);

        const save = [...root.querySelectorAll('button')].find(
            (b) => b.textContent?.trim() === 'Save',
        ) as HTMLButtonElement;
        save.click();
        await settle();

        const after = root.querySelector(
            'input[aria-label="Room name"]',
        ) as HTMLInputElement;
        expect(after.value).toBe('Anime talk');
    });
});
