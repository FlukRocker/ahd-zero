<script setup lang="ts">
import { adminCall, ui } from '@/lib/adminFetch';
import { onBeforeUnmount, onMounted, ref } from 'vue';

interface Online {
    id: number;
    uuid: string;
    name: string;
    rank: string;
}
interface Stats {
    perDay: { day: string; count: number }[];
    activeMembers7d: number;
    topChatters: { name: string; count: number }[];
    coinsInCirculation: number;
    totalMessages: number;
    bannedNow: number;
    onlineNow: number;
}

const emit = defineEmits<{ (e: 'details', id: number): void }>();

const online = ref<Online[]>([]);
const stats = ref<Stats | null>(null);
const error = ref('');
const busyId = ref<number | null>(null);
let timer: ReturnType<typeof setInterval> | null = null;

async function loadOnline() {
    const r = await adminCall<{ online: Online[] }>(
        'GET',
        '/dashboard/chat/tools/online',
    );

    if (r.ok && r.data) {
        online.value = r.data.online;
    }
}

async function loadStats() {
    const r = await adminCall<Stats>('GET', '/dashboard/chat/tools/stats');

    if (r.ok && r.data) {
        stats.value = r.data;
    }
}

async function act(p: Online, path: string, body?: object, confirmMsg?: string) {
    if (confirmMsg && !window.confirm(confirmMsg)) {
        return;
    }

    busyId.value = p.id;
    error.value = '';
    const r = await adminCall('POST', path, body);

    if (!r.ok) {
        error.value = r.error;
    }

    busyId.value = null;
    await loadOnline();
}

const maxDay = () => Math.max(1, ...(stats.value?.perDay.map((d) => d.count) ?? [1]));

onMounted(() => {
    void loadOnline();
    void loadStats();
    timer = setInterval(() => void loadOnline(), 30000);
});
onBeforeUnmount(() => timer && clearInterval(timer));
</script>

<template>
    <div class="space-y-5">
        <div v-if="stats" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div
                v-for="[label, value] in [
                    ['Online now', stats.onlineNow],
                    ['Active members (7d)', stats.activeMembers7d],
                    ['Total messages', stats.totalMessages],
                    ['Coins in circulation', stats.coinsInCirculation],
                    ['Banned now', stats.bannedNow],
                ]"
                :key="label"
                :class="[ui.card, 'p-4']"
            >
                <div class="text-xs text-muted-foreground">{{ label }}</div>
                <div class="text-2xl font-semibold tabular-nums">{{ value }}</div>
            </div>
        </div>

        <div v-if="stats" class="grid gap-5 lg:grid-cols-3">
            <div :class="[ui.card, 'p-4 lg:col-span-2']">
                <h2 class="text-sm font-semibold">Messages per day (14 days)</h2>
                <div class="mt-3 flex h-32 items-end gap-1">
                    <div
                        v-for="d in stats.perDay"
                        :key="d.day"
                        class="flex-1 rounded-t bg-primary/70"
                        :style="{ height: (d.count / maxDay()) * 100 + '%', minHeight: '2px' }"
                        :title="d.day + ': ' + d.count"
                    ></div>
                </div>
            </div>
            <div :class="[ui.card, 'p-4']">
                <h2 class="text-sm font-semibold">Top chatters (7 days)</h2>
                <ol class="mt-2 space-y-1 text-sm">
                    <li v-if="stats.topChatters.length === 0" class="text-muted-foreground">No messages yet.</li>
                    <li v-for="t in stats.topChatters" :key="t.name" class="flex justify-between">
                        <span class="truncate">{{ t.name }}</span>
                        <span class="tabular-nums text-muted-foreground">{{ t.count }}</span>
                    </li>
                </ol>
            </div>
        </div>

        <div :class="ui.card">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <h2 class="text-sm font-semibold">Online now ({{ online.length }})</h2>
                <button type="button" :class="ui.btn" @click="loadOnline">Refresh</button>
            </div>
            <p v-if="error" class="px-4 pt-3 text-sm text-destructive">{{ error }}</p>
            <ul class="divide-y text-sm">
                <li v-if="online.length === 0" class="px-4 py-8 text-center text-muted-foreground">
                    Nobody is online right now.
                </li>
                <li v-for="p in online" :key="p.uuid" class="flex flex-wrap items-center gap-2 px-4 py-2.5">
                    <span class="font-medium">{{ p.name }}</span>
                    <span class="rounded bg-muted px-1.5 py-0.5 text-[10px] uppercase text-muted-foreground">{{ p.rank }}</span>
                    <span class="ml-auto flex flex-wrap gap-2">
                        <button type="button" :class="ui.btn" @click="emit('details', p.id)">Details</button>
                        <button type="button" :class="ui.btn" :disabled="busyId === p.id" @click="act(p, `/dashboard/chat/tools/members/${p.id}/gag`, { minutes: 10 })">Gag 10m</button>
                        <button type="button" :class="ui.btn" :disabled="busyId === p.id" @click="act(p, `/dashboard/chat/tools/members/${p.id}/kick`)">Kick</button>
                        <button type="button" :class="ui.btnDanger" :disabled="busyId === p.id" @click="act(p, `/dashboard/chat/members/${p.id}/ban`, {}, `Ban ${p.name} from chat?`)">Ban</button>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
