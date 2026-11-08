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

    router.reload();

    return { ok: true, status: res.status };
}

const busy = ref(false);

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
        <section class="space-y-8 p-6">
            <p v-if="error" class="text-sm" style="color: hsl(0 84% 60%)">
                {{ error }}
            </p>

            <div>
                <h2 class="text-lg font-semibold">Participants</h2>
                <table class="mt-2 w-full text-sm">
                    <thead>
                        <tr class="text-left opacity-60">
                            <th>Member</th>
                            <th>Rank</th>
                            <th>Coins</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in participants.data"
                            :key="row.member_id"
                        >
                            <td>
                                {{ row.member?.name ?? `#${row.member_id}` }}
                            </td>
                            <td>
                                <select
                                    :value="row.rank"
                                    @change="
                                        setRank(
                                            row.member_id,
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="member">member</option>
                                    <option value="mod">mod</option>
                                    <option value="owner">owner</option>
                                    <option value="main_owner">
                                        main owner
                                    </option>
                                </select>
                            </td>
                            <td>{{ row.balance }}</td>
                            <td class="space-x-2">
                                <button
                                    type="button"
                                    @click="grant(row.member_id)"
                                >
                                    Grant coins
                                </button>
                                <button type="button" @click="toggleBan(row)">
                                    {{ isBanned(row) ? 'Unban' : 'Ban' }}
                                </button>
                                <select v-model="grantChoice[row.member_id]">
                                    <option value="" disabled>Cosmetic…</option>
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
                                    :disabled="
                                        busy || !grantChoice[row.member_id]
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
                            </td>
                        </tr>
                    </tbody>
                </table>
                <nav
                    v-if="participants.links && participants.links.length > 3"
                    class="mt-2 flex flex-wrap gap-2 text-sm"
                >
                    <template
                        v-for="(l, i) in participants.links"
                        :key="`${i}-${l.label}`"
                    >
                        <Link
                            v-if="l.url"
                            :href="l.url"
                            preserve-scroll
                            :class="l.active ? 'font-bold underline' : ''"
                        >
                            {{ linkLabel(l.label) }}
                        </Link>
                        <span v-else class="opacity-40">{{
                            linkLabel(l.label)
                        }}</span>
                    </template>
                </nav>
            </div>

            <div>
                <h2 class="text-lg font-semibold">Rooms</h2>
                <ul class="mt-2 space-y-2 text-sm">
                    <li
                        v-for="room in rooms"
                        :key="room.id"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <code>{{ room.slug }}</code>
                        <input
                            v-model="roomEdit(room).name"
                            maxlength="60"
                            aria-label="Room name"
                        />
                        <input
                            v-model="roomEdit(room).topic"
                            maxlength="160"
                            placeholder="Topic"
                            aria-label="Room topic"
                        />
                        <input
                            v-model="roomEdit(room).sort"
                            type="number"
                            class="w-20"
                            aria-label="Room sort order"
                        />
                        <button
                            type="button"
                            class="underline"
                            :disabled="busy"
                            @click="saveRoom(room)"
                        >
                            Save
                        </button>
                        <span v-if="!room.active" class="opacity-60"
                            >(inactive)</span
                        >
                        <button
                            v-if="room.slug !== 'lobby'"
                            type="button"
                            class="underline"
                            :disabled="busy"
                            @click="toggleRoom(room)"
                        >
                            {{ room.active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </li>
                </ul>

                <h3 class="mt-4 font-semibold">New room</h3>
                <form
                    class="mt-2 grid max-w-xl gap-2 text-sm"
                    @submit.prevent="createRoom"
                >
                    <label>
                        Name
                        <input
                            v-model="roomForm.name"
                            maxlength="60"
                            required
                        />
                        <span v-if="fieldError('name')" class="text-xs">{{
                            fieldError('name')
                        }}</span>
                    </label>
                    <label>
                        Slug (fixed once created)
                        <input
                            v-model="roomForm.slug"
                            maxlength="40"
                            pattern="[a-z0-9\-]{2,40}"
                            required
                        />
                        <span v-if="fieldError('slug')" class="text-xs">{{
                            fieldError('slug')
                        }}</span>
                    </label>
                    <label>
                        Topic
                        <input v-model="roomForm.topic" maxlength="160" />
                    </label>
                    <label>
                        Sort
                        <input v-model="roomForm.sort" type="number" />
                    </label>
                    <div>
                        <button type="submit" :disabled="busy">
                            Create room
                        </button>
                    </div>
                </form>
            </div>

            <div>
                <h2 class="text-lg font-semibold">New cosmetic</h2>
                <form
                    class="mt-2 grid max-w-xl gap-2 text-sm"
                    @submit.prevent="createCosmetic"
                >
                    <label>
                        Type
                        <select v-model="form.type">
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
                    <label>
                        Name
                        <input v-model="form.name" type="text" maxlength="60" />
                        <span v-if="fieldError('name')" class="err">{{
                            fieldError('name')
                        }}</span>
                    </label>
                    <label>
                        Slug
                        <input v-model="form.slug" type="text" maxlength="60" />
                        <span v-if="fieldError('slug')" class="err">{{
                            fieldError('slug')
                        }}</span>
                    </label>
                    <label>
                        Price
                        <input
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            max="1000000"
                        />
                        <span v-if="fieldError('price')" class="err">{{
                            fieldError('price')
                        }}</span>
                    </label>
                    <label>
                        Sort
                        <input
                            v-model.number="form.sort"
                            type="number"
                            min="-32768"
                            max="32767"
                        />
                        <span v-if="fieldError('sort')" class="err">{{
                            fieldError('sort')
                        }}</span>
                    </label>
                    <label v-if="form.type === 'background'">
                        Background style
                        <select v-model="form.bgMode">
                            <option value="color">Colour</option>
                            <option value="gradient">Gradient</option>
                        </select>
                    </label>
                    <label v-if="formFields.includes('color')">
                        Colour
                        <input v-model="form.color" type="color" />
                    </label>
                    <label v-if="formFields.includes('from')">
                        From
                        <input v-model="form.from" type="color" />
                    </label>
                    <label v-if="formFields.includes('to')">
                        To
                        <input v-model="form.to" type="color" />
                    </label>
                    <label v-if="formFields.includes('angle')">
                        Angle (0-360)
                        <input
                            v-model.number="form.angle"
                            type="number"
                            min="0"
                            max="360"
                        />
                    </label>
                    <label v-if="formFields.includes('strength')">
                        Strength (1-3)
                        <input
                            v-model.number="form.strength"
                            type="number"
                            min="1"
                            max="3"
                        />
                    </label>
                    <label v-if="formFields.includes('code')">
                        Smiley code, like (wave)
                        <input v-model="form.code" type="text" />
                    </label>
                    <label v-if="formFields.includes('asset')">
                        Asset slug (file in public/chat/pawns)
                        <input v-model="form.asset" type="text" />
                    </label>
                    <span v-if="payloadError()" class="err">{{
                        payloadError()
                    }}</span>
                    <div>
                        <button type="submit" :disabled="busy">
                            Create cosmetic
                        </button>
                    </div>
                </form>
            </div>

            <div>
                <h2 class="text-lg font-semibold">Cosmetics</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="item in cosmetics" :key="item.id">
                        {{ item.name }} — {{ item.type }} —
                        {{ item.price }} coins
                        <span v-if="!item.active" class="opacity-60"
                            >(inactive)</span
                        >
                        <button
                            v-else
                            type="button"
                            class="ml-2 underline"
                            @click="deactivate(item.id)"
                        >
                            Deactivate
                        </button>
                    </li>
                </ul>
            </div>

            <div>
                <h2 class="text-lg font-semibold">Recent messages</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="m in messages" :key="m.id">
                        <strong>{{ m.member?.name ?? 'unknown' }}</strong>
                        <span class="opacity-60"> {{ m.created_at }} </span>
                        {{ m.body }}
                        <button
                            type="button"
                            class="ml-2 underline"
                            :disabled="busy"
                            @click="deleteMessage(m)"
                        >
                            Delete
                        </button>
                    </li>
                </ul>
            </div>

            <div>
                <h2 class="text-lg font-semibold">Moderation log</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="entry in log" :key="entry.id">
                        {{ entry.created_at }} — {{ entry.action }} → member
                        {{ entry.target_member_id }}
                    </li>
                </ul>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.err {
    display: block;
    color: hsl(0 84% 60%);
}
</style>
