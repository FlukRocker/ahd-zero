<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import CommentForm from './CommentForm.vue';
import CommentItem, { type CommentData } from './CommentItem.vue';

interface PageProps {
    auth?: { user?: { id?: number | string; name?: string } | null };
    memberAuth?: {
        member?: { id?: string; name?: string; avatar?: string | null } | null;
    };
}

const props = defineProps<{
    commentableType: 'anime' | 'episode';
    commentableId: number;
}>();

const page = usePage<PageProps>();

const member = computed(() => page.props.memberAuth?.member ?? null);
const adminUser = computed(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => adminUser.value !== null && adminUser.value !== undefined);
const isLoggedIn = computed(() => member.value !== null || isAdmin.value);

const currentUserId = computed(
    () =>
        member.value?.id ??
        (adminUser.value?.id !== undefined && adminUser.value.id !== null
            ? String(adminUser.value.id)
            : null),
);
const currentUserName = computed(
    () => member.value?.name ?? adminUser.value?.name ?? null,
);

const comments = ref<CommentData[]>([]);
const loading = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);

async function fetchComments(pageNum = 1) {
    loading.value = true;
    try {
        const res = await fetch(
            `/api/comments/${props.commentableType}/${props.commentableId}?page=${pageNum}`,
        );
        const data = await res.json();
        if (pageNum === 1) {
            comments.value = data.data ?? [];
        } else {
            comments.value.push(...(data.data ?? []));
        }
        currentPage.value = data.current_page ?? 1;
        lastPage.value = data.last_page ?? 1;
        total.value = data.total ?? 0;
    } catch {
        // network error — leave list empty
    } finally {
        loading.value = false;
    }
}

async function submitComment(
    body: string,
    parentId: string | null,
    turnstileToken?: string,
) {
    const res = await fetch('/api/comments', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            body,
            commentable_type: props.commentableType,
            commentable_id: props.commentableId,
            parent_id: parentId,
            'cf-turnstile-response': turnstileToken ?? '',
        }),
    });

    if (res.ok) {
        await fetchComments(1);
    }
}

async function editComment(commentId: string, body: string) {
    const res = await fetch(`/api/comments/${commentId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ body }),
    });

    if (res.ok) {
        await fetchComments(1);
    }
}

async function deleteComment(commentId: string) {
    const res = await fetch(`/api/comments/${commentId}`, {
        method: 'DELETE',
        headers: {
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    if (res.ok) {
        await fetchComments(1);
    }
}

async function reactToComment(commentId: string, emoji: string) {
    const res = await fetch(`/api/comments/${commentId}/react`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ emoji }),
    });

    if (res.ok) {
        const updated = await res.json();
        updateCommentInList(comments.value, commentId, updated);
    }
}

function updateCommentInList(
    list: CommentData[],
    id: string,
    updated: CommentData,
) {
    for (let i = 0; i < list.length; i++) {
        if (list[i].id === id || list[i]._id === id) {
            list[i].reactions = updated.reactions;
            return;
        }
        if (list[i].replies) {
            updateCommentInList(list[i].replies!, id, updated);
        }
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

onMounted(() => fetchComments());
</script>

<template>
    <section
        class="rounded-xl"
        style="
            background: hsl(var(--bg-elev));
            border: 1px solid hsl(var(--border-ahd));
        "
    >
        <div
            class="flex items-center justify-between border-b px-4 py-4 sm:px-6"
            :style="{
                borderColor: 'hsl(var(--border-ahd))',
            }"
        >
            <h3 class="font-display text-[22px] italic">
                ความคิดเห็น ({{ total }})
            </h3>
        </div>

        <div class="p-4 sm:p-6">
            <div v-if="isLoggedIn" class="mb-6">
                <div class="mb-2 flex items-center gap-2">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[12px] font-bold"
                        style="
                            background: hsl(var(--accent) / 0.12);
                            color: hsl(var(--accent));
                        "
                    >
                        {{ currentUserName?.charAt(0).toUpperCase() }}
                    </div>
                    <span
                        class="text-[14px] font-medium"
                        style="color: hsl(var(--fg))"
                    >
                        {{ currentUserName }}
                    </span>
                </div>
                <CommentForm @submit="submitComment" />
            </div>
            <div
                v-else
                class="mb-6 rounded-xl px-4 py-3 text-center"
                style="
                    background: hsl(var(--bg-soft));
                    border: 1px solid hsl(var(--border-ahd));
                "
            >
                <p class="text-[14px]" style="color: hsl(var(--fg-muted))">
                    <a
                        href="/member/login"
                        class="font-medium"
                        style="color: hsl(var(--accent))"
                        >เข้าสู่ระบบ</a
                    >
                    เพื่อแสดงความคิดเห็น
                </p>
            </div>

            <div
                v-if="loading && comments.length === 0"
                class="py-8 text-center text-[14px]"
                style="color: hsl(var(--fg-muted))"
            >
                กำลังโหลดความคิดเห็น...
            </div>

            <div v-else-if="comments.length" class="divide-y" style="border-color: hsl(var(--border-ahd))">
                <CommentItem
                    v-for="comment in comments"
                    :key="comment.id"
                    :comment="comment"
                    :current-user-id="currentUserId"
                    :is-admin="isAdmin"
                    @reply="submitComment"
                    @react="reactToComment"
                    @edit="editComment"
                    @delete="deleteComment"
                />
            </div>

            <div v-else class="py-8 text-center">
                <p class="text-[14px]" style="color: hsl(var(--fg-faint))">
                    ยังไม่มีความคิดเห็น เป็นคนแรกที่แสดงความคิดเห็น!
                </p>
            </div>

            <div v-if="currentPage < lastPage" class="mt-4 text-center">
                <button
                    type="button"
                    class="btn btn-ghost"
                    :disabled="loading"
                    @click="fetchComments(currentPage + 1)"
                >
                    {{ loading ? 'กำลังโหลด...' : 'โหลดเพิ่มเติม' }}
                </button>
            </div>
        </div>
    </section>
</template>
