<script setup lang="ts">
import { adminCall, ui } from '@/lib/adminFetch';
import { ref } from 'vue';

interface Msg {
    id: number;
    body: string;
    room_id: number;
    created_at: string;
    member: { id: number; name: string } | null;
}

const props = defineProps<{ rooms: { id: number; name: string }[] }>();

const roomName = (id: number) => props.rooms.find((r) => r.id === id)?.name ?? `room ${id}`;
const emit = defineEmits<{ (e: 'details', id: number): void }>();

const q = ref('');
const member = ref('');
const room = ref('');
const results = ref<Msg[]>([]);
const searched = ref(false);
const error = ref('');
const note = ref('');

async function search() {
    error.value = '';
    note.value = '';
    const params = new URLSearchParams();

    if (q.value.trim()) params.set('q', q.value.trim());
    if (member.value.trim()) params.set('member', member.value.trim());
    if (room.value) params.set('room', room.value);

    const r = await adminCall<{ messages: Msg[] }>('GET', `/dashboard/chat/tools/messages?${params}`);
    searched.value = true;

    if (r.ok && r.data) {
        results.value = r.data.messages;
    } else {
        results.value = [];
        error.value = r.error;
    }
}

async function remove(m: Msg) {
    if (!window.confirm('Delete this message?')) return;
    const r = await adminCall('DELETE', `/dashboard/chat/messages/${m.id}`);

    if (r.ok) {
        results.value = results.value.filter((x) => x.id !== m.id);
    } else {
        error.value = r.error;
    }
}

async function purge(m: Msg) {
    if (!m.member || !window.confirm(`Delete EVERY message written by ${m.member.name}, in all rooms?`)) return;
    const r = await adminCall<{ deleted: number }>('POST', `/dashboard/chat/tools/members/${m.member.id}/purge`);

    if (r.ok) {
        const id = m.member.id;
        results.value = results.value.filter((x) => x.member?.id !== id);
        note.value = `Deleted ${r.data?.deleted ?? 0} messages.`;
    } else {
        error.value = r.error;
    }
}
</script>

<template>
    <div class="space-y-4">
        <form :class="[ui.card, 'grid gap-3 p-4 sm:grid-cols-4']" @submit.prevent="search">
            <input v-model="q" type="text" placeholder="Text in message" :class="ui.field" />
            <input v-model="member" type="text" placeholder="Member name" :class="ui.field" />
            <select v-model="room" :class="ui.field">
                <option value="">All rooms</option>
                <option v-for="r in rooms" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
            </select>
            <button type="submit" :class="ui.btnPrimary">Search</button>
        </form>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <p v-if="note" class="text-sm text-emerald-600 dark:text-emerald-400">{{ note }}</p>

        <div :class="ui.card">
            <ul class="divide-y text-sm">
                <li v-if="searched && results.length === 0" class="px-4 py-8 text-center text-muted-foreground">No messages found.</li>
                <li v-for="m in results" :key="m.id" class="flex flex-wrap items-start gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs text-muted-foreground">
                            <button v-if="m.member" type="button" class="font-medium underline" @click="emit('details', m.member.id)">{{ m.member.name }}</button>
                            · {{ roomName(m.room_id) }} · {{ new Date(m.created_at).toLocaleString() }}
                        </div>
                        <div class="break-words">{{ m.body }}</div>
                    </div>
                    <button type="button" :class="ui.btn" @click="remove(m)">Delete</button>
                    <button v-if="m.member" type="button" :class="ui.btnDanger" @click="purge(m)">Delete all from member</button>
                </li>
            </ul>
        </div>
    </div>
</template>
