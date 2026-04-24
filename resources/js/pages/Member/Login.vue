<script setup lang="ts">
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<{ siteConfig?: { registrationEnabled?: boolean } }>();
const registrationEnabled = computed(
    () => page.props.siteConfig?.registrationEnabled !== false,
);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/member/login', {
        onFinish: () => form.reset('password'),
    });
}

useSeo(() => ({ title: 'เข้าสู่ระบบ', robots: 'noindex, follow' }));
</script>

<template>
    <Head title="เข้าสู่ระบบ" />
    <FrontLayout>
        <section class="mx-auto max-w-md px-6 pt-20 pb-24 lg:px-10">
            <div
                class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                สมาชิก
            </div>
            <h1
                class="font-display mb-8 leading-none italic"
                style="font-size: clamp(44px, 6vw, 64px)"
            >
                ยินดีต้อนรับกลับ
            </h1>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        อีเมล
                    </label>
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                    <p
                        v-if="form.errors.email"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        รหัสผ่าน
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                </div>

                <label
                    class="flex items-center gap-2 text-[13px]"
                    style="color: hsl(var(--fg-muted))"
                >
                    <input v-model="form.remember" type="checkbox" />
                    จดจำฉัน
                </label>

                <button
                    type="submit"
                    class="btn btn-primary w-full justify-center"
                    :disabled="form.processing"
                >
                    <AhdIcon name="arrow" :size="14" /> เข้าสู่ระบบ
                </button>

                <p
                    v-if="registrationEnabled"
                    class="text-center text-[13px]"
                    style="color: hsl(var(--fg-muted))"
                >
                    ยังไม่มีบัญชี?
                    <Link
                        href="/member/register"
                        class="u-grow"
                        style="color: hsl(var(--accent))"
                        >สมัครสมาชิก</Link
                    >
                </p>
                <p
                    v-else
                    class="text-center font-mono text-[13px]"
                    style="color: hsl(var(--fg-faint))"
                >
                    ปิดรับสมาชิกใหม่
                </p>
            </form>
        </section>
    </FrontLayout>
</template>
