import { safeStyle } from './lobby';

const TYPE_LABELS = {
    name_color: 'สีชื่อ',
    name_gradient: 'ชื่อไล่สี',
    glow: 'ชื่อเรืองแสง',
    smiley: 'สไมลี่',
    pawn: 'ตัวละคร',
    background: 'พื้นหลังข้อความ',
};

// The server answers in English; show Thai for the messages we know.
const MESSAGES_TH = {
    'That item is not available.': 'ไอเทมนี้ไม่พร้อมจำหน่าย',
    'That item is not for sale.': 'ไอเทมนี้ไม่ได้วางขาย',
    'Sold out.': 'สินค้าหมดแล้ว',
    'You already own that item.': 'คุณมีไอเทมนี้แล้ว',
    'You do not have enough coins.': 'เหรียญไม่พอ',
    'You do not own that item.': 'คุณยังไม่มีไอเทมนี้',
    'That item is always available and is not equipped.': 'ไอเทมนี้ใช้ได้เสมอ ไม่ต้องสวมใส่',
};

export function thaiMessage(message) {
    return MESSAGES_TH[message] ?? message;
}

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
        // Result popup for buy / equip: { ok, title, message, item }
        modal: null,

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
            return TYPE_LABELS[item.type] ?? 'ไอเทมตกแต่ง';
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
                    this.error = typeof payload.message === 'string' ? thaiMessage(payload.message) : 'ทำรายการไม่สำเร็จ';

                    return null;
                }

                return (await response.json().catch(() => ({}))) ?? {};
            } catch {
                this.error = 'ติดต่อเซิร์ฟเวอร์ไม่ได้';

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
                this.modal = { ok: false, title: 'ซื้อไม่สำเร็จ', message: this.error, item: null };

                return;
            }

            if (Number.isFinite(reply.balance)) {
                this.balance = reply.balance;
            }

            this.items = applyBuy(this.items, item.id);
            this.modal = {
                ok: true,
                title: 'ซื้อสำเร็จ',
                message: `ซื้อ "${item.name}" เรียบร้อย เหลือ ${this.balance} เหรียญ`,
                item: item.slot === null ? null : item,
            };
        },

        closeModal() {
            this.modal = null;
        },

        /** From the success popup: wear what was just bought. */
        async equipFromModal() {
            const target = this.modal?.item;
            this.modal = null;

            if (target) {
                await this.equip(this.items.find((i) => i.id === target.id) ?? target);
            }
        },

        async equip(item) {
            if (!item.owned || item.slot === null || item.equipped) {
                return;
            }

            const reply = await this.request(`/chat/cosmetics/${encodeURIComponent(item.id)}/equip`, item.id);

            if (reply === null) {
                this.modal = { ok: false, title: 'สวมใส่ไม่สำเร็จ', message: this.error, item: null };

                return;
            }

            this.items = applyEquip(this.items, item.id);
            this.modal = { ok: true, title: 'สวมใส่แล้ว', message: `กำลังสวมใส่ "${item.name}"`, item: null };
        },
    };
}
