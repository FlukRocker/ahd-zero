<script setup lang="ts">
import AdminPagination from '@/components/AdminPagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { debouncedWatch } from '@vueuse/core';
import { ref } from 'vue';

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
    router.get(
        '/dashboard/members',
        { search: search.value, status: status.value },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

debouncedWatch(search, apply, { debounce: 300 });

async function ban(id: number) {
    const reason = window.prompt('Reason for ban?') ?? null;
    await fetch(`/dashboard/members/${id}/ban`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN':
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '',
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
            'X-CSRF-TOKEN':
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '',
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
            'X-CSRF-TOKEN':
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '',
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });
    router.reload({ only: ['members'] });
}

const field =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
const btn =
    'inline-flex h-8 items-center justify-center rounded-md border border-input bg-background px-3 text-xs font-medium hover:bg-accent';
const btnDanger =
    'inline-flex h-8 items-center justify-center rounded-md border border-destructive/40 px-3 text-xs font-medium text-destructive hover:bg-destructive/10';
const th =
    'px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground';

function joined(date: string | null): string {
    return date
        ? new Date(date).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Members', href: '/dashboard/members' },
];
</script>

<template>
    <Head title="Members" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="mx-auto w-full max-w-6xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Members</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ members.total ?? members.data.length }} accounts.
                        Search by name or email.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search name or email…"
                        aria-label="Search members"
                        :class="[field, 'w-64']"
                    />
                    <select
                        v-model="status"
                        :class="field"
                        aria-label="Filter by status"
                        @change="apply"
                    >
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="banned">Banned</option>
                    </select>
                </div>
            </div>

            <div
                class="rounded-xl border bg-card text-card-foreground shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th :class="th">Member</th>
                                <th :class="th">Status</th>
                                <th :class="th">Joined</th>
                                <th :class="[th, 'text-right']">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!members.data.length">
                                <td
                                    colspan="4"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No members match.
                                </td>
                            </tr>
                            <tr v-for="m in members.data" :key="m.id">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold uppercase"
                                            aria-hidden="true"
                                        >
                                            {{ (m.name || '?').charAt(0) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">
                                                {{ m.name }}
                                            </p>
                                            <p
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ m.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-if="m.banned_at"
                                            class="rounded-full bg-destructive/15 px-2 py-0.5 text-xs font-medium text-destructive"
                                            :title="m.ban_reason ?? ''"
                                            >Banned</span
                                        >
                                        <span
                                            v-else
                                            class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                            >Active</span
                                        >
                                        <span
                                            v-if="!m.email_verified_at"
                                            class="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                                            >Unverified</span
                                        >
                                    </div>
                                    <p
                                        v-if="m.banned_at && m.ban_reason"
                                        class="mt-1 max-w-xs truncate text-xs text-muted-foreground"
                                    >
                                        {{ m.ban_reason }}
                                    </p>
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ joined(m.created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            v-if="!m.banned_at"
                                            type="button"
                                            :class="btn"
                                            @click="ban(m.id)"
                                        >
                                            Ban
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            :class="btn"
                                            @click="unban(m.id)"
                                        >
                                            Unban
                                        </button>
                                        <button
                                            type="button"
                                            :class="btnDanger"
                                            @click="destroy(m.id)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <AdminPagination :links="members.links" />
        </section>
    </AppLayout>
</template>
