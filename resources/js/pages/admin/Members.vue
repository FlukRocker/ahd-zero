<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { debouncedWatch } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { type BreadcrumbItem } from '@/types';

interface MemberRow {
    id: number;
    uuid: string;
    name: string;
    email: string;
    avatar?: string | null;
    bio?: string | null;
    email_verified_at: string | null;
    banned_at: string | null;
    ban_reason?: string | null;
    created_at: string | null;
}

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total?: number;
}

const props = defineProps<{
    members: Paginator<MemberRow>;
    filters: { search: string; status: string };
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');

function apply() {
    router.get('/dashboard/members', { search: search.value, status: status.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

debouncedWatch(search, apply, { debounce: 300 });

async function ban(id: number) {
    const reason = window.prompt('Reason for ban?') ?? null;
    await fetch(`/dashboard/members/${id}/ban`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ reason }),
    });
    router.reload({ only: ['members'] });
}

async function unban(id: number) {
    await fetch(`/dashboard/members/${id}/unban`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });
    router.reload({ only: ['members'] });
}

async function destroy(id: number) {
    if (!window.confirm('Delete this member permanently?')) return;
    await fetch(`/dashboard/members/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });
    router.reload({ only: ['members'] });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Members', href: '/dashboard/members' },
];
</script>

<template>
    <Head title="Members" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 space-y-6">
            <div class="flex flex-wrap gap-3 items-center">
                <div
                    class="flex items-center gap-3 px-4 py-2 rounded-full flex-1 min-w-[240px]"
                    style="background: hsl(var(--bg-soft)); border: 1px solid hsl(var(--border-ahd));"
                >
                    <AhdIcon name="search" :size="16" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search name or email…"
                        class="flex-1 bg-transparent outline-none text-[14px]"
                    />
                </div>
                <select
                    v-model="status"
                    class="px-3 py-2 rounded-full text-[13px] outline-none"
                    style="background: hsl(var(--bg-soft)); border: 1px solid hsl(var(--border-ahd));"
                    @change="apply"
                >
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="banned">Banned</option>
                </select>
            </div>

            <div class="rounded-xl overflow-hidden" style="border: 1px solid hsl(var(--border-ahd));">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr style="background: hsl(var(--bg-soft));">
                            <th class="text-left p-3 font-mono text-[10px] tracking-widest uppercase">Name</th>
                            <th class="text-left p-3 font-mono text-[10px] tracking-widest uppercase">Email</th>
                            <th class="text-left p-3 font-mono text-[10px] tracking-widest uppercase">Status</th>
                            <th class="text-right p-3 font-mono text-[10px] tracking-widest uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in members.data" :key="m.id" style="border-top: 1px solid hsl(var(--border-ahd));">
                            <td class="p-3">{{ m.name }}</td>
                            <td class="p-3 font-mono text-[12px]" style="color: hsl(var(--fg-muted));">{{ m.email }}</td>
                            <td class="p-3">
                                <span
                                    v-if="m.banned_at"
                                    class="chip font-mono"
                                    style="background: hsl(0 84% 60% / 0.12); color: hsl(0 84% 60%); border-color: hsl(0 84% 60% / 0.3);"
                                >BANNED</span>
                                <span v-else class="chip font-mono">Active</span>
                            </td>
                            <td class="p-3 text-right space-x-2">
                                <button
                                    v-if="!m.banned_at"
                                    type="button"
                                    class="text-[12px] u-grow"
                                    @click="ban(m.id)"
                                >Ban</button>
                                <button
                                    v-else
                                    type="button"
                                    class="text-[12px] u-grow"
                                    @click="unban(m.id)"
                                >Unban</button>
                                <button
                                    type="button"
                                    class="text-[12px] u-grow"
                                    style="color: hsl(0 84% 60%);"
                                    @click="destroy(m.id)"
                                >Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!members.data.length">
                            <td colspan="4" class="p-8 text-center opacity-60 font-mono text-[12px]">
                                No members.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="members.links" />
        </div>
    </AppLayout>
</template>
