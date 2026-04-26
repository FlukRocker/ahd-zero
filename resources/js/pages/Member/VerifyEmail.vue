<script setup lang="ts">
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ pendingEmail?: string | null }>();

const page = usePage<{
    flash?: { status?: string };
    errors?: Record<string, string>;
}>();

const sent = computed(() => page.props.flash?.status === 'sent');
const errorMessage = computed(() => page.props.errors?.email ?? '');

// Resend form is empty — the controller looks up the member from session
// (or from the authenticated guard) so no payload is required.
const form = useForm({});

function resend() {
    form.post('/member/email/verification-notification', { preserveScroll: true });
}

useSeo(() => ({ title: 'ยืนยันอีเมล', robots: 'noindex, nofollow' }));
</script>

<template>
    <Head title="ยืนยันอีเมล" />
    <FrontLayout>
        <section class="mx-auto max-w-md px-6 pt-20 pb-24 lg:px-10">
            <div
                class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                สมาชิก
            </div>
            <h1
                class="font-display mb-6 leading-none italic"
                style="font-size: clamp(40px, 6vw, 56px)"
            >
                ยืนยันอีเมลของคุณ
            </h1>

            <p class="mb-2 text-[15px]" style="color: hsl(var(--fg-muted))">
                เราได้ส่งลิงก์ยืนยันไปยัง
            </p>
            <p
                v-if="pendingEmail"
                class="mb-6 font-mono text-[14px]"
                style="color: hsl(var(--fg))"
            >
                {{ pendingEmail }}
            </p>
            <p class="mb-8 text-[14px]" style="color: hsl(var(--fg-muted))">
                เปิดอีเมลและกดลิงก์ยืนยันเพื่อใช้งานบัญชี
                หากไม่พบในกล่องจดหมาย กรุณาตรวจในโฟลเดอร์สแปม
            </p>

            <div
                v-if="sent"
                class="mb-4 rounded-xl px-4 py-3 text-[13px]"
                style="
                    background: hsl(var(--accent-soft));
                    color: hsl(var(--accent));
                "
            >
                ส่งอีเมลยืนยันเรียบร้อยแล้ว กรุณาตรวจสอบอีเมลของคุณ
            </div>

            <div
                v-if="errorMessage"
                class="mb-4 rounded-xl px-4 py-3 text-[13px]"
                style="
                    background: hsl(var(--accent-soft));
                    color: hsl(var(--accent));
                "
            >
                {{ errorMessage }}
            </div>

            <form @submit.prevent="resend">
                <button
                    type="submit"
                    class="btn btn-primary w-full justify-center"
                    :disabled="form.processing"
                >
                    <AhdIcon name="arrow" :size="14" /> ส่งอีเมลใหม่
                </button>
            </form>

            <p
                class="mt-6 text-center text-[13px]"
                style="color: hsl(var(--fg-muted))"
            >
                เข้าสู่ระบบด้วยบัญชีอื่น?
                <Link
                    href="/member/login"
                    class="u-grow"
                    style="color: hsl(var(--accent))"
                >เข้าสู่ระบบ</Link>
            </p>
        </section>
    </FrontLayout>
</template>
