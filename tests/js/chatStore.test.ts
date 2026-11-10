// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import chatStore, { applyBuy, applyEquip } from '../../resources/js/chat/store';

const item = (over: Record<string, unknown>) => ({
    id: 1,
    type: 'name_color',
    name: 'Sunset',
    price: 100,
    slot: 'name',
    style: {},
    owned: false,
    equipped: false,
    ...over,
});

function makeStore(items: unknown[], balance: number) {
    const store = chatStore() as any;
    store.items = items;
    store.balance = balance;

    return store;
}

function reply(status: number, body: unknown) {
    return Promise.resolve({
        ok: status >= 200 && status < 300,
        status,
        json: () => Promise.resolve(body),
    });
}

describe('chat store helpers', () => {
    it('test_applyEquip_clears_only_the_same_slot', () => {
        const items = [
            item({ id: 1, slot: 'name', owned: true, equipped: true }),
            item({ id: 2, type: 'name_gradient', slot: 'name', owned: true }),
            item({ id: 3, type: 'glow', slot: 'effect', owned: true, equipped: true }),
        ];
        const next = applyEquip(items, 2);

        expect(next.map((i: any) => i.equipped)).toEqual([false, true, true]);
        expect(items[0].equipped).toBe(true);
    });

    it('test_applyEquip_ignores_slotless_items', () => {
        const items = [
            item({ id: 1, slot: null, owned: true, equipped: false }),
            item({ id: 2, slot: null, owned: true, equipped: true }),
            item({ id: 3, slot: 'name', owned: true, equipped: true }),
        ];
        const next = applyEquip(items, 1);

        expect(next[1].equipped).toBe(true);
        expect(next[2].equipped).toBe(true);
    });

    it('test_applyBuy_marks_owned', () => {
        const items = [item({ id: 1 }), item({ id: 2 })];
        const next = applyBuy(items, 2);

        expect(next.map((i: any) => i.owned)).toEqual([false, true]);
        expect(items[1].owned).toBe(false);
    });
});

describe('chat store requests', () => {
    beforeEach(() => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 't';
        document.head.appendChild(meta);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        document.head.innerHTML = '';
    });

    it('test_buy_updates_balance_and_owned_from_the_server_reply', async () => {
        const fetchMock = vi.fn(() => reply(200, { ok: true, balance: 30 }));
        vi.stubGlobal('fetch', fetchMock);
        const store = makeStore([item({ id: 1 })], 130);

        await store.buy(store.items[0]);

        expect(fetchMock).toHaveBeenCalledWith('/chat/store/1/buy', expect.objectContaining({ method: 'POST' }));
        expect(store.balance).toBe(30);
        expect(store.items[0].owned).toBe(true);
        expect(store.error).toBe('');
    });

    it('test_buy_refusal_shows_the_server_message_and_changes_nothing', async () => {
        vi.stubGlobal('fetch', vi.fn(() => reply(422, { message: 'You do not have enough coins.' })));
        const store = makeStore([item({ id: 1 })], 130);

        await store.buy(store.items[0]);

        expect(store.error).toBe('เหรียญไม่พอ');
        expect(store.balance).toBe(130);
        expect(store.items[0].owned).toBe(false);
        expect(store.busy).toBeNull();
    });

    it('survives a JSON null error body', async () => {
        vi.stubGlobal('fetch', vi.fn(() => reply(500, null)));
        const store = makeStore([item({ id: 1 })], 130);

        await store.buy(store.items[0]);

        expect(store.error).toBe('ทำรายการไม่สำเร็จ');
    });

    it('test_double_click_buy_sends_one_request', async () => {
        let resolve: (v: unknown) => void = () => {};
        const fetchMock = vi.fn(() => new Promise((r) => { resolve = r; }));
        vi.stubGlobal('fetch', fetchMock);
        const store = makeStore([item({ id: 1 })], 500);

        const first = store.buy(store.items[0]);
        const second = store.buy(store.items[0]);
        resolve(await reply(200, { ok: true, balance: 400 }));
        await Promise.all([first, second]);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(store.balance).toBe(400);
    });

    it('test_equip_403_shows_message', async () => {
        vi.stubGlobal('fetch', vi.fn(() => reply(403, { message: 'You do not own that item.' })));
        const store = makeStore([item({ id: 1, owned: true }), item({ id: 2, owned: true, equipped: true })], 0);

        await store.equip(store.items[0]);

        expect(store.error).toBe('คุณยังไม่มีไอเทมนี้');
        expect(store.items[0].equipped).toBe(false);
        expect(store.items[1].equipped).toBe(true);
    });

    it('equip 200 applies the slot swap', async () => {
        vi.stubGlobal('fetch', vi.fn(() => reply(200, { ok: true })));
        const store = makeStore([item({ id: 1, owned: true }), item({ id: 2, owned: true, equipped: true })], 0);

        await store.equip(store.items[0]);

        expect(store.items.map((i: any) => i.equipped)).toEqual([true, false]);
    });
});

describe('chat store backgrounds', () => {
    it('labels a background and exposes its safe preview style', () => {
        const store = makeStore([], 0);
        const bg = item({
            type: 'background',
            slot: 'background',
            style: { '--chat-background': '#112233', 'background-image': 'url(x)' },
        });

        expect(store.typeLabel(bg)).toBe('พื้นหลังข้อความ');
        expect(store.previewStyle(bg)).toEqual({ '--chat-background': '#112233' });
        expect(store.isBackground(bg)).toBe(true);
        expect(store.isBackground(item({}))).toBe(false);
    });
});
