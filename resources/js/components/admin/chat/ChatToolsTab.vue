<script setup lang="ts">
import { adminCall, ui } from '@/lib/adminFetch';
import { onMounted, reactive, ref } from 'vue';

interface RoomCtl {
    id: number;
    name: string;
    locked: boolean;
    slow: number;
}

const announcement = ref('');
const words = ref('');
const rooms = ref<RoomCtl[]>([]);
const note = ref('');
const error = ref('');

function report(r: { ok: boolean; error: string }, okMsg: string) {
    error.value = r.ok ? '' : r.error;
    note.value = r.ok ? okMsg : '';
}

async function load() {
    const r = await adminCall<{ announcement: string; words: string; rooms: RoomCtl[] }>(
        'GET',
        '/dashboard/chat/tools/settings',
    );

    if (r.ok && r.data) {
        announcement.value = r.data.announcement;
        words.value = r.data.words;
        rooms.value = r.data.rooms.map((x) => reactive({ ...x }));
    }
}

async function saveAnnouncement() {
    report(
        await adminCall('POST', '/dashboard/chat/tools/announcement', { announcement: announcement.value }),
        announcement.value.trim() === '' ? 'Announcement removed.' : 'Announcement saved. It shows on the next chat load.',
    );
}

async function saveWords() {
    report(await adminCall('POST', '/dashboard/chat/tools/words', { words: words.value }), 'Word list saved.');
    await load();
}

async function saveRoom(room: RoomCtl) {
    report(
        await adminCall('POST', `/dashboard/chat/tools/rooms/${room.id}/controls`, {
            locked: room.locked,
            slow: Number(room.slow) || 0,
        }),
        `${room.name} updated.`,
    );
}

async function clearRoom(room: RoomCtl) {
    if (!window.confirm(`Delete ALL messages in "${room.name}"? This cannot be undone from the dashboard.`)) {
        return;
    }

    const r = await adminCall<{ deleted: number }>('POST', `/dashboard/chat/tools/rooms/${room.id}/clear`);
    report(r, `Cleared ${r.data?.deleted ?? 0} messages from ${room.name}.`);
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <p v-if="note" class="text-sm text-emerald-600 dark:text-emerald-400">{{ note }}</p>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <div :class="[ui.card, 'space-y-3 p-5']">
            <h2 class="font-semibold">Announcement banner</h2>
            <p class="text-sm text-muted-foreground">
                One line shown at the top of every chat box. Leave empty to remove it. Max 300 characters.
            </p>
            <input v-model="announcement" type="text" maxlength="300" :class="ui.field" />
            <button type="button" :class="ui.btnPrimary" @click="saveAnnouncement">Save announcement</button>
        </div>

        <div :class="[ui.card, 'space-y-3 p-5']">
            <h2 class="font-semibold">Room controls</h2>
            <p class="text-sm text-muted-foreground">
                Locked rooms and slow mode apply to members; mods and above are exempt.
            </p>
            <div v-for="room in rooms" :key="room.id" class="flex flex-wrap items-center gap-3 rounded-lg border p-3 text-sm">
                <span class="min-w-24 font-medium">{{ room.name }}</span>
                <label class="flex items-center gap-2"><input v-model="room.locked" type="checkbox" /> Locked</label>
                <label class="flex items-center gap-2">
                    Slow mode (seconds, 0 = off)
                    <input v-model.number="room.slow" type="number" min="0" max="3600" class="h-8 w-20 rounded-md border border-input bg-background px-2" />
                </label>
                <button type="button" :class="ui.btn" @click="saveRoom(room)">Save</button>
                <button type="button" :class="[ui.btnDanger, 'ml-auto']" @click="clearRoom(room)">Clear messages</button>
            </div>
        </div>

        <div :class="[ui.card, 'space-y-3 p-5']">
            <h2 class="font-semibold">Word filter</h2>
            <p class="text-sm text-muted-foreground">
                One word or phrase per line. A message containing one is refused for members (case-insensitive,
                matches inside words, because Thai has no word breaks). Max 500 entries, 2-40 characters each.
            </p>
            <textarea v-model="words" rows="8" :class="[ui.field, 'h-auto py-2 font-mono']"></textarea>
            <button type="button" :class="ui.btnPrimary" @click="saveWords">Save word list</button>
        </div>
    </div>
</template>
