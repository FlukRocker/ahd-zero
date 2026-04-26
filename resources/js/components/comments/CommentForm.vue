<script setup lang="ts">
import TurnstileWidget from '@/components/ahd/TurnstileWidget.vue';
import { renderMarkdown } from '@/composables/useMarkdown';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    parentId?: string | null;
    placeholder?: string;
}>();

const emit = defineEmits<{
    submit: [body: string, parentId: string | null, turnstileToken: string];
    cancel: [];
}>();

const page = usePage<{
    siteConfig?: { turnstileSiteKey?: string | null };
}>();
const turnstileSiteKey = computed(
    () => page.props.siteConfig?.turnstileSiteKey ?? '',
);

const body = ref('');
const preview = ref(false);
const submitting = ref(false);
const turnstileToken = ref('');
const turnstileRef = ref<InstanceType<typeof TurnstileWidget> | null>(null);

const renderedPreview = computed(() =>
    body.value ? renderMarkdown(body.value) : '',
);

async function submit() {
    if (!body.value.trim() || submitting.value) return;
    if (turnstileSiteKey.value && !turnstileToken.value) return;
    submitting.value = true;
    emit('submit', body.value, props.parentId ?? null, turnstileToken.value);
    body.value = '';
    preview.value = false;
    submitting.value = false;
    turnstileToken.value = '';
    turnstileRef.value?.reset();
}
</script>

<template>
    <div class="comment-form">
        <div class="mb-2 flex gap-2">
            <button
                type="button"
                class="tab"
                :class="{ active: !preview }"
                @click="preview = false"
            >
                เขียน
            </button>
            <button
                type="button"
                class="tab"
                :class="{ active: preview }"
                @click="preview = true"
            >
                ดูตัวอย่าง
            </button>
        </div>

        <textarea
            v-if="!preview"
            v-model="body"
            :placeholder="placeholder ?? 'เขียนความคิดเห็น... (รองรับ Markdown)'"
            rows="3"
            class="comment-textarea"
        />
        <div
            v-else
            class="prose-preview"
            v-html="renderedPreview || '<span style=&quot;color: hsl(var(--fg-faint))&quot;>ยังไม่มีเนื้อหา</span>'"
        />

        <TurnstileWidget
            v-if="turnstileSiteKey"
            ref="turnstileRef"
            v-model="turnstileToken"
            :site-key="turnstileSiteKey"
        />

        <div class="mt-2 flex items-center justify-between">
            <span
                class="text-[10px]"
                style="color: hsl(var(--fg-faint))"
            >
                Markdown & code highlighting
            </span>
            <div class="flex gap-2">
                <button
                    v-if="parentId"
                    type="button"
                    class="btn-cancel"
                    @click="emit('cancel')"
                >
                    ยกเลิก
                </button>
                <button
                    type="button"
                    class="btn-submit"
                    :disabled="
                        !body.trim() ||
                        submitting ||
                        (!!turnstileSiteKey && !turnstileToken)
                    "
                    @click="submit"
                >
                    {{ parentId ? 'ตอบกลับ' : 'แสดงความคิดเห็น' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.comment-form {
    border-radius: 12px;
    border: 1px solid hsl(var(--border-ahd));
    background: hsl(var(--bg-elev));
    padding: 12px;
}

.tab {
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    color: hsl(var(--fg-muted));
    transition: background 0.15s, color 0.15s;
}

.tab.active {
    background: hsl(var(--bg-soft));
    color: hsl(var(--fg));
}

.tab:hover {
    color: hsl(var(--fg));
}

.comment-textarea {
    width: 100%;
    resize: none;
    border-radius: 8px;
    border: 1px solid hsl(var(--border-ahd));
    background: hsl(var(--bg));
    padding: 8px 12px;
    font-size: 14px;
    color: hsl(var(--fg));
    outline: none;
}

.comment-textarea::placeholder {
    color: hsl(var(--fg-faint));
}

.comment-textarea:focus {
    border-color: hsl(var(--accent));
}

.prose-preview {
    min-height: 76px;
    border-radius: 8px;
    border: 1px solid hsl(var(--border-ahd));
    background: hsl(var(--bg));
    padding: 8px 12px;
    font-size: 14px;
    color: hsl(var(--fg));
}

.btn-cancel {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    color: hsl(var(--fg-muted));
}

.btn-cancel:hover {
    color: hsl(var(--fg));
}

.btn-submit {
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    background: hsl(var(--accent));
    color: hsl(var(--accent-fg));
    transition: opacity 0.15s;
}

.btn-submit:hover:not(:disabled) {
    opacity: 0.9;
}

.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
