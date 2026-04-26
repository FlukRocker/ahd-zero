<script setup lang="ts">
import { renderMarkdown } from '@/composables/useMarkdown';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import CommentForm from './CommentForm.vue';
import ReactionBar from './ReactionBar.vue';

export interface CommentData {
    id: string;
    _id?: string;
    body: string;
    user_id: string | null;
    user_name: string;
    user_avatar: string | null;
    is_admin?: boolean;
    deleted_by?: 'author' | 'admin' | null;
    deleted_at?: string | null;
    reactions: Array<{ emoji: string; user_ids: string[] }>;
    created_at: string;
    replies?: CommentData[];
}

const props = defineProps<{
    comment: CommentData;
    currentUserId: string | null;
    isAdmin: boolean;
    depth?: number;
}>();

const emit = defineEmits<{
    reply: [body: string, parentId: string, turnstileToken?: string];
    react: [commentId: string, emoji: string];
    edit: [commentId: string, body: string];
    delete: [commentId: string];
}>();

const showReplyForm = ref(false);
const editing = ref(false);
const menuOpen = ref(false);
const showDeleteConfirm = ref(false);
const editBody = ref('');
const menuBtnRef = ref<HTMLButtonElement | null>(null);
const menuStyle = ref({ top: '0px', left: '0px' });

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
    if (menuOpen.value && menuBtnRef.value) {
        const rect = menuBtnRef.value.getBoundingClientRect();
        menuStyle.value = {
            top: `${rect.bottom + 4}px`,
            left: `${rect.right - 144}px`,
        };
    }
}

function onClickOutside(e: MouseEvent) {
    if (
        menuOpen.value &&
        menuBtnRef.value &&
        !menuBtnRef.value.contains(e.target as Node)
    ) {
        menuOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));

const isDeleted = computed(() => !!props.comment.deleted_by);
const renderedBody = computed(() =>
    isDeleted.value ? '' : renderMarkdown(props.comment.body),
);

const timeAgo = computed(() => {
    const diff = Date.now() - new Date(props.comment.created_at).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'เมื่อสักครู่';
    if (mins < 60) return `${mins} นาทีที่แล้ว`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} ชั่วโมงที่แล้ว`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days} วันที่แล้ว`;
    return new Date(props.comment.created_at).toLocaleDateString('th-TH');
});

const isOwner = computed(
    () => props.currentUserId === props.comment.user_id,
);
const canDelete = computed(() => isOwner.value || props.isAdmin);

function startEdit() {
    editBody.value = props.comment.body;
    editing.value = true;
}

function submitEdit() {
    if (editBody.value.trim()) {
        emit('edit', props.comment.id, editBody.value);
        editing.value = false;
    }
}

function handleReply(
    body: string,
    _parentId: string | null,
    turnstileToken?: string,
) {
    emit('reply', body, props.comment.id, turnstileToken);
    showReplyForm.value = false;
}
</script>

<template>
    <div :class="depth && depth > 0 ? 'ml-6 sm:ml-10 pl-4 sm:pl-6 nested' : ''">
        <div class="flex gap-3 py-3">
            <div class="avatar">
                <img
                    v-if="comment.user_avatar"
                    :src="comment.user_avatar"
                    :alt="comment.user_name"
                    class="h-full w-full rounded-full object-cover"
                />
                <template v-else>
                    {{ comment.user_name.charAt(0).toUpperCase() }}
                </template>
            </div>

            <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span
                            class="text-[14px] font-semibold"
                            style="color: hsl(var(--fg))"
                        >
                            {{ comment.user_name }}
                        </span>
                        <span
                            v-if="comment.is_admin"
                            class="admin-badge"
                        >
                            Admin
                        </span>
                        <span
                            class="text-[12px]"
                            style="color: hsl(var(--fg-faint))"
                        >
                            {{ timeAgo }}
                        </span>
                    </div>

                    <button
                        v-if="!isDeleted && (isOwner || isAdmin)"
                        ref="menuBtnRef"
                        type="button"
                        class="menu-trigger"
                        aria-label="เมนู"
                        @click.stop="toggleMenu"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="5" r="1" />
                            <circle cx="12" cy="12" r="1" />
                            <circle cx="12" cy="19" r="1" />
                        </svg>
                    </button>
                </div>

                <div v-if="isDeleted" class="deleted-block">
                    <p class="italic text-[14px]" style="color: hsl(var(--fg-faint))">
                        {{ comment.deleted_by === 'admin' ? 'ความคิดเห็นนี้ถูกลบโดยแอดมิน' : 'ความคิดเห็นนี้ถูกลบโดยผู้เขียน' }}
                        <span
                            v-if="comment.deleted_at"
                            class="text-[11px]"
                            style="color: hsl(var(--fg-faint))"
                        > — {{ new Date(comment.deleted_at).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                    </p>
                </div>

                <template v-else>
                    <div
                        v-if="!editing"
                        class="prose-body"
                        v-html="renderedBody"
                    />

                    <div v-else class="space-y-2">
                        <textarea
                            v-model="editBody"
                            rows="3"
                            class="edit-textarea"
                        />
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="btn-save"
                                @click="submitEdit"
                            >
                                บันทึก
                            </button>
                            <button
                                type="button"
                                class="btn-cancel-edit"
                                @click="editing = false"
                            >
                                ยกเลิก
                            </button>
                        </div>
                    </div>

                    <div v-if="!editing" class="mt-2">
                        <ReactionBar
                            :reactions="comment.reactions || []"
                            :current-user-id="currentUserId"
                            @react="(emoji) => emit('react', comment.id, emoji)"
                        />
                    </div>
                </template>

                <div v-if="showReplyForm" class="mt-3">
                    <CommentForm
                        :parent-id="comment.id"
                        placeholder="ตอบกลับ..."
                        @submit="handleReply"
                        @cancel="showReplyForm = false"
                    />
                </div>

                <div v-if="comment.replies?.length">
                    <CommentItem
                        v-for="reply in comment.replies"
                        :key="reply.id"
                        :comment="reply"
                        :current-user-id="currentUserId"
                        :is-admin="isAdmin"
                        :depth="(depth ?? 0) + 1"
                        @reply="(body, parentId, token) => emit('reply', body, parentId, token)"
                        @react="(commentId, emoji) => emit('react', commentId, emoji)"
                        @edit="(commentId, body) => emit('edit', commentId, body)"
                        @delete="(commentId) => emit('delete', commentId)"
                    />
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="menuOpen"
                class="dropdown-menu"
                :style="menuStyle"
                @click.stop
            >
                <button
                    type="button"
                    class="dropdown-item"
                    @click="showReplyForm = !showReplyForm; menuOpen = false"
                >
                    ตอบกลับ
                </button>
                <button
                    v-if="isOwner"
                    type="button"
                    class="dropdown-item"
                    @click="startEdit(); menuOpen = false"
                >
                    แก้ไข
                </button>
                <button
                    v-if="canDelete"
                    type="button"
                    class="dropdown-item dropdown-item-danger"
                    @click="showDeleteConfirm = true; menuOpen = false"
                >
                    ลบ
                </button>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="showDeleteConfirm"
                class="modal-backdrop"
                @click.self="showDeleteConfirm = false"
            >
                <div class="modal-card">
                    <h3 class="font-display text-[20px] mb-2 italic">
                        ยืนยันการลบ
                    </h3>
                    <p class="mb-6 text-[14px]" style="color: hsl(var(--fg-muted))">
                        คุณต้องการลบความคิดเห็นนี้หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้
                    </p>
                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="btn-modal-cancel"
                            @click="showDeleteConfirm = false"
                        >
                            ยกเลิก
                        </button>
                        <button
                            type="button"
                            class="btn-modal-danger"
                            @click="emit('delete', comment.id); showDeleteConfirm = false"
                        >
                            ลบความคิดเห็น
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.nested {
    border-left: 1px solid hsl(var(--border-ahd));
}

.avatar {
    height: 32px;
    width: 32px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: hsl(var(--bg-soft));
    color: hsl(var(--fg-muted));
    font-size: 12px;
    font-weight: 700;
}

.admin-badge {
    padding: 2px 6px;
    border-radius: 4px;
    background: hsl(var(--accent));
    color: hsl(var(--accent-fg));
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.menu-trigger {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: hsl(var(--fg-faint));
    transition: background 0.15s, color 0.15s;
}

.menu-trigger:hover {
    background: hsl(var(--bg-soft));
    color: hsl(var(--fg));
}

.deleted-block {
    margin-top: 4px;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid hsl(var(--border-ahd));
    background: hsl(var(--bg-soft));
}

.prose-body {
    font-size: 14px;
    color: hsl(var(--fg));
    line-height: 1.5;
}

.prose-body :deep(p) {
    margin: 4px 0;
}

.prose-body :deep(pre) {
    background: hsl(var(--bg-soft));
    border-radius: 8px;
    padding: 8px 12px;
    overflow-x: auto;
    font-size: 12px;
}

.prose-body :deep(code) {
    background: hsl(var(--bg-soft));
    padding: 1px 4px;
    border-radius: 4px;
    font-size: 12px;
}

.prose-body :deep(a) {
    color: hsl(var(--accent));
    text-decoration: underline;
}

.edit-textarea {
    width: 100%;
    resize: none;
    border-radius: 8px;
    border: 1px solid hsl(var(--border-ahd));
    background: hsl(var(--bg-elev));
    padding: 8px 12px;
    font-size: 14px;
    color: hsl(var(--fg));
    outline: none;
}

.edit-textarea:focus {
    border-color: hsl(var(--accent));
}

.btn-save {
    padding: 4px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    background: hsl(var(--accent));
    color: hsl(var(--accent-fg));
}

.btn-cancel-edit {
    padding: 4px 12px;
    border-radius: 8px;
    font-size: 12px;
    color: hsl(var(--fg-muted));
}

.btn-cancel-edit:hover {
    color: hsl(var(--fg));
}

.dropdown-menu {
    position: fixed;
    z-index: 50;
    width: 144px;
    overflow: hidden;
    border-radius: 12px;
    border: 1px solid hsl(var(--border-strong));
    background: hsl(var(--bg-elev));
    padding: 4px 0;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
}

.dropdown-item {
    width: 100%;
    text-align: left;
    padding: 8px 12px;
    font-size: 12px;
    color: hsl(var(--fg-muted));
    transition: background 0.15s, color 0.15s;
}

.dropdown-item:hover {
    background: hsl(var(--bg-soft));
    color: hsl(var(--fg));
}

.dropdown-item-danger {
    color: hsl(0 70% 50%);
}

.dropdown-item-danger:hover {
    color: hsl(0 78% 56%);
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(4px);
}

.modal-card {
    margin: 0 16px;
    width: 100%;
    max-width: 384px;
    border-radius: 16px;
    border: 1px solid hsl(var(--border-strong));
    background: hsl(var(--bg-elev));
    padding: 24px;
    box-shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
}

.btn-modal-cancel {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: hsl(var(--fg-muted));
}

.btn-modal-cancel:hover {
    background: hsl(var(--bg-soft));
    color: hsl(var(--fg));
}

.btn-modal-danger {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    background: hsl(0 78% 56%);
    color: white;
}

.btn-modal-danger:hover {
    background: hsl(0 78% 48%);
}
</style>
