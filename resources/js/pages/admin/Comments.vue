<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
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
    if (type.includes('Anime')) return 'อนิเมะ';
    if (type.includes('Episode')) return 'ตอน';
    return type;
}

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('th-TH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Comments', href: '/dashboard/comments' },
];
</script>

<template>
    <Head title="Comments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="search"
                    placeholder="ค้นหาความคิดเห็น..."
                    class="min-w-64 rounded-lg px-3 py-2 text-[13px] outline-none"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                />
                <select
                    v-model="status"
                    class="rounded-lg px-3 py-2 text-[13px] outline-none"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                    @change="apply"
                >
                    <option value="all">ทั้งหมด</option>
                    <option value="active">ปกติ</option>
                    <option value="deleted">ถูกลบ</option>
                </select>
                <span
                    class="ml-auto font-mono text-[12px]"
                    style="color: hsl(var(--fg-muted))"
                >
                    ทั้งหมด {{ comments.total }} ข้อความ
                </span>
            </div>

            <div
                class="overflow-hidden rounded-xl"
                style="
                    background: hsl(var(--bg-elev));
                    border: 1px solid hsl(var(--border-ahd));
                "
            >
                <ul class="divide-y" style="border-color: hsl(var(--border-ahd))">
                    <li
                        v-for="c in comments.data"
                        :key="c.id"
                        class="flex items-start gap-3 p-4"
                        :class="c.deleted_by ? 'opacity-50' : ''"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[12px] font-bold"
                            style="
                                background: hsl(var(--accent) / 0.12);
                                color: hsl(var(--accent));
                            "
                        >
                            {{ c.user_name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span
                                    class="text-[13px] font-medium"
                                    style="color: hsl(var(--fg))"
                                >
                                    {{ c.user_name }}
                                </span>
                                <span
                                    v-if="c.is_admin"
                                    class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                    style="
                                        background: hsl(var(--accent));
                                        color: hsl(var(--accent-fg));
                                    "
                                >
                                    Admin
                                </span>
                                <span
                                    v-if="c.deleted_by"
                                    class="rounded px-1.5 py-0.5 text-[10px] font-mono"
                                    style="
                                        background: hsl(var(--bg-soft));
                                        color: hsl(var(--fg-muted));
                                    "
                                >
                                    ถูกลบ ({{ c.deleted_by }})
                                </span>
                                <span
                                    class="font-mono text-[11px]"
                                    style="color: hsl(var(--fg-faint))"
                                >
                                    {{ formatDate(c.created_at) }}
                                </span>
                            </div>
                            <p
                                v-if="!c.deleted_by"
                                class="line-clamp-2 text-[13px]"
                                style="color: hsl(var(--fg-muted))"
                            >
                                {{ c.body }}
                            </p>
                            <p
                                v-else
                                class="text-[13px] italic"
                                style="color: hsl(var(--fg-faint))"
                            >
                                ข้อความถูกลบ
                            </p>
                            <div class="mt-1 flex items-center gap-2">
                                <span
                                    class="rounded px-1.5 py-0.5 font-mono text-[10px]"
                                    style="
                                        background: hsl(var(--bg-soft));
                                        color: hsl(var(--fg-muted));
                                    "
                                >
                                    {{ typeLabel(c.commentable_type) }} #{{ c.commentable_id }}
                                </span>
                            </div>
                        </div>
                        <button
                            v-if="!c.deleted_by"
                            type="button"
                            class="btn btn-ghost text-[12px]"
                            @click="openAction(c)"
                        >
                            จัดการ
                        </button>
                    </li>
                </ul>
                <div
                    v-if="comments.data.length === 0"
                    class="py-12 text-center text-[13px]"
                    style="color: hsl(var(--fg-muted))"
                >
                    ไม่พบความคิดเห็น
                </div>
            </div>

            <Pagination
                :links="comments.links"
                :last-page="comments.last_page"
            />
        </div>

        <Teleport to="body">
            <div
                v-if="actionModal"
                class="fixed inset-0 z-50 flex items-center justify-center"
                style="background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(4px)"
                @click.self="actionModal = false"
            >
                <div
                    class="mx-4 w-full max-w-md rounded-2xl p-6 shadow-2xl"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-strong));
                    "
                >
                    <h3 class="font-display mb-1 text-[20px] italic">
                        จัดการความคิดเห็น
                    </h3>
                    <p
                        class="mb-5 text-[13px]"
                        style="color: hsl(var(--fg-muted))"
                    >
                        โดย <strong>{{ actionTarget?.user_name }}</strong>
                    </p>

                    <div class="mb-5 space-y-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl p-3"
                            :style="
                                actionType === 'delete'
                                    ? 'border: 1px solid hsl(var(--accent)); background: hsl(var(--accent) / 0.06)'
                                    : 'border: 1px solid hsl(var(--border-ahd))'
                            "
                        >
                            <input
                                v-model="actionType"
                                type="radio"
                                value="delete"
                                class="mt-0.5"
                            />
                            <div>
                                <p
                                    class="text-[13px] font-medium"
                                    style="color: hsl(var(--fg))"
                                >
                                    ลบเฉพาะข้อความนี้
                                </p>
                                <p
                                    class="text-[11px]"
                                    style="color: hsl(var(--fg-muted))"
                                >
                                    ลบเฉพาะข้อความที่เลือก
                                </p>
                            </div>
                        </label>

                        <label
                            v-if="actionTarget?.user_id"
                            class="flex cursor-pointer items-start gap-3 rounded-xl p-3"
                            :style="
                                actionType === 'delete-all'
                                    ? 'border: 1px solid hsl(40 80% 50%); background: hsl(40 80% 50% / 0.08)'
                                    : 'border: 1px solid hsl(var(--border-ahd))'
                            "
                        >
                            <input
                                v-model="actionType"
                                type="radio"
                                value="delete-all"
                                class="mt-0.5"
                            />
                            <div>
                                <p
                                    class="text-[13px] font-medium"
                                    style="color: hsl(var(--fg))"
                                >
                                    ลบข้อความทั้งหมดของผู้ใช้นี้
                                </p>
                                <p
                                    class="text-[11px]"
                                    style="color: hsl(var(--fg-muted))"
                                >
                                    ลบทุกข้อความที่ {{ actionTarget?.user_name }} เคยเขียน
                                </p>
                            </div>
                        </label>

                        <label
                            v-if="actionTarget?.user_id && !actionTarget?.is_admin"
                            class="flex cursor-pointer items-start gap-3 rounded-xl p-3"
                            :style="
                                actionType === 'delete-ban'
                                    ? 'border: 1px solid hsl(0 78% 56%); background: hsl(0 78% 56% / 0.08)'
                                    : 'border: 1px solid hsl(var(--border-ahd))'
                            "
                        >
                            <input
                                v-model="actionType"
                                type="radio"
                                value="delete-ban"
                                class="mt-0.5"
                            />
                            <div>
                                <p
                                    class="text-[13px] font-medium"
                                    style="color: hsl(0 78% 56%)"
                                >
                                    ลบและแบนผู้ใช้
                                </p>
                                <p
                                    class="text-[11px]"
                                    style="color: hsl(var(--fg-muted))"
                                >
                                    ลบข้อความ + แบนบัญชี {{ actionTarget?.user_name }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <div
                        v-if="actionType === 'delete-ban'"
                        class="mb-5 space-y-3 rounded-xl p-4"
                        style="
                            border: 1px solid hsl(0 78% 56% / 0.4);
                            background: hsl(0 78% 56% / 0.05);
                        "
                    >
                        <div>
                            <label
                                class="mb-1 block text-[11px] font-mono"
                                style="color: hsl(var(--fg-muted))"
                            >
                                เหตุผลในการแบน
                            </label>
                            <input
                                v-model="banReason"
                                type="text"
                                placeholder="เหตุผล (ไม่บังคับ)"
                                class="w-full rounded-lg px-3 py-2 text-[13px] outline-none"
                                style="
                                    background: hsl(var(--bg-elev));
                                    border: 1px solid hsl(var(--border-ahd));
                                "
                            />
                        </div>
                        <label
                            class="flex cursor-pointer items-center gap-2 text-[13px]"
                            style="color: hsl(var(--fg-muted))"
                        >
                            <input
                                v-model="deleteAllOnBan"
                                type="checkbox"
                            />
                            ลบข้อความทั้งหมดของผู้ใช้นี้ด้วย
                        </label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="btn btn-ghost"
                            @click="actionModal = false"
                        >
                            ยกเลิก
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="confirmAction"
                        >
                            {{ actionType === 'delete-ban' ? 'ลบและแบน' : actionType === 'delete-all' ? 'ลบทั้งหมด' : 'ลบข้อความ' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
