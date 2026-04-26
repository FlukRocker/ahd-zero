<script setup lang="ts">
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<{ flash?: { status?: string } }>();
const flash = computed(() => page.props.flash?.status ?? null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put('/member/settings/password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

useSeo(() => ({ title: 'เปลี่ยนรหัสผ่าน', robots: 'noindex, nofollow' }));
</script>

<template>
    <Head title="เปลี่ยนรหัสผ่าน" />
    <FrontLayout>
        <section class="mx-auto max-w-3xl px-6 pt-12 pb-20 lg:px-10">
            <div
                class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                ตั้งค่า
            </div>
            <h1
                class="font-display mb-8 leading-none italic"
                style="font-size: clamp(36px, 5vw, 48px)"
            >
                รหัสผ่าน
            </h1>

            <nav class="mb-8 flex gap-4 border-b" style="border-color: hsl(var(--border-ahd))">
                <Link
                    href="/member/settings/profile"
                    class="border-b-2 border-transparent px-1 py-2 text-[14px]"
                    style="color: hsl(var(--fg-muted))"
                >
                    โปรไฟล์
                </Link>
                <Link
                    href="/member/settings/password"
                    class="border-b-2 px-1 py-2 text-[14px] font-medium"
                    style="border-color: hsl(var(--accent)); color: hsl(var(--fg))"
                >
                    รหัสผ่าน
                </Link>
            </nav>

            <div
                v-if="flash === 'password-updated'"
                class="mb-6 rounded-xl px-4 py-3 text-[13px]"
                style="
                    background: hsl(var(--accent-soft));
                    color: hsl(var(--accent));
                "
            >
                เปลี่ยนรหัสผ่านเรียบร้อยแล้ว
            </div>

            <form class="space-y-5 max-w-md" @submit.prevent="submit">
                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        รหัสผ่านปัจจุบัน
                    </label>
                    <input
                        v-model="form.current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                    <p
                        v-if="form.errors.current_password"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors.current_password }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        รหัสผ่านใหม่
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                    <p
                        v-if="form.errors.password"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        ยืนยันรหัสผ่านใหม่
                    </label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                </div>

                <button
                    type="submit"
                    class="btn btn-primary justify-center px-6"
                    :disabled="form.processing"
                >
                    <AhdIcon name="arrow" :size="14" /> เปลี่ยนรหัสผ่าน
                </button>
            </form>
        </section>
    </FrontLayout>
</template>
