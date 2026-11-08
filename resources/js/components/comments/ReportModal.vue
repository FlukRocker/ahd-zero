<script setup lang="ts">
import {
    submitReport,
    type ReportReason,
    type ReportTargetType,
} from '@/lib/reports';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    type: ReportTargetType;
    targetId: string;
}>();

const emit = defineEmits<{ close: [] }>();

const reasons: Array<{ value: ReportReason; label: string }> = [
    { value: 'spam', label: 'สแปม' },
    { value: 'abuse', label: 'ละเมิด' },
    { value: 'other', label: 'อื่นๆ' },
];

const reason = ref<ReportReason>('spam');
const note = ref('');
const busy = ref(false);
const message = ref('');
const failed = ref(false);

watch(
    () => props.open,
    (open) => {
        if (open) {
            reason.value = 'spam';
            note.value = '';
            message.value = '';
            failed.value = false;
        }
    },
);

async function submit() {
    busy.value = true;
    failed.value = false;

    try {
        const result = await submitReport(
            props.type,
            props.targetId,
            reason.value,
            note.value,
        );
        message.value =
            result === 'created'
                ? 'ขอบคุณ เราจะตรวจสอบ'
                : 'คุณรายงานรายการนี้แล้ว';
    } catch (e) {
        failed.value = true;
        message.value = e instanceof Error ? e.message : 'ส่งรายงานไม่สำเร็จ';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="report-backdrop" @click.self="emit('close')">
            <div class="report-card" role="dialog" aria-label="รายงาน">
                <h3 class="report-title">รายงาน</h3>

                <template v-if="message === '' || failed">
                    <label
                        v-for="r in reasons"
                        :key="r.value"
                        class="report-reason"
                    >
                        <input v-model="reason" type="radio" :value="r.value" />
                        {{ r.label }}
                    </label>
                    <textarea
                        v-model="note"
                        class="report-note"
                        maxlength="500"
                        rows="3"
                        placeholder="รายละเอียดเพิ่มเติม (ไม่บังคับ)"
                    />
                    <p v-if="failed" class="report-error">{{ message }}</p>
                    <div class="report-actions">
                        <button
                            type="button"
                            class="report-cancel"
                            @click="emit('close')"
                        >
                            ยกเลิก
                        </button>
                        <button
                            type="button"
                            class="report-send"
                            :disabled="busy"
                            @click="submit"
                        >
                            ส่งรายงาน
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p>{{ message }}</p>
                    <div class="report-actions">
                        <button
                            type="button"
                            class="report-send"
                            @click="emit('close')"
                        >
                            ปิด
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.report-backdrop {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.5);
    padding: 16px;
}
.report-card {
    width: 100%;
    max-width: 360px;
    border-radius: 16px;
    border: 1px solid hsl(var(--border-strong));
    background: hsl(var(--bg-elev));
    color: hsl(var(--fg));
    padding: 20px;
}
.report-title {
    margin-bottom: 12px;
    font-size: 18px;
    font-weight: 700;
}
.report-reason {
    display: flex;
    gap: 8px;
    padding: 4px 0;
    font-size: 14px;
}
.report-note {
    width: 100%;
    margin-top: 8px;
    resize: none;
    border-radius: 8px;
    border: 1px solid hsl(var(--border-ahd));
    background: hsl(var(--bg));
    padding: 8px 12px;
    font-size: 14px;
}
.report-error {
    margin-top: 8px;
    font-size: 13px;
    color: #e5484d;
}
.report-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 16px;
}
.report-cancel {
    font-size: 13px;
    color: hsl(var(--fg-muted));
}
.report-send {
    border-radius: 8px;
    background: hsl(var(--accent));
    color: hsl(var(--accent-fg));
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 700;
}
.report-send:disabled {
    opacity: 0.6;
}
</style>
