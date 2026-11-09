<script setup lang="ts">
import AdminPagination from '@/components/AdminPagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { debouncedWatch } from '@vueuse/core';
import { ref } from 'vue';

interface CommentRow {
    id: string;
    body: string;
    user_id: string | null;
    user_name: string;
    user_avatar: string | null;
    is_admin: boolean;
    deleted_by: string | null;
    commentable_type: string;
    commentable_id: number;
    created_at: string;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    comments: Paginator<CommentRow>;
    filters: { search: string; status: string };
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');

function apply() {
    router.get(
        '/dashboard/comments',
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

debouncedWatch(search, apply, { debounce: 300 });

const actionModal = ref(false);
const actionTarget = ref<CommentRow | null>(null);
const actionType = ref<'delete' | 'delete-all' | 'delete-ban'>('delete');
const banReason = ref('');
const deleteAllOnBan = ref(true);

function openAction(comment: CommentRow) {
    actionTarget.value = comment;
    actionType.value = 'delete';
    banReason.value = '';
    deleteAllOnBan.value = true;
    actionModal.value = true;
}

async function confirmAction() {
    if (!actionTarget.value) return;
    const csrf = getCsrf();

    if (actionType.value === 'delete') {
        await fetch(`/dashboard/comments/${actionTarget.value.id}`, {
            method: 'DELETE',
            headers: { 'X-XSRF-TOKEN': csrf, Accept: 'application/json' },
            credentials: 'same-origin',
        });
    } else if (actionType.value === 'delete-all') {
        await fetch('/dashboard/comments/delete-all-by-user', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrf,
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: actionTarget.value.user_id }),
        });
    } else if (actionType.value === 'delete-ban') {
        await fetch(
            `/dashboard/comments/${actionTarget.value.id}/delete-and-ban`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    ban_reason: banReason.value,
                    delete_all: deleteAllOnBan.value,
                }),
            },
        );
    }

    actionModal.value = false;
    router.reload();
}

function getCsrf(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function typeLabel(type: string): string {
    if (type.includes('Anime')) return 'Anime';
    if (type.includes('Episode')) return 'Episode';
    return type;
}

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

const field =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
const btn =
    'inline-flex h-8 items-center justify-center rounded-md border border-input bg-background px-3 text-xs font-medium hover:bg-accent';

const choices = [
    {
        value: 'delete',
        title: 'Delete this comment only',
        hint: 'Removes just the selected comment.',
        tone: '',
    },
    {
        value: 'delete-all',
        title: "Delete all of this member's comments",
        hint: 'Removes every comment they have written.',
        tone: 'amber',
    },
    {
        value: 'delete-ban',
        title: 'Delete and ban member',
        hint: 'Removes the comment and bans the account.',
        tone: 'red',
    },
] as const;

function choiceVisible(value: string): boolean {
    const t = actionTarget.value;

    if (value === 'delete') return true;
    if (value === 'delete-all') return Boolean(t?.user_id);

    return Boolean(t?.user_id && !t?.is_admin);
}

const confirmLabel = () =>
    actionType.value === 'delete-ban'
        ? 'Delete and ban'
        : actionType.value === 'delete-all'
          ? 'Delete all'
          : 'Delete comment';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Comments', href: '/dashboard/comments' },
];
</script>

<template>
    <Head title="Comments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="mx-auto w-full max-w-6xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Comments</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ comments.total }} comments. Delete, bulk delete or
                        ban from the Manage button.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search comments…"
                        aria-label="Search comments"
                        :class="[field, 'w-64']"
                    />
                    <select
                        v-model="status"
                        :class="field"
                        aria-label="Filter by status"
                        @change="apply"
                    >
                        <option value="all">All</option>
                        <option value="active">Visible</option>
                        <option value="deleted">Deleted</option>
                    </select>
                </div>
            </div>

            <div
                class="rounded-xl border bg-card text-card-foreground shadow-sm"
            >
                <ul class="divide-y">
                    <li
                        v-if="comments.data.length === 0"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        No comments found.
                    </li>
                    <li
                        v-for="c in comments.data"
                        :key="c.id"
                        class="flex items-start gap-3 px-4 py-3"
                        :class="c.deleted_by ? 'opacity-60' : ''"
                    >
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold uppercase"
                            aria-hidden="true"
                        >
                            {{ c.user_name.charAt(0) }}
                        </div>
                        <div class="min-w-0 flex-1 text-sm">
                            <div
                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <span class="font-medium">{{
                                    c.user_name
                                }}</span>
                                <span
                                    v-if="c.is_admin"
                                    class="rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground uppercase"
                                    >Admin</span
                                >
                                <span
                                    v-if="c.deleted_by"
                                    class="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                    >Deleted by {{ c.deleted_by }}</span
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    formatDate(c.created_at)
                                }}</span>
                                <span
                                    class="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                    >{{ typeLabel(c.commentable_type) }} #{{
                                        c.commentable_id
                                    }}</span
                                >
                            </div>
                            <p
                                v-if="!c.deleted_by"
                                class="mt-1 line-clamp-3 break-words text-muted-foreground"
                            >
                                {{ c.body }}
                            </p>
                            <p v-else class="mt-1 text-muted-foreground italic">
                                Comment removed.
                            </p>
                        </div>
                        <button
                            v-if="!c.deleted_by"
                            type="button"
                            :class="btn"
                            @click="openAction(c)"
                        >
                            Manage
                        </button>
                    </li>
                </ul>
            </div>

            <AdminPagination :links="comments.links" />
        </section>

        <Teleport to="body">
            <div
                v-if="actionModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-sm"
                @click.self="actionModal = false"
                @keydown.escape="actionModal = false"
            >
                <div
                    class="w-full max-w-md rounded-xl border bg-card p-6 text-card-foreground shadow-2xl"
                    role="dialog"
                    aria-label="Manage comment"
                >
                    <h3 class="text-lg font-semibold">Manage comment</h3>
                    <p class="mb-4 text-sm text-muted-foreground">
                        By <strong>{{ actionTarget?.user_name }}</strong>
                    </p>

                    <div class="mb-4 space-y-2">
                        <template v-for="ch in choices" :key="ch.value">
                            <label
                                v-if="choiceVisible(ch.value)"
                                class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm"
                                :class="
                                    actionType === ch.value
                                        ? ch.tone === 'red'
                                            ? 'border-destructive bg-destructive/10'
                                            : ch.tone === 'amber'
                                              ? 'border-amber-500 bg-amber-500/10'
                                              : 'border-primary bg-accent'
                                        : ''
                                "
                            >
                                <input
                                    v-model="actionType"
                                    type="radio"
                                    :value="ch.value"
                                    class="mt-1"
                                />
                                <span>
                                    <span
                                        class="block font-medium"
                                        :class="
                                            ch.tone === 'red'
                                                ? 'text-destructive'
                                                : ''
                                        "
                                        >{{ ch.title }}</span
                                    >
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{ ch.hint }}</span
                                    >
                                </span>
                            </label>
                        </template>
                    </div>

                    <div
                        v-if="actionType === 'delete-ban'"
                        class="mb-4 space-y-3 rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm"
                    >
                        <label class="grid gap-1 font-medium">
                            Ban reason (optional)
                            <input
                                v-model="banReason"
                                type="text"
                                :class="[field, 'w-full font-normal']"
                            />
                        </label>
                        <label
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <input v-model="deleteAllOnBan" type="checkbox" />
                            Also delete all of their comments
                        </label>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            :class="btn"
                            @click="actionModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-8 items-center justify-center rounded-md px-3 text-xs font-medium text-white"
                            :class="
                                actionType === 'delete-ban'
                                    ? 'bg-destructive hover:bg-destructive/90'
                                    : 'bg-primary text-primary-foreground hover:bg-primary/90'
                            "
                            @click="confirmAction"
                        >
                            {{ confirmLabel() }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
