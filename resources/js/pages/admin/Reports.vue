<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Group {
    type: 'chat_message' | 'comment';
    id: string;
    excerpt: string;
    author: { name: string | null; memberId: number | null };
    count: number;
    reasons: string[];
    notes: string[];
    status: string;
    available: boolean;
}

defineProps<{
    groups: Group[];
    filters: { status: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/dashboard/reports' },
];

const statuses = ['open', 'actioned', 'dismissed'];
const busy = ref<string | null>(null);
const error = ref('');

function csrf(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

const keyOf = (g: Group) => `${g.type}:${g.id}`;

async function act(g: Group, action: string, confirmText?: string) {
    if (confirmText && !window.confirm(confirmText)) {
        return;
    }

    busy.value = keyOf(g) + action;
    error.value = '';

    try {
        const response = await fetch(
            `/dashboard/reports/${keyOf(g)}/${action}`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: '{}',
            },
        );

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            error.value = payload.message ?? 'That action failed.';
            return;
        }

        router.reload();
    } finally {
        busy.value = null;
    }
}

function filter(status: string) {
    router.get('/dashboard/reports', { status }, { preserveState: true });
}
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center gap-2">
                <h1 class="font-display mr-4 text-[26px] italic">Reports</h1>
                <button
                    v-for="s in statuses"
                    :key="s"
                    type="button"
                    class="rounded-md px-3 py-1 text-sm capitalize"
                    :class="
                        filters.status === s
                            ? 'bg-primary text-primary-foreground'
                            : 'border'
                    "
                    @click="filter(s)"
                >
                    {{ s }}
                </button>
            </div>

            <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

            <p v-if="!groups.length" class="text-sm opacity-60">
                No reports here.
            </p>

            <ul class="space-y-3">
                <li
                    v-for="g in groups"
                    :key="keyOf(g)"
                    class="rounded-xl border p-4"
                >
                    <div
                        class="mb-1 flex flex-wrap items-center gap-2 text-xs opacity-70"
                    >
                        <span class="uppercase">{{
                            g.type === 'chat_message'
                                ? 'Chat message'
                                : 'Comment'
                        }}</span>
                        <span
                            >· {{ g.count }}
                            {{ g.count === 1 ? 'report' : 'reports' }}</span
                        >
                        <span
                            v-for="r in g.reasons"
                            :key="r"
                            class="rounded bg-black/10 px-1.5"
                            >{{ r }}</span
                        >
                    </div>

                    <p v-if="g.available" class="break-words">
                        {{ g.excerpt }}
                    </p>
                    <p v-else class="italic opacity-60">
                        This item was deleted.
                    </p>
                    <p v-if="g.author.name" class="text-sm opacity-70">
                        by {{ g.author.name }}
                    </p>
                    <ul v-if="g.notes.length" class="mt-1 text-sm opacity-80">
                        <li v-for="(n, i) in g.notes" :key="i">“{{ n }}”</li>
                    </ul>

                    <div
                        v-if="g.status === 'open'"
                        class="mt-3 flex flex-wrap gap-2 text-sm"
                    >
                        <template v-if="g.available">
                            <button
                                type="button"
                                class="rounded-md border px-3 py-1"
                                :disabled="busy !== null"
                                @click="act(g, 'delete', 'Delete this item?')"
                            >
                                Delete
                            </button>
                            <template v-if="g.author.memberId !== null">
                                <template v-if="g.type === 'chat_message'">
                                    <button
                                        type="button"
                                        class="rounded-md border px-3 py-1"
                                        :disabled="busy !== null"
                                        @click="
                                            act(
                                                g,
                                                'ban-chat',
                                                'Ban this member from chat?',
                                            )
                                        "
                                    >
                                        Ban from chat
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border px-3 py-1"
                                        :disabled="busy !== null"
                                        @click="
                                            act(
                                                g,
                                                'ban-site',
                                                'Ban this member from the whole site?',
                                            )
                                        "
                                    >
                                        Ban from site
                                    </button>
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-md border px-3 py-1"
                                    :disabled="busy !== null"
                                    @click="
                                        act(
                                            g,
                                            'ban-comment',
                                            'Delete this comment and ban its author?',
                                        )
                                    "
                                >
                                    Delete and ban
                                </button>
                            </template>
                        </template>
                        <button
                            type="button"
                            class="rounded-md border px-3 py-1"
                            :disabled="busy !== null"
                            @click="act(g, 'dismiss')"
                        >
                            Dismiss
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
