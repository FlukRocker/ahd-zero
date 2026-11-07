<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface CosmeticRow {
    id: number;
    name: string;
    type: string;
    price: number;
    active: boolean;
}

interface ParticipantRow {
    member_id: number;
    rank: string;
    balance: number;
    banned_at: string | null;
    member: { name: string } | null;
}

interface LogRow {
    id: number;
    action: string;
    target_member_id: number;
    created_at: string;
}

defineProps<{
    cosmetics: CosmeticRow[];
    participants: { data: ParticipantRow[] };
    log: LogRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Chat', href: '/dashboard/chat' },
];
const error = ref('');

async function send(method: string, url: string, body?: object) {
    error.value = '';
    const res = await fetch(url, {
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

    if (!res.ok) {
        const data = (await res.json().catch(() => ({}))) as {
            message?: string;
        };
        error.value = data.message ?? `Request failed (${res.status})`;

        return;
    }

    router.reload({ preserveScroll: true });
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

function toggleBan(row: ParticipantRow) {
    void send(
        'POST',
        `/dashboard/chat/members/${row.member_id}/ban`,
        row.banned_at ? { unban: true } : {},
    );
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
                                    {{ row.banned_at ? 'Unban' : 'Ban' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
