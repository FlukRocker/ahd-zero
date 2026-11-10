import { safeStyle } from './lobby';

const TYPE_LABELS = {
    name_color: 'Name colour',
    name_gradient: 'Name gradient',
    glow: 'Name glow',
    smiley: 'Smiley',
    background: 'Message background',
};

/** New array with item `id` marked owned. Input is not mutated. */
export function applyBuy(items, id) {
    return items.map((item) => (item.id === id ? { ...item, owned: true } : item));
}

/** New array with `id` equipped and the same non-null slot cleared elsewhere. */
export function applyEquip(items, id) {
    const target = items.find((item) => item.id === id);
    const slot = target?.slot ?? null;

    return items.map((item) => {
        if (item.id === id) {
            return { ...item, equipped: true };
        }

        if (slot !== null && item.slot === slot && item.equipped) {
            return { ...item, equipped: false };
        }

        return item;
    });
}

export default function chatStore() {
    return {
        items: [],
        balance: 0,
        busy: null,
        error: '',

        init() {
            try {
                const node = document.getElementById('store-bootstrap');
                const data = node ? JSON.parse(node.textContent) : {};
                this.items = Array.isArray(data.items) ? data.items : [];
                this.balance = Number(data.balance) || 0;
            } catch {
                this.items = [];
            }
        },

        typeLabel(item) {
            return TYPE_LABELS[item.type] ?? 'Cosmetic';
        },

        previewStyle(item) {
            return safeStyle(item.style);
        },

        isBackground(item) {
            return item.type === 'background';
        },

        iconOf(item) {
            return safeStyle(item.style)['--chat-icon'] ?? '';
        },

        animOf(item) {
            return safeStyle(item.style)['--chat-anim'] ?? '';
        },

        hasGradient(item) {
            return '--chat-name-gradient' in safeStyle(item.style);
        },

        canBuy(item) {
            return !item.owned && !item.show_only && !item.sold_out && this.busy === null && this.balance >= item.price;
        },

        async request(url, id) {
            if (this.busy !== null) {
                return null;
            }

            this.busy = id;
            this.error = '';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });

                if (!response.ok) {
                    const payload = (await response.json().catch(() => ({}))) ?? {};
                    this.error = typeof payload.message === 'string' ? payload.message : 'That did not work.';

                    return null;
                }

                return (await response.json().catch(() => ({}))) ?? {};
            } catch {
                this.error = 'Could not reach the server.';

                return null;
            } finally {
                this.busy = null;
            }
        },

        async buy(item) {
            if (!this.canBuy(item)) {
                return;
            }

            const reply = await this.request(`/chat/store/${encodeURIComponent(item.id)}/buy`, item.id);

            if (reply === null) {
                return;
            }

            if (Number.isFinite(reply.balance)) {
                this.balance = reply.balance;
            }

            this.items = applyBuy(this.items, item.id);
        },

        async equip(item) {
            if (!item.owned || item.slot === null || item.equipped) {
                return;
            }

            const reply = await this.request(`/chat/cosmetics/${encodeURIComponent(item.id)}/equip`, item.id);

            if (reply === null) {
                return;
            }

            this.items = applyEquip(this.items, item.id);
        },
    };
}
