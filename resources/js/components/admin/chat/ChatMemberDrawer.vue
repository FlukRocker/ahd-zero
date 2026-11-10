<script setup lang="ts">
import { adminCall, ui } from '@/lib/adminFetch';
import { ref, watch } from 'vue';

interface Detail {
    id: number;
    name: string;
    email: string;
    rank: string;
    balance: number;
    banned: boolean;
    ban_reason: string | null;
    gagged: boolean;
    gagged_until: string | null;
    cosmetics: { id: number; name: string; type: string; equipped: boolean }[];
    messages: { id: number; body: string; created_at: string }[];
    transactions: { delta: number; balance_after: number; note: string | null; created_at: string }[];
}

const props = defineProps<{ memberId: number | null }>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'changed'): void }>();

const detail = ref<Detail | null>(null);
const error = ref('');
const note = ref('');
const takeAmount = ref(1);
const takeNote = ref('');
const gagMinutes = ref(10);
const busy = ref(false);

async function load() {
    detail.value = null;
    error.value = '';

    if (props.memberId === null) return;

    const r = await adminCall<Detail>('GET', `/dashboard/chat/tools/members/${props.memberId}`);

    if (r.ok && r.data) {
        detail.value = r.data;
    } else {
        error.value = r.error;
    }
}

async function act(method: 'POST', path: string, body?: object, confirmMsg?: string, okMsg = 'Done.') {
    if (confirmMsg && !window.confirm(confirmMsg)) return;

    busy.value = true;
    error.value = '';
    note.value = '';
    const r = await adminCall(method, path, body);
    busy.value = false;

    if (r.ok) {
        note.value = okMsg;
        emit('changed');
        await load();
    } else {
        error.value = r.error;
    }
}

const base = () => `/dashboard/chat/tools/members/${props.memberId}`;

watch(() => props.memberId, load, { immediate: true });
</script>

<template>
    <div v-if="memberId !== null" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="emit('close')">
        <aside class="h-full w-full max-w-md overflow-y-auto border-l bg-background p-5 shadow-xl" role="dialog" aria-label="Member details">
            <div class="flex items-start justify-between">
                <h2 class="text-lg font-semibold">{{ detail?.name ?? 'Member' }}</h2>
                <button type="button" :class="ui.btn" @click="emit('close')">Close</button>
            </div>
            <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
            <p v-if="note" class="mt-2 text-sm text-emerald-600 dark:text-emerald-400">{{ note }}</p>

            <template v-if="detail">
                <div class="mt-1 text-xs text-muted-foreground">{{ detail.email }} · rank {{ detail.rank }}</div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="rounded bg-muted px-2 py-1">{{ detail.balance }} coins</span>
                    <span v-if="detail.banned" class="rounded bg-destructive/15 px-2 py-1 text-destructive">Banned{{ detail.ban_reason ? ': ' + detail.ban_reason : '' }}</span>
                    <span v-if="detail.gagged" class="rounded bg-amber-500/20 px-2 py-1 text-amber-600">Gagged</span>
                </div>

                <h3 class="mt-5 text-sm font-semibold">Sanctions</h3>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <input v-model.number="gagMinutes" type="number" min="1" class="h-8 w-20 rounded-md border border-input bg-background px-2 text-sm" />
                    <button type="button" :class="ui.btn" :disabled="busy" @click="act('POST', `${base()}/gag`, { minutes: gagMinutes }, undefined, 'Gagged.')">Gag (min)</button>
                    <button type="button" :class="ui.btn" :disabled="busy || !detail.gagged" @click="act('POST', `${base()}/gag`, { ungag: true }, undefined, 'Ungagged.')">Ungag</button>
                    <button type="button" :class="ui.btn" :disabled="busy" @click="act('POST', `${base()}/kick`, undefined, undefined, 'Kicked for a minute.')">Kick</button>
                    <button v-if="!detail.banned" type="button" :class="ui.btnDanger" :disabled="busy" @click="act('POST', `/dashboard/chat/members/${detail.id}/ban`, {}, `Ban ${detail.name}?`, 'Banned.')">Ban</button>
                    <button v-else type="button" :class="ui.btn" :disabled="busy" @click="act('POST', `/dashboard/chat/members/${detail.id}/ban`, { unban: true }, undefined, 'Unbanned.')">Unban</button>
                </div>

                <h3 class="mt-5 text-sm font-semibold">Take coins back</h3>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <input v-model.number="takeAmount" type="number" min="1" class="h-8 w-24 rounded-md border border-input bg-background px-2 text-sm" />
                    <input v-model="takeNote" type="text" maxlength="200" placeholder="Reason (optional)" class="h-8 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-sm" />
                    <button type="button" :class="ui.btn" :disabled="busy" @click="act('POST', `${base()}/take-coins`, { amount: takeAmount, note: takeNote || null }, `Take ${takeAmount} coins from ${detail.name}?`, 'Coins taken back.')">Take</button>
                </div>

                <h3 class="mt-5 text-sm font-semibold">Cosmetics owned</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-if="detail.cosmetics.length === 0" class="text-muted-foreground">None.</li>
                    <li v-for="c in detail.cosmetics" :key="c.id" class="flex items-center gap-2">
                        <span>{{ c.name }}</span>
                        <span class="text-xs text-muted-foreground">{{ c.type }}{{ c.equipped ? ' · equipped' : '' }}</span>
                        <button type="button" :class="[ui.btn, 'ml-auto']" :disabled="busy" @click="act('POST', `${base()}/equip-cosmetic`, { cosmetic_id: c.id, equipped: !c.equipped }, undefined, c.equipped ? 'Unequipped.' : 'Equipped.')">{{ c.equipped ? 'Unequip' : 'Equip' }}</button>
                        <button type="button" :class="ui.btnDanger" :disabled="busy" @click="act('POST', `${base()}/revoke-cosmetic`, { cosmetic_id: c.id }, `Remove ${c.name} from ${detail.name}?`, 'Cosmetic removed.')">Revoke</button>
                    </li>
                </ul>

                <h3 class="mt-5 text-sm font-semibold">Recent messages</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-if="detail.messages.length === 0" class="text-muted-foreground">None.</li>
                    <li v-for="m in detail.messages" :key="m.id" class="break-words"><span class="text-xs text-muted-foreground">{{ new Date(m.created_at).toLocaleString() }}</span> {{ m.body }}</li>
                </ul>
                <button type="button" :class="[ui.btnDanger, 'mt-2']" :disabled="busy" @click="act('POST', `${base()}/purge`, undefined, `Delete EVERY message written by ${detail.name}?`, 'Messages deleted.')">Delete all their messages</button>

                <h3 class="mt-5 text-sm font-semibold">Coin history</h3>
                <ul class="mt-2 space-y-1 text-xs">
                    <li v-if="detail.transactions.length === 0" class="text-muted-foreground">None.</li>
                    <li v-for="(t, i) in detail.transactions" :key="i">
                        <span :class="t.delta < 0 ? 'text-destructive' : 'text-emerald-600'">{{ t.delta > 0 ? '+' : '' }}{{ t.delta }}</span>
                        → {{ t.balance_after }} <span class="text-muted-foreground">{{ t.note ?? '' }}</span>
                    </li>
                </ul>
            </template>
        </aside>
    </div>
</template>
