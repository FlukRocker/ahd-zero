<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {
    buildPayload,
    fieldsFor,
    type PayloadFields,
} from '@/lib/cosmeticPayload';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface CosmeticRow {
    id: number;
    name: string;
    type: string;
    price: number;
    active: boolean;
}

interface RoomRow {
    id: number;
    slug: string;
    name: string;
    topic: string | null;
    sort: number;
    active: boolean;
}

interface ParticipantRow {
    member_id: number;
    rank: string;
    balance: number;
    banned_at: string | null;
    banned_until: string | null;
    member: { name: string } | null;
}

interface MessageRow {
    id: number;
    body: string;
    created_at: string;
    member: { id: number; name: string } | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface LogRow {
    id: number;
    action: string;
    target_member_id: number;
    created_at: string;
}

const props = defineProps<{
    cosmetics: CosmeticRow[];
    rooms: RoomRow[];
    participants: { data: ParticipantRow[]; links?: PageLink[] };
    messages: MessageRow[];
    log: LogRow[];
    rules: string;
    pinnedIds: number[];
}>();

// Types the admin can author. pawn has no visible effect yet.
const TYPE_OPTIONS = [
    { value: 'name_color', label: 'Name colour' },
    { value: 'name_gradient', label: 'Name gradient' },
    { value: 'glow', label: 'Glow' },
    { value: 'smiley', label: 'Smiley' },
    { value: 'background', label: 'Message background' },
];

const activeCosmetics = computed(() => props.cosmetics.filter((c) => c.active));

const LABEL_ENTITIES: Record<string, string> = {
    '&laquo;': '\u00ab',
    '&raquo;': '\u00bb',
    '&amp;': '&',
};

// Pagination labels arrive with HTML entities; decode the known ones to text.
function linkLabel(label: string): string {
    return label.replace(/&(laquo|raquo|amp);/g, (m) =>
        Object.hasOwn(LABEL_ENTITIES, m) ? LABEL_ENTITIES[m] : m,
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Chat', href: '/dashboard/chat' },
];
const error = ref('');
const fieldErrors = ref<Record<string, string[]>>({});

const form = reactive({
    type: 'name_color',
    name: '',
    slug: '',
    price: 0,
    sort: 0,
    color: '#ff8800',
    from: '#ff8800',
    to: '#0088ff',
    angle: 90,
    strength: 2,
    code: '',
    asset: '',
    bgMode: 'color',
});
const grantChoice = reactive<Record<number, string>>({});

const formFields = computed(() =>
    fieldsFor(form.type, form.bgMode === 'gradient'),
);

function fieldError(key: string): string | undefined {
    return fieldErrors.value[key]?.[0];
}

function payloadError(): string | undefined {
    const key = Object.keys(fieldErrors.value).find((k) =>
        k.startsWith('payload'),
    );

    return key ? fieldErrors.value[key]?.[0] : undefined;
}

async function send(
    method: string,
    url: string,
    body?: object,
): Promise<{ ok: boolean; status: number }> {
    error.value = '';
    fieldErrors.value = {};
    let res: Response;

    try {
        res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });
    } catch {
        error.value = 'Network error — please retry.';

        return { ok: false, status: 0 };
    }

    if (!res.ok) {
        const data = ((await res.json().catch(() => ({}))) ?? {}) as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        error.value = data.message ?? `Request failed (${res.status})`;
        fieldErrors.value = data.errors ?? {};

        return { ok: false, status: res.status };
    }

    // Wait for the fresh props before returning: callers clear their local edit
    // state afterwards, and doing that while the old props are still on screen
    // re-seeds the inputs with the old values (and a later save writes them back).
    await new Promise<void>((resolve) => {
        router.reload({ onFinish: () => resolve() });
    });

    return { ok: true, status: res.status };
}

const busy = ref(false);

const TABS = [
    { key: 'participants', label: 'Participants' },
    { key: 'rooms', label: 'Rooms' },
    { key: 'cosmetics', label: 'Cosmetics' },
    { key: 'messages', label: 'Messages' },
    { key: 'rules', label: 'Rules' },
    { key: 'log', label: 'Moderation log' },
] as const;
const tab = ref<(typeof TABS)[number]['key']>('participants');

const field =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
const btn =
    'inline-flex h-8 items-center justify-center rounded-md border border-input bg-background px-3 text-xs font-medium hover:bg-accent disabled:opacity-50';
const btnPrimary =
    'inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50';
const btnDanger =
    'inline-flex h-8 items-center justify-center rounded-md border border-destructive/40 px-3 text-xs font-medium text-destructive hover:bg-destructive/10 disabled:opacity-50';
const card = 'rounded-xl border bg-card text-card-foreground shadow-sm';
const th =
    'px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground';
const td = 'px-4 py-3 align-middle';

// One in-flight request at a time for the create/grant/delete buttons.
async function guarded<T>(fn: () => Promise<T>): Promise<T | undefined> {
    if (busy.value) {
        return undefined;
    }

    busy.value = true;

    try {
        return await fn();
    } finally {
        busy.value = false;
    }
}

async function createCosmetic() {
    await guarded(doCreateCosmetic);
}

async function doCreateCosmetic() {
    const fields: PayloadFields = {
        color: form.color,
        from: form.from,
        to: form.to,
        angle: form.angle,
        strength: form.strength,
        code: form.code,
        asset: form.asset,
        gradient: form.bgMode === 'gradient',
    };

    const { ok } = await send('POST', '/dashboard/chat/cosmetics', {
        type: form.type,
        name: form.name,
        slug: form.slug,
        price: Number(form.price),
        sort: Number(form.sort),
        payload: buildPayload(form.type, fields),
    });

    if (ok) {
        form.name = '';
        form.slug = '';
    }
}

function grantCosmetic(memberId: number, memberName: string) {
    if (busy.value) {
        return;
    }

    const id = Number(grantChoice[memberId]);
    const item = props.cosmetics.find((c) => c.id === id);

    if (!item) {
        return;
    }

    if (window.confirm(`Grant "${item.name}" to ${memberName}?`)) {
        void guarded(async () => {
            const { ok } = await send(
                'POST',
                `/dashboard/chat/members/${memberId}/cosmetics`,
                { cosmetic_id: id },
            );

            if (ok) {
                grantChoice[memberId] = '';
            }
        });
    }
}

const rulesText = ref(props.rules);

async function saveRules() {
    await guarded(async () => {
        await send('POST', '/dashboard/chat/rules', { rules: rulesText.value });
    });
}

function isPinned(row: MessageRow): boolean {
    return props.pinnedIds.includes(row.id);
}

function togglePin(row: MessageRow) {
    void guarded(async () => {
        await send(
            isPinned(row) ? 'DELETE' : 'POST',
            `/dashboard/chat/messages/${row.id}/pin`,
        );
    });
}

function deleteMessage(row: MessageRow) {
    if (busy.value) {
        return;
    }

    if (
        window.confirm(
            `Delete this message from ${row.member?.name ?? 'unknown'}?`,
        )
    ) {
        void guarded(async () => {
            const { status } = await send(
                'DELETE',
                `/dashboard/chat/messages/${row.id}`,
            );

            if (status === 404) {
                router.reload();
                error.value = 'That message was already removed.';
            }
        });
    }
}

function grant(memberId: number) {
    const amount = Number(window.prompt('How many coins?') ?? 0);

    if (amount > 0) {
        void send('POST', `/dashboard/chat/members/${memberId}/grant`, {
            amount,
        });
    }
}

const rankForm = reactive({ email: '', rank: 'owner' });

async function grantRankByEmail() {
    const email = rankForm.email.trim();

    if (email === '' || busy.value) {
        return;
    }

    await guarded(async () => {
        const { ok } = await send('POST', '/dashboard/chat/rank', {
            email,
            rank: rankForm.rank,
        });

        if (ok) {
            rankForm.email = '';
        }
    });
}

function setRank(memberId: number, rank: string) {
    void send('POST', `/dashboard/chat/members/${memberId}/rank`, { rank });
}

function isBanned(row: ParticipantRow): boolean {
    return (
        Boolean(row.banned_at) &&
        (!row.banned_until || new Date(row.banned_until) > new Date())
    );
}

function toggleBan(row: ParticipantRow) {
    void send(
        'POST',
        `/dashboard/chat/members/${row.member_id}/ban`,
        isBanned(row) ? { unban: true } : {},
    );
}

const roomForm = reactive({ name: '', slug: '', topic: '', sort: 0 });
const roomEdits = reactive<
    Record<number, { name: string; topic: string; sort: number }>
>({});

function roomEdit(room: RoomRow) {
    roomEdits[room.id] ??= {
        name: room.name,
        topic: room.topic ?? '',
        sort: room.sort,
    };

    return roomEdits[room.id];
}

function createRoom() {
    void guarded(async () => {
        const { ok } = await send('POST', '/dashboard/chat/rooms', {
            name: roomForm.name,
            slug: roomForm.slug,
            topic: roomForm.topic === '' ? null : roomForm.topic,
            sort: Number(roomForm.sort),
        });

        if (ok) {
            roomForm.name = '';
            roomForm.slug = '';
            roomForm.topic = '';
            roomForm.sort = 0;
        }
    });
}

function saveRoom(room: RoomRow) {
    const edit = roomEdit(room);

    void guarded(async () => {
        const { ok } = await send('PATCH', `/dashboard/chat/rooms/${room.id}`, {
            name: edit.name,
            topic: edit.topic === '' ? null : edit.topic,
            sort: Number(edit.sort),
        });

        if (ok) {
            delete roomEdits[room.id];
        }
    });
}

function toggleRoom(room: RoomRow) {
    void guarded(async () => {
        await send('PATCH', `/dashboard/chat/rooms/${room.id}`, {
            active: !room.active,
        });
    });
}

function deactivate(id: number) {
    void send('DELETE', `/dashboard/chat/cosmetics/${id}`);
}
</script>

<template>
    <Head title="Chat" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="mx-auto w-full max-w-6xl space-y-5 p-4 sm:p-6">
            <div>
                <h1 class="text-xl font-semibold">Chat management</h1>
                <p class="text-sm text-muted-foreground">
                    Ranks, bans, rooms, shop items and message clean-up.
                </p>
            </div>

            <p
                v-if="error"
                role="alert"
                class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                {{ error }}
            </p>

            <nav
                class="flex gap-1 overflow-x-auto border-b"
                role="tablist"
                aria-label="Chat sections"
            >
                <button
                    v-for="t in TABS"
                    :key="t.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === t.key"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="
                        tab === t.key
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="tab = t.key"
                >
                    {{ t.label }}
                </button>
            </nav>

            <!-- Participants -->
            <div v-show="tab === 'participants'" :class="card">
                <form
                    class="flex flex-wrap items-end gap-2 border-b p-4"
                    @submit.prevent="grantRankByEmail"
                >
                    <label class="min-w-56 flex-1 text-xs font-medium">
                        Grant chat rank to member (email)
                        <input
                            v-model="rankForm.email"
                            type="email"
                            maxlength="255"
                            placeholder="member@example.com"
                            :class="[field, 'mt-1']"
                        />
                    </label>
                    <label class="text-xs font-medium">
                        Rank
                        <select v-model="rankForm.rank" :class="[field, 'mt-1 w-40']">
                            <option value="member">member</option>
                            <option value="mod">mod</option>
                            <option value="owner">admin (owner)</option>
                            <option value="main_owner">main owner</option>
                        </select>
                    </label>
                    <button type="submit" :class="btnPrimary" :disabled="busy">
                        Grant
                    </button>
                </form>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th :class="th">Member</th>
                                <th :class="th">Rank</th>
                                <th :class="th">Coins</th>
                                <th :class="th">Status</th>
                                <th :class="th">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="participants.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No chat participants yet. Members appear
                                    here after they first open the chat.
                                </td>
                            </tr>
                            <tr
                                v-for="row in participants.data"
                                :key="row.member_id"
                            >
                                <td :class="[td, 'font-medium']">
                                    {{
                                        row.member?.name ?? `#${row.member_id}`
                                    }}
                                </td>
                                <td :class="td">
                                    <select
                                        :value="row.rank"
                                        :class="[field, 'w-36']"
                                        aria-label="Rank"
                                        @change="
                                            setRank(
                                                row.member_id,
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option value="member">member</option>
                                        <option value="mod">mod</option>
                                        <option value="owner">
                                            admin (owner)
                                        </option>
                                        <option value="main_owner">
                                            main owner
                                        </option>
                                    </select>
                                </td>
                                <td :class="[td, 'tabular-nums']">
                                    {{ row.balance }}
                                </td>
                                <td :class="td">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            isBanned(row)
                                                ? 'bg-destructive/15 text-destructive'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{
                                            isBanned(row) ? 'Banned' : 'Active'
                                        }}
                                    </span>
                                </td>
                                <td :class="td">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <button
                                            type="button"
                                            :class="btn"
                                            @click="grant(row.member_id)"
                                        >
                                            Grant coins
                                        </button>
                                        <button
                                            type="button"
                                            :class="
                                                isBanned(row) ? btn : btnDanger
                                            "
                                            @click="toggleBan(row)"
                                        >
                                            {{
                                                isBanned(row) ? 'Unban' : 'Ban'
                                            }}
                                        </button>
                                        <select
                                            v-model="grantChoice[row.member_id]"
                                            :class="[field, 'h-8 w-40 text-xs']"
                                            aria-label="Cosmetic to grant"
                                        >
                                            <option value="" disabled>
                                                Cosmetic…
                                            </option>
                                            <option
                                                v-for="c in activeCosmetics"
                                                :key="c.id"
                                                :value="String(c.id)"
                                            >
                                                {{ c.name }}
                                            </option>
                                        </select>
                                        <button
                                            type="button"
                                            :class="btn"
                                            :disabled="
                                                busy ||
                                                !grantChoice[row.member_id]
                                            "
                                            @click="
                                                grantCosmetic(
                                                    row.member_id,
                                                    row.member?.name ??
                                                        `#${row.member_id}`,
                                                )
                                            "
                                        >
                                            Grant cosmetic
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav
                    v-if="participants.links && participants.links.length > 3"
                    class="flex flex-wrap gap-1 border-t p-3 text-sm"
                >
                    <template
                        v-for="(l, i) in participants.links"
                        :key="`${i}-${l.label}`"
                    >
                        <Link
                            v-if="l.url"
                            :href="l.url"
                            preserve-scroll
                            class="rounded-md border px-2.5 py-1 hover:bg-accent"
                            :class="l.active ? 'bg-accent font-semibold' : ''"
                        >
                            {{ linkLabel(l.label) }}
                        </Link>
                        <span
                            v-else
                            class="rounded-md border px-2.5 py-1 opacity-40"
                            >{{ linkLabel(l.label) }}</span
                        >
                    </template>
                </nav>
            </div>

            <!-- Rooms -->
            <div v-show="tab === 'rooms'" class="space-y-5">
                <div :class="card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr>
                                    <th :class="th">Slug</th>
                                    <th :class="th">Name</th>
                                    <th :class="th">Topic</th>
                                    <th :class="th">Sort</th>
                                    <th :class="th">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="room in rooms" :key="room.id">
                                    <td :class="td">
                                        <code
                                            class="rounded bg-muted px-1.5 py-0.5 text-xs"
                                            >{{ room.slug }}</code
                                        >
                                        <span
                                            v-if="!room.active"
                                            class="ml-2 text-xs text-muted-foreground"
                                            >inactive</span
                                        >
                                    </td>
                                    <td :class="td">
                                        <input
                                            v-model="roomEdit(room).name"
                                            :class="[field, 'min-w-32']"
                                            maxlength="60"
                                            aria-label="Room name"
                                        />
                                    </td>
                                    <td :class="td">
                                        <input
                                            v-model="roomEdit(room).topic"
                                            :class="[field, 'min-w-48']"
                                            maxlength="160"
                                            placeholder="Topic"
                                            aria-label="Room topic"
                                        />
                                    </td>
                                    <td :class="td">
                                        <input
                                            v-model="roomEdit(room).sort"
                                            type="number"
                                            :class="[field, 'w-20']"
                                            aria-label="Room sort order"
                                        />
                                    </td>
                                    <td :class="td">
                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                :class="btn"
                                                :disabled="busy"
                                                @click="saveRoom(room)"
                                            >
                                                Save
                                            </button>
                                            <button
                                                v-if="room.slug !== 'lobby'"
                                                type="button"
                                                :class="btn"
                                                :disabled="busy"
                                                @click="toggleRoom(room)"
                                            >
                                                {{
                                                    room.active
                                                        ? 'Deactivate'
                                                        : 'Activate'
                                                }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div :class="[card, 'p-5']">
                    <h2 class="font-semibold">New room</h2>
                    <form
                        class="mt-3 grid gap-3 sm:grid-cols-2"
                        @submit.prevent="createRoom"
                    >
                        <label class="grid gap-1 text-sm font-medium">
                            Name
                            <input
                                v-model="roomForm.name"
                                :class="field"
                                maxlength="60"
                                required
                            />
                            <span
                                v-if="fieldError('name')"
                                class="text-xs font-normal text-destructive"
                                >{{ fieldError('name') }}</span
                            >
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Slug (fixed once created)
                            <input
                                v-model="roomForm.slug"
                                :class="field"
                                maxlength="40"
                                pattern="[a-z0-9\-]{2,40}"
                                required
                            />
                            <span
                                v-if="fieldError('slug')"
                                class="text-xs font-normal text-destructive"
                                >{{ fieldError('slug') }}</span
                            >
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Topic
                            <input
                                v-model="roomForm.topic"
                                :class="field"
                                maxlength="160"
                            />
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Sort
                            <input
                                v-model="roomForm.sort"
                                type="number"
                                :class="field"
                            />
                        </label>
                        <div class="sm:col-span-2">
                            <button
                                type="submit"
                                :class="btnPrimary"
                                :disabled="busy"
                            >
                                Create room
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cosmetics -->
            <div v-show="tab === 'cosmetics'" class="space-y-5">
                <div :class="card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr>
                                    <th :class="th">Name</th>
                                    <th :class="th">Type</th>
                                    <th :class="th">Price</th>
                                    <th :class="th">Status</th>
                                    <th :class="th"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-if="cosmetics.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No cosmetics yet. Create the first one
                                        below.
                                    </td>
                                </tr>
                                <tr v-for="item in cosmetics" :key="item.id">
                                    <td :class="[td, 'font-medium']">
                                        {{ item.name }}
                                    </td>
                                    <td :class="td">{{ item.type }}</td>
                                    <td :class="[td, 'tabular-nums']">
                                        {{ item.price }} coins
                                    </td>
                                    <td :class="td">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                item.active
                                                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                            >{{
                                                item.active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }}</span
                                        >
                                    </td>
                                    <td :class="[td, 'text-right']">
                                        <button
                                            v-if="item.active"
                                            type="button"
                                            :class="btn"
                                            @click="deactivate(item.id)"
                                        >
                                            Deactivate
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div :class="[card, 'p-5']">
                    <h2 class="font-semibold">New cosmetic</h2>
                    <form
                        class="mt-3 grid gap-3 sm:grid-cols-2"
                        @submit.prevent="createCosmetic"
                    >
                        <label class="grid gap-1 text-sm font-medium">
                            Type
                            <select v-model="form.type" :class="field">
                                <option
                                    v-for="t in TYPE_OPTIONS"
                                    :key="t.value"
                                    :value="t.value"
                                >
                                    {{ t.label }}
                                </option>
                            </select>
                            <span v-if="fieldError('type')" class="err">{{
                                fieldError('type')
                            }}</span>
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Name
                            <input
                                v-model="form.name"
                                type="text"
                                maxlength="60"
                                :class="field"
                            />
                            <span v-if="fieldError('name')" class="err">{{
                                fieldError('name')
                            }}</span>
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Slug
                            <input
                                v-model="form.slug"
                                type="text"
                                maxlength="60"
                                :class="field"
                            />
                            <span v-if="fieldError('slug')" class="err">{{
                                fieldError('slug')
                            }}</span>
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Price (coins)
                            <input
                                v-model.number="form.price"
                                type="number"
                                min="0"
                                max="1000000"
                                :class="field"
                            />
                            <span v-if="fieldError('price')" class="err">{{
                                fieldError('price')
                            }}</span>
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Sort
                            <input
                                v-model.number="form.sort"
                                type="number"
                                min="-32768"
                                max="32767"
                                :class="field"
                            />
                            <span v-if="fieldError('sort')" class="err">{{
                                fieldError('sort')
                            }}</span>
                        </label>
                        <label
                            v-if="form.type === 'background'"
                            class="grid gap-1 text-sm font-medium"
                        >
                            Background style
                            <select v-model="form.bgMode" :class="field">
                                <option value="color">Colour</option>
                                <option value="gradient">Gradient</option>
                            </select>
                        </label>
                        <label
                            v-if="formFields.includes('color')"
                            class="grid gap-1 text-sm font-medium"
                        >
                            Colour
                            <input
                                v-model="form.color"
                                type="color"
                                class="h-9 w-20 cursor-pointer rounded-md border border-input bg-background p-1"
                            />
                        </label>
                        <label
                            v-if="formFields.includes('from')"
                            class="grid gap-1 text-sm font-medium"
                        >
                            From
                            <input
                                v-model="form.from"
                                type="color"
                                class="h-9 w-20 cursor-pointer rounded-md border border-input bg-background p-1"
                            />
                        </label>
                        <label
                            v-if="formFields.includes('to')"
                            class="grid gap-1 text-sm font-medium"
                        >
                            To
                            <input
                                v-model="form.to"
                                type="color"
                                class="h-9 w-20 cursor-pointer rounded-md border border-input bg-background p-1"
                            />
                        </label>
                        <label
                            v-if="formFields.includes('angle')"
                            class="grid gap-1 text-sm font-medium"
                        >
                            Angle (0-360)
                            <input
                                v-model.number="form.angle"
                                type="number"
                                min="0"
                                max="360"
                                :class="field"
                            />
                        </label>
                        <label
                            v-if="formFields.includes('strength')"
                            class="grid gap-1 text-sm font-medium"
                        >
                            Strength (1-3)
                            <input
                                v-model.number="form.strength"
                                type="number"
                                min="1"
                                max="3"
                                :class="field"
                            />
                        </label>
                        <label
                            v-if="formFields.includes('code')"
                            class="grid gap-1 text-sm font-medium"
                        >
                            Smiley code, like (wave)
                            <input
                                v-model="form.code"
                                type="text"
                                :class="field"
                            />
                        </label>
                        <label
                            v-if="formFields.includes('asset')"
                            class="grid gap-1 text-sm font-medium"
                        >
                            Asset slug (file in public/chat-assets/pawns)
                            <input
                                v-model="form.asset"
                                type="text"
                                :class="field"
                            />
                        </label>
                        <span v-if="payloadError()" class="err sm:col-span-2">{{
                            payloadError()
                        }}</span>
                        <div class="sm:col-span-2">
                            <button
                                type="submit"
                                :class="btnPrimary"
                                :disabled="busy"
                            >
                                Create cosmetic
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Messages -->
            <div v-show="tab === 'messages'" :class="card">
                <ul class="divide-y text-sm">
                    <li
                        v-if="messages.length === 0"
                        class="px-4 py-10 text-center text-muted-foreground"
                    >
                        No messages yet.
                    </li>
                    <li
                        v-for="m in messages"
                        :key="m.id"
                        class="flex items-start gap-3 px-4 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-baseline gap-x-2">
                                <strong>{{
                                    m.member?.name ?? 'unknown'
                                }}</strong>
                                <span class="text-xs text-muted-foreground">{{
                                    m.created_at
                                }}</span>
                            </p>
                            <p class="mt-0.5 break-words">{{ m.body }}</p>
                        </div>
                        <button
                            type="button"
                            :class="btn"
                            :disabled="busy"
                            @click="togglePin(m)"
                        >
                            {{ isPinned(m) ? 'Unpin' : 'Pin' }}
                        </button>
                        <button
                            type="button"
                            :class="btnDanger"
                            :disabled="busy"
                            @click="deleteMessage(m)"
                        >
                            Delete
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Rules shown to a member before their first message -->
            <div v-show="tab === 'rules'" :class="[card, 'space-y-3 p-4']">
                <p class="text-sm text-muted-foreground">
                    Shown to each member before they can chat, until they accept.
                    Editing the text asks everyone to accept again. Leave empty
                    to turn the gate off. Plain text only.
                </p>
                <textarea
                    v-model="rulesText"
                    rows="10"
                    maxlength="2000"
                    :class="[field, 'h-auto py-2']"
                ></textarea>
                <button
                    type="button"
                    :class="btnPrimary"
                    :disabled="busy"
                    @click="saveRules"
                >
                    Save rules
                </button>
            </div>

            <!-- Moderation log -->
            <div v-show="tab === 'log'" :class="card">
                <ul class="divide-y text-sm">
                    <li
                        v-if="log.length === 0"
                        class="px-4 py-10 text-center text-muted-foreground"
                    >
                        No moderation actions yet.
                    </li>
                    <li
                        v-for="entry in log"
                        :key="entry.id"
                        class="flex flex-wrap items-center gap-x-3 px-4 py-3"
                    >
                        <span class="text-xs text-muted-foreground">{{
                            entry.created_at
                        }}</span>
                        <span class="font-medium">{{ entry.action }}</span>
                        <span class="text-muted-foreground"
                            >member {{ entry.target_member_id }}</span
                        >
                    </li>
                </ul>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.err {
    display: block;
    font-size: 0.75rem;
    font-weight: 400;
    color: hsl(0 84% 60%);
}
</style>
